<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =========================================================================
 * BƯỚC 5: PHÁT SỰ KIỆN CHUẨN GIAO ƯỚC (IDEMPOTENT) & BỘ ĐƠN MẪU O-1001..O-1008
 * =========================================================================
 */

/**
 * 1. Lấy mã đơn chuẩn giao ước (ưu tiên O-1001..O-1008 nếu là đơn mẫu, ngược lại dùng ORD-<ID>)
 */
function mammart_get_contract_order_code( $order ) {
    if ( ! $order ) {
        return '';
    }
    $sample_code = $order->get_meta( '_mammart_sample_code' );
    return $sample_code ? $sample_code : 'ORD-' . $order->get_id();
}

// Hiển thị kèm mã O-1001..O-1008 ngay trên danh sách Orders của WooCommerce
add_filter( 'woocommerce_order_number', function ( $order_number, $order ) {
    if ( is_a( $order, 'WC_Order' ) ) {
        $sample_code = $order->get_meta( '_mammart_sample_code' );
        if ( $sample_code ) {
            return $sample_code . ' (#' . $order_number . ')';
        }
    }
    return $order_number;
}, 10, 2 );

/**
 * 2. Chuẩn hóa dữ liệu đơn hàng theo bảng trường quy ước (Mục 3 thẻ bàn giao)
 * Lưu ý: Nếu nhóm thống nhất đổi tên key trong Mục 3, chỉ cần sửa tên key ở mảng return bên dưới.
 */
function mammart_build_order_contract_payload( $order ) {
    $order_code     = mammart_get_contract_order_code( $order );
    $payment_method = $order->get_payment_method(); // 'cod' hoặc 'sepay'
    $raw_status     = $order->get_status();
    $pay_status     = $order->get_meta( '_mammart_payment_status' );

    if ( empty( $pay_status ) ) {
        $pay_status = ( 'completed' === $raw_status ) ? 'Paid' : 'Unpaid';
    }

    // Chuẩn hóa trạng thái giao vận để Long/Phúc/Liêm đọc trực tiếp
    $delivery_map = array(
        'pending'        => 'pending_payment',
        'on-hold'        => 'awaiting_confirmation',
        'cod-awaiting'   => 'awaiting_confirmation',
        'confirmed'      => 'confirmed_awaiting_pack',
        'processing'     => 'packing',
        'shipping'       => 'shipping',
        'completed'      => 'delivered',
        'cancelled'      => 'cancelled',
        'refund-pending' => 'cancelled_refund_pending',
    );
    $delivery_status = $delivery_map[ $raw_status ] ?? $raw_status;
    $cod_collected   = ( 'cod' === $payment_method && 'Paid' === $pay_status );

    $items = array();
    foreach ( $order->get_items() as $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }
        $product = $item->get_product();
        $qty     = (int) $item->get_quantity();
        $total   = (float) $item->get_total();

        $items[] = array(
            'product_id' => $item->get_product_id(),
            'sku'        => $product ? ( $product->get_sku() ?: 'SKU-' . $item->get_product_id() ) : 'N/A',
            'name'       => $item->get_name(),
            'quantity'   => $qty,
            'unit_price' => $qty > 0 ? round( $total / $qty, 0 ) : 0,
            'line_total' => $total,
        );
    }

    return array(
        'order_code'        => $order_code,
        'woo_order_id'      => $order->get_id(),
        'customer_id'       => (int) $order->get_user_id(),
        'customer_name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
        'customer_phone'    => $order->get_billing_phone(),
        'customer_email'    => $order->get_billing_email(),
        'shipping_province' => $order->get_shipping_state() ?: $order->get_billing_state(),
        'order_status'      => $raw_status,
        'delivery_status'   => $delivery_status,
        'payment_method'    => $payment_method,
        'payment_status'    => $pay_status,
        'cod_collected'     => $cod_collected,
        'sepay_reference'   => $order->get_meta( '_mammart_sepay_ref' ) ?: null,
        'shipping_fee'      => (float) $order->get_shipping_total(),
        'total_amount'      => (float) $order->get_total(),
        'currency'          => $order->get_currency(),
        'cancel_reason'     => $order->get_meta( '_mammart_cancel_reason' ) ?: null,
        'cancelled_by'      => $order->get_meta( '_mammart_cancelled_by' ) ?: null,
        'items'             => $items,
    );
}

/**
 * 3. Bộ phát sự kiện Idempotent (Chống lặp 2 lần theo khóa event_name + order_code)
 * Hỗ trợ 4 sự kiện: OrderPlaced, PaymentConfirmed, OrderCompleted, OrderCancelled
 */
function mammart_emit_contract_event( $event_name, $order, $occurred_at = null, $extra_meta = array() ) {
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }
    if ( ! $order ) {
        return null;
    }

    $allowed_events = array( 'OrderPlaced', 'PaymentConfirmed', 'OrderCompleted', 'OrderCancelled' );
    if ( ! in_array( $event_name, $allowed_events, true ) ) {
        return null;
    }

    $order_code = mammart_get_contract_order_code( $order );
    $dedupe_key = $event_name . ':' . $order_code;

    // Kiểm tra khóa chống trùng lặp (để Long không bao giờ tính kỳ nhắc 2 lần)
    $emitted_index = get_option( 'mammart_emitted_contract_events', array() );
    if ( isset( $emitted_index[ $dedupe_key ] ) ) {
        mammart_append_event_audit_log( sprintf(
            "[%s] [IGNORED_DUPLICATE_EVENT] Event: %s | Order: %s | Existing event_id: %s -> Bỏ qua, không phát lặp lần 2.",
            current_time( 'Y-m-d H:i:s' ),
            $event_name,
            $order_code,
            $emitted_index[ $dedupe_key ]['event_id']
        ) );
        return $emitted_index[ $dedupe_key ];
    }

    $timestamp = $occurred_at ? $occurred_at : current_time( 'c' ); // ISO-8601 kèm múi giờ +07:00
    $event_id  = 'EVT-' . strtoupper( substr( md5( $dedupe_key . '|' . wp_generate_uuid4() ), 0, 12 ) );

    $event_record = array_merge(
        array(
            'event_id'       => $event_id,
            'event_name'     => $event_name,
            'occurred_at'    => $timestamp,
            'idempotency_key'=> $dedupe_key,
        ),
        mammart_build_order_contract_payload( $order ),
        $extra_meta
    );

    // Lưu chỉ mục chống trùng
    $emitted_index[ $dedupe_key ] = array(
        'event_id'    => $event_id,
        'event_name'  => $event_name,
        'order_code'  => $order_code,
        'occurred_at' => $timestamp,
    );
    update_option( 'mammart_emitted_contract_events', $emitted_index, false );

    // Lưu vào danh sách sự kiện đầy đủ & xuất ra file JSON/JSONL cho Long, Phúc, Liêm
    $all_events   = get_option( 'mammart_contract_events_stream', array() );
    $all_events[] = $event_record;
    update_option( 'mammart_contract_events_stream', $all_events, false );

    $order->update_meta_data( '_mammart_evt_' . strtolower( $event_name ), $event_id );
    $order->add_order_note( sprintf( '[SỰ KIỆN - %s] Đã phát %s (event_id: %s | lúc: %s).', $order_code, $event_name, $event_id, $timestamp ) );
    $order->save();

    mammart_sync_contract_export_files();
    mammart_append_event_audit_log( sprintf(
        "[%s] [EMITTED] Event: %s | Order: %s | event_id: %s | occurred_at: %s",
        current_time( 'Y-m-d H:i:s' ),
        $event_name,
        $order_code,
        $event_id,
        $timestamp
    ) );

    return $event_record;
}

function mammart_append_event_audit_log( $line ) {
    $upload_dir = wp_upload_dir();
    $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'mammart-contract-events.log';
    file_put_contents( $log_file, $line . "\n", FILE_APPEND );
}

/**
 * Xuất đồng bộ ra 2 file trong wp-content/uploads/ để đồng đội đọc độc lập:
 * - mammart-contract-events.json (gồm cả danh sách events và snapshot O-1001..O-1008)
 * - mammart-contract-events.jsonl (mỗi dòng 1 sự kiện JSON)
 */
function mammart_sync_contract_export_files() {
    $upload_dir = wp_upload_dir();
    $base_dir   = trailingslashit( $upload_dir['basedir'] );
    $all_events = get_option( 'mammart_contract_events_stream', array() );

    $sample_orders = array();
    $orders        = wc_get_orders( array(
        'limit'      => 20,
        'orderby'    => 'ID',
        'order'      => 'ASC',
        'meta_query' => array(
            array(
                'key'     => '_mammart_sample_code',
                'compare' => 'EXISTS',
            ),
        ),
    ) );

    foreach ( $orders as $order ) {
        $sample_orders[] = mammart_build_order_contract_payload( $order );
    }

    $bundle = array(
        'schema_version' => '1.0-handover-week2',
        'generated_at'   => current_time( 'c' ),
        'total_events'   => count( $all_events ),
        'events'         => $all_events,
        'sample_orders'  => $sample_orders,
    );

    file_put_contents(
        $base_dir . 'mammart-contract-events.json',
        wp_json_encode( $bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
    );

    $jsonl_lines = '';
    foreach ( $all_events as $evt ) {
        $jsonl_lines .= wp_json_encode( $evt, JSON_UNESCAPED_UNICODE ) . "\n";
    }
    file_put_contents( $base_dir . 'mammart-contract-events.jsonl', $jsonl_lines );
}

/**
 * 4. Tự động gắn hook phát 4 sự kiện trên luồng đơn hàng thật
 */
// 4.1. OrderPlaced khi tạo đơn mới
add_action( 'woocommerce_checkout_order_created', function ( $order ) {
    if ( 'cod' !== $order->get_payment_method() && ! $order->get_meta( '_mammart_sample_code' ) ) {
        mammart_emit_contract_event( 'OrderPlaced', $order );
    }
}, 25 );

add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
    if ( 'cod' !== $order->get_payment_method() && ! $order->get_meta( '_mammart_sample_code' ) ) {
        mammart_emit_contract_event( 'OrderPlaced', $order );
    }
}, 25 );

add_action( 'woocommerce_order_status_cod-awaiting', function ( $order_id, $order ) {
    if ( ! $order->get_meta( '_mammart_sample_code' ) ) {
        mammart_emit_contract_event( 'OrderPlaced', $order );
    }
}, 25, 2 );

// 4.2. PaymentConfirmed khi SePay xác nhận Paid (đơn chuyển sang Confirmed với trạng thái Paid)
add_action( 'woocommerce_order_status_confirmed', function ( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( $order && ! $order->get_meta( '_mammart_sample_code' ) && 'Paid' === $order->get_meta( '_mammart_payment_status' ) ) {
        mammart_emit_contract_event( 'PaymentConfirmed', $order );
    }
}, 30, 2 );

// 4.3. Khi đơn Hoàn thành (Completed):
// - Nếu là COD: tự động ghi nhận đã thu tiền COD (Paid) -> phát PaymentConfirmed trước rồi phát OrderCompleted
// - Nếu là SePay: phát OrderCompleted
add_action( 'woocommerce_order_status_completed', function ( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order || $order->get_meta( '_mammart_sample_code' ) ) {
        return;
    }

    if ( 'cod' === $order->get_payment_method() && 'Paid' !== $order->get_meta( '_mammart_payment_status' ) ) {
        $order->update_meta_data( '_mammart_payment_status', 'Paid' );
        $order->update_meta_data( '_mammart_paid_at', current_time( 'mysql' ) );
        $order->add_order_note( '[COD ĐÃ THU] Giao hàng thành công -> Ghi nhận đã thu tiền COD (Paid).' );
        $order->save();

        mammart_emit_contract_event( 'PaymentConfirmed', $order );
    }

    mammart_emit_contract_event( 'OrderCompleted', $order );
}, 30, 2 );

// 4.4. OrderCancelled khi đơn chuyển sang Cancelled hoặc Refund_Pending (chống phát lặp 2 lần)
add_action( 'woocommerce_order_status_cancelled', function ( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( $order && ! $order->get_meta( '_mammart_sample_code' ) ) {
        mammart_emit_contract_event( 'OrderCancelled', $order );
    }
}, 30, 2 );

add_action( 'woocommerce_order_status_refund-pending', function ( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( $order && ! $order->get_meta( '_mammart_sample_code' ) ) {
        mammart_emit_contract_event( 'OrderCancelled', $order );
    }
}, 30, 2 );

/**
 * 5. REST API Read-Only để Long, Phúc, Liêm gọi thử độc lập nếu cần
 * - GET /wp-json/mammart/v1/contract-events
 * - GET /wp-json/mammart/v1/sample-orders
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'mammart/v1', '/contract-events', array(
        'methods'             => 'GET',
        'callback'            => function () {
            return new WP_REST_Response( array(
                'total'  => count( get_option( 'mammart_contract_events_stream', array() ) ),
                'events' => get_option( 'mammart_contract_events_stream', array() ),
            ), 200 );
        },
        'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
    ) );

    register_rest_route( 'mammart/v1', '/sample-orders', array(
        'methods'             => 'GET',
        'callback'            => function () {
            $orders = wc_get_orders( array(
                'limit'      => 20,
                'orderby'    => 'ID',
                'order'      => 'ASC',
                'meta_query' => array(
                    array(
                        'key'     => '_mammart_sample_code',
                        'compare' => 'EXISTS',
                    ),
                ),
            ) );
            $data = array();
            foreach ( $orders as $order ) {
                $data[] = mammart_build_order_contract_payload( $order );
            }
            return new WP_REST_Response( array(
                'total'         => count( $data ),
                'sample_orders' => $data,
            ), 200 );
        },
        'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
    ) );
} );

/**
 * 6. Trình tạo bộ 8 đơn mẫu chuẩn O-1001 đến O-1008
 * Đặc biệt:
 * - O-1006: Đã giao (completed) + COD đã thu (Paid, cod_collected = true)
 * - O-1008: Đang giao (shipping) + COD chưa thu (Unpaid, cod_collected = false)
 */
function mammart_seed_sample_orders_o1001_to_o1008() {
    // Xóa bộ đơn mẫu cũ nếu bấm chạy lại để không bị trùng
    $existing = wc_get_orders( array(
        'limit'      => 50,
        'meta_query' => array(
            array(
                'key'     => '_mammart_sample_code',
                'compare' => 'EXISTS',
            ),
        ),
    ) );

    $emitted_index = get_option( 'mammart_emitted_contract_events', array() );
    $all_events    = get_option( 'mammart_contract_events_stream', array() );

    foreach ( $existing as $old_order ) {
        $old_code = $old_order->get_meta( '_mammart_sample_code' );
        foreach ( array( 'OrderPlaced', 'PaymentConfirmed', 'OrderCompleted', 'OrderCancelled' ) as $ev ) {
            unset( $emitted_index[ $ev . ':' . $old_code ] );
        }
        $all_events = array_values( array_filter( $all_events, function ( $row ) use ( $old_code ) {
            return ( $row['order_code'] ?? '' ) !== $old_code;
        } ) );
        $old_order->delete( true );
    }
    update_option( 'mammart_emitted_contract_events', $emitted_index, false );
    update_option( 'mammart_contract_events_stream', $all_events, false );

    // Lấy tối đa 3 sản phẩm thật có sẵn trong cửa hàng
    $products = wc_get_products( array(
        'status' => 'publish',
        'limit'  => 3,
        'type'   => 'simple',
    ) );

    $scenarios = array(
        array(
            'code'           => 'O-1001',
            'desc'           => 'COD mới đặt - Chờ xác nhận, chưa thu tiền',
            'customer_name'  => 'Nguyễn Minh Anh',
            'phone'          => '0901001001',
            'email'          => 'minhanh.o1001@mammart.local',
            'province'       => 'TP. Hồ Chí Minh',
            'ship_fee'       => 20000,
            'payment_method' => 'cod',
            'payment_title'  => 'Thanh toán khi nhận hàng (COD)',
            'payment_status' => 'Unpaid',
            'status'         => 'on-hold',
            'stock_deducted' => 'no',
            'events'         => array( 'OrderPlaced' ),
        ),
        array(
            'code'           => 'O-1002',
            'desc'           => 'COD đã duyệt - Chờ đóng gói (Confirmed), chưa thu tiền',
            'customer_name'  => 'Trần Thu Hà',
            'phone'          => '0901001002',
            'email'          => 'thuha.o1002@mammart.local',
            'province'       => 'Hà Nội',
            'ship_fee'       => 30000,
            'payment_method' => 'cod',
            'payment_title'  => 'Thanh toán khi nhận hàng (COD)',
            'payment_status' => 'Unpaid',
            'status'         => 'confirmed',
            'stock_deducted' => 'yes',
            'events'         => array( 'OrderPlaced' ),
        ),
        array(
            'code'           => 'O-1003',
            'desc'           => 'SePay mới đặt - Chờ chuyển khoản, chưa trừ tồn',
            'customer_name'  => 'Lê Hoàng Nam',
            'phone'          => '0901001003',
            'email'          => 'hoangnam.o1003@mammart.local',
            'province'       => 'TP. Hồ Chí Minh',
            'ship_fee'       => 20000,
            'payment_method' => 'sepay',
            'payment_title'  => 'Chuyển khoản ngân hàng qua SePay (QR Code)',
            'payment_status' => 'Unpaid',
            'status'         => 'on-hold',
            'stock_deducted' => 'no',
            'events'         => array( 'OrderPlaced' ),
        ),
        array(
            'code'           => 'O-1004',
            'desc'           => 'SePay đã thanh toán đủ (Paid) - Chờ đóng gói (Confirmed)',
            'customer_name'  => 'Phạm Ngọc Mai',
            'phone'          => '0901001004',
            'email'          => 'ngocmai.o1004@mammart.local',
            'province'       => 'Đà Nẵng',
            'ship_fee'       => 30000,
            'payment_method' => 'sepay',
            'payment_title'  => 'Chuyển khoản ngân hàng qua SePay (QR Code)',
            'payment_status' => 'Paid',
            'sepay_ref'      => 'FT263181004',
            'status'         => 'confirmed',
            'stock_deducted' => 'yes',
            'events'         => array( 'OrderPlaced', 'PaymentConfirmed' ),
        ),
        array(
            'code'           => 'O-1005',
            'desc'           => 'SePay đã trả tiền - Đang đóng gói (Processing, đã khóa hủy)',
            'customer_name'  => 'Võ Gia Huy',
            'phone'          => '0901001005',
            'email'          => 'giahuy.o1005@mammart.local',
            'province'       => 'TP. Hồ Chí Minh',
            'ship_fee'       => 20000,
            'payment_method' => 'sepay',
            'payment_title'  => 'Chuyển khoản ngân hàng qua SePay (QR Code)',
            'payment_status' => 'Paid',
            'sepay_ref'      => 'FT263181005',
            'status'         => 'processing',
            'stock_deducted' => 'yes',
            'events'         => array( 'OrderPlaced', 'PaymentConfirmed' ),
        ),
        array(
            'code'           => 'O-1006',
            'desc'           => 'ĐÃ GIAO + COD ĐÃ THU (Completed + Paid)',
            'customer_name'  => 'Đặng Khánh Linh',
            'phone'          => '0901001006',
            'email'          => 'khanhlinh.o1006@mammart.local',
            'province'       => 'TP. Hồ Chí Minh',
            'ship_fee'       => 20000,
            'payment_method' => 'cod',
            'payment_title'  => 'Thanh toán khi nhận hàng (COD)',
            'payment_status' => 'Paid',
            'status'         => 'completed',
            'stock_deducted' => 'yes',
            'events'         => array( 'OrderPlaced', 'PaymentConfirmed', 'OrderCompleted' ),
        ),
        array(
            'code'           => 'O-1007',
            'desc'           => 'SePay đã trả tiền nhưng khách hủy trước đóng gói (Refund_Pending)',
            'customer_name'  => 'Bùi Thanh Tùng',
            'phone'          => '0901001007',
            'email'          => 'thanhtung.o1007@mammart.local',
            'province'       => 'Cần Thơ',
            'ship_fee'       => 30000,
            'payment_method' => 'sepay',
            'payment_title'  => 'Chuyển khoản ngân hàng qua SePay (QR Code)',
            'payment_status' => 'Refund_Pending',
            'sepay_ref'      => 'FT263181007',
            'status'         => 'refund-pending',
            'stock_deducted' => 'yes',
            'stock_restocked'=> 'yes',
            'cancel_reason'  => 'Đổi ý / Không còn nhu cầu mua',
            'cancelled_by'   => 'customer',
            'events'         => array( 'OrderPlaced', 'PaymentConfirmed', 'OrderCancelled' ),
        ),
        array(
            'code'           => 'O-1008',
            'desc'           => 'ĐANG GIAO + COD CHƯA THU (Shipping + Unpaid)',
            'customer_name'  => 'Huỳnh Quốc Bảo',
            'phone'          => '0901001008',
            'email'          => 'quocbao.o1008@mammart.local',
            'province'       => 'Đồng Nai',
            'ship_fee'       => 30000,
            'payment_method' => 'cod',
            'payment_title'  => 'Thanh toán khi nhận hàng (COD)',
            'payment_status' => 'Unpaid',
            'status'         => 'shipping',
            'stock_deducted' => 'yes',
            'events'         => array( 'OrderPlaced' ),
        ),
    );

    $base_ts = current_time( 'timestamp' ) - ( 8 * 3600 );

    foreach ( $scenarios as $idx => $sc ) {
        $order = wc_create_order( array(
            'customer_id' => get_current_user_id(),
            'status'      => 'pending',
        ) );

        // Gắn dòng sản phẩm mẫu (không làm ảnh hưởng tồn kho thực của shop khi seed)
        if ( ! empty( $products ) ) {
            $prod = $products[ $idx % count( $products ) ];
            $order->add_product( $prod, 1 );
        } else {
            $fee_item = new WC_Order_Item_Fee();
            $fee_item->set_name( 'Sữa bột Mầm Mart Mẫu #' . ( $idx + 1 ) );
            $fee_item->set_amount( 250000 );
            $fee_item->set_total( 250000 );
            $order->add_item( $fee_item );
        }

        // Gắn phí vận chuyển theo đúng quy tắc 20.000đ HCM / 30.000đ tỉnh khác
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_title( 'Phí giao hàng tiêu chuẩn (' . $sc['province'] . ')' );
        $shipping->set_method_id( 'flat_rate' );
        $shipping->set_total( $sc['ship_fee'] );
        $order->add_item( $shipping );

        $address = array(
            'first_name' => $sc['customer_name'],
            'email'      => $sc['email'],
            'phone'      => $sc['phone'],
            'address_1'  => 'Số ' . ( $idx + 10 ) . ' Đường Mẫu Kiểm Thử',
            'city'       => $sc['province'],
            'state'      => $sc['province'],
            'country'    => 'VN',
        );
        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );

        $order->set_payment_method( $sc['payment_method'] );
        $order->set_payment_method_title( $sc['payment_title'] );
        $order->calculate_totals();

        // Gắn các cờ meta chuẩn Mầm Mart
        $order->update_meta_data( '_mammart_sample_code', $sc['code'] );
        $order->update_meta_data( '_mammart_payment_status', $sc['payment_status'] );
        $order->update_meta_data( '_mammart_stock_deducted', $sc['stock_deducted'] );
        if ( ! empty( $sc['stock_restocked'] ) ) {
            $order->update_meta_data( '_mammart_stock_restocked', $sc['stock_restocked'] );
        }
        if ( ! empty( $sc['sepay_ref'] ) ) {
            $order->update_meta_data( '_mammart_sepay_ref', $sc['sepay_ref'] );
        }
        if ( 'Paid' === $sc['payment_status'] ) {
            $order->update_meta_data( '_mammart_paid_at', gmdate( 'Y-m-d H:i:s', $base_ts + ( $idx * 1800 ) + 300 ) );
        }
        if ( ! empty( $sc['cancel_reason'] ) ) {
            $order->update_meta_data( '_mammart_cancel_reason', $sc['cancel_reason'] );
            $order->update_meta_data( '_mammart_cancelled_by', $sc['cancelled_by'] );
            $order->update_meta_data( '_mammart_refund_amount', $order->get_total() );
        }

        $order->save();
        $order->update_status( $sc['status'], '[ĐƠN MẪU ' . $sc['code'] . '] ' . $sc['desc'] );

        // Phát chuỗi sự kiện theo đúng thứ tự thời gian của đơn mẫu
        foreach ( $sc['events'] as $step_idx => $event_name ) {
            $evt_time = gmdate( 'Y-m-d\TH:i:s+07:00', $base_ts + ( $idx * 1800 ) + ( $step_idx * 300 ) );
            mammart_emit_contract_event( $event_name, $order, $evt_time );
        }
    }

    mammart_sync_contract_export_files();
}

/**
 * 7. Trang quản trị Bước 5: WooCommerce -> Sự kiện & Đơn mẫu
 */
add_action( 'admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Sự kiện giao ước & Đơn mẫu O-1001..O-1008 (Bước 5)',
        'Sự kiện & Đơn mẫu',
        'manage_woocommerce',
        'mammart-contract-events',
        'mammart_render_contract_events_admin_page'
    );
} );

function mammart_render_contract_events_admin_page() {
    $notice = '';

    if ( isset( $_POST['mammart_b5_action'] ) && check_admin_referer( 'mammart_b5_nonce' ) ) {
        $act = sanitize_text_field( wp_unslash( $_POST['mammart_b5_action'] ) );

        if ( 'seed_samples' === $act ) {
            mammart_seed_sample_orders_o1001_to_o1008();
            $notice = 'Đã tạo mới bộ 8 đơn mẫu O-1001 → O-1008, phát sự kiện kèm event_id và xuất file JSON/JSONL!';
        } elseif ( 'test_duplicate_event' === $act ) {
            $sample_o1006 = wc_get_orders( array(
                'limit'      => 1,
                'meta_query' => array(
                    array(
                        'key'   => '_mammart_sample_code',
                        'value' => 'O-1006',
                    ),
                ),
            ) );
            if ( ! empty( $sample_o1006 ) ) {
                $before_count = count( get_option( 'mammart_contract_events_stream', array() ) );
                mammart_emit_contract_event( 'OrderCompleted', $sample_o1006[0] );
                mammart_emit_contract_event( 'PaymentConfirmed', $sample_o1006[0] );
                $after_count = count( get_option( 'mammart_contract_events_stream', array() ) );
                $notice      = sprintf(
                    'Đã bắn lặp lại OrderCompleted & PaymentConfirmed cho O-1006 -> Tổng số sự kiện trước: %d | sau: %d (không tăng thêm sự kiện trùng!).',
                    $before_count,
                    $after_count
                );
            } else {
                $notice = 'Vui lòng bấm "Tạo / Reset bộ 8 đơn mẫu" trước khi test bắn lặp.';
            }
        }
    }

    $sample_orders = wc_get_orders( array(
        'limit'      => 20,
        'orderby'    => 'ID',
        'order'      => 'ASC',
        'meta_query' => array(
            array(
                'key'     => '_mammart_sample_code',
                'compare' => 'EXISTS',
            ),
        ),
    ) );

    $all_events  = get_option( 'mammart_contract_events_stream', array() );
    $upload_dir  = wp_upload_dir();
    $audit_file  = trailingslashit( $upload_dir['basedir'] ) . 'mammart-contract-events.log';
    $audit_lines = file_exists( $audit_file ) ? file_get_contents( $audit_file ) : "Chưa có log phát sự kiện.\n";
    ?>
    <div class="wrap">
        <h1>Xuất Sự Kiện Giao Ước & Bộ Đơn Mẫu O-1001 → O-1008 (Bước 5)</h1>
        <p>
            Dữ liệu sự kiện và đơn mẫu tự động xuất ra <code>wp-content/uploads/mammart-contract-events.json</code> (và <code>.jsonl</code>) để <strong>Long, Phúc, Liêm</strong> dùng độc lập ở Tuần 3.
        </p>

        <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><strong><?php echo esc_html( $notice ); ?></strong></p></div>
        <?php endif; ?>

        <form method="post" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap;">
            <?php wp_nonce_field( 'mammart_b5_nonce' ); ?>
            <button type="submit" name="mammart_b5_action" value="seed_samples" class="button button-primary">
                1. Tạo / Reset bộ 8 đơn mẫu (O-1001 → O-1008)
            </button>
            <button type="submit" name="mammart_b5_action" value="test_duplicate_event" class="button">
                2. Test chống lặp sự kiện (Bắn lặp lại O-1006)
            </button>
            <a href="<?php echo esc_url( rest_url( 'mammart/v1/sample-orders' ) ); ?>" target="_blank" class="button">
                Xem API Đơn mẫu (JSON)
            </a>
            <a href="<?php echo esc_url( rest_url( 'mammart/v1/contract-events' ) ); ?>" target="_blank" class="button">
                Xem API Sự kiện (JSON)
            </a>
        </form>

        <h2>Bảng đối chiếu bộ 8 đơn mẫu (O-1001 → O-1008)</h2>
        <table class="widefat striped" style="margin-bottom:24px;">
            <thead>
                <tr>
                    <th>Mã mẫu</th>
                    <th>ID Woo</th>
                    <th>Khách hàng & Tỉnh</th>
                    <th>Phương thức</th>
                    <th>Trạng thái đơn / Giao vận</th>
                    <th>Trạng thái thu tiền</th>
                    <th>Tổng tiền</th>
                    <th>Các sự kiện đã phát (kèm event_id)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $sample_orders ) ) : ?>
                    <tr><td colspan="8">Chưa có bộ đơn mẫu. Bấm nút <strong>"1. Tạo / Reset bộ 8 đơn mẫu (O-1001 → O-1008)"</strong> ở trên để khởi tạo.</td></tr>
                <?php else : ?>
                    <?php foreach ( $sample_orders as $so ) :
                        $payload = mammart_build_order_contract_payload( $so );
                        $ev_tags = array();
                        foreach ( array( 'OrderPlaced', 'PaymentConfirmed', 'OrderCompleted', 'OrderCancelled' ) as $ev_name ) {
                            $eid = $so->get_meta( '_mammart_evt_' . strtolower( $ev_name ) );
                            if ( $eid ) {
                                $ev_tags[] = $ev_name . ' (' . $eid . ')';
                            }
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $payload['order_code'] ); ?></strong></td>
                            <td>#<?php echo esc_html( $payload['woo_order_id'] ); ?></td>
                            <td><?php echo esc_html( $payload['customer_name'] . ' — ' . $payload['shipping_province'] ); ?></td>
                            <td><code><?php echo esc_html( strtoupper( $payload['payment_method'] ) ); ?></code></td>
                            <td>
                                <strong><?php echo esc_html( $payload['order_status'] ); ?></strong><br>
                                <small><code><?php echo esc_html( $payload['delivery_status'] ); ?></code></small>
                            </td>
                            <td>
                                <?php if ( 'O-1006' === $payload['order_code'] ) : ?>
                                    <span style="color:#007017; font-weight:bold;">✅ Đã giao + COD đã thu (Paid)</span>
                                <?php elseif ( 'O-1008' === $payload['order_code'] ) : ?>
                                    <span style="color:#b32d2e; font-weight:bold;">⏳ Đang giao + COD chưa thu (Unpaid)</span>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $payload['payment_status'] ); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo wp_kses_post( wc_price( $payload['total_amount'] ) ); ?></td>
                            <td><small><?php echo esc_html( implode( ' | ', $ev_tags ) ); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>Nhật ký kiểm soát chống lặp sự kiện (<code>mammart-contract-events.log</code>)</h2>
        <textarea readonly rows="8" style="width:100%; font-family:monospace; background:#1e1e1e; color:#9cdcfe; padding:10px; margin-bottom:20px;"><?php echo esc_textarea( $audit_lines ); ?></textarea>

        <h2>Dữ liệu JSON sự kiện đã phát (Tổng: <?php echo count( $all_events ); ?> sự kiện)</h2>
        <textarea readonly rows="14" style="width:100%; font-family:monospace; background:#1e1e1e; color:#d4d4d4; padding:10px;"><?php echo esc_textarea( wp_json_encode( $all_events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
    </div>
    <?php
}
