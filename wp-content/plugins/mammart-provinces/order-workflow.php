<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =========================================================================
 * 1. TRẠNG THÁI ĐƠN HÀNG: Confirmed, Shipping, Refund_Pending & Nhãn Processing
 * =========================================================================
 */
add_action( 'init', function () {
    $custom_statuses = array(
        'wc-confirmed'      => 'Đã xác nhận / Chờ đóng gói (Confirmed)',
        'wc-shipping'       => 'Đang giao hàng (Shipping)',
        'wc-refund-pending' => 'Chờ hoàn tiền (Refund_Pending)',
    );

    foreach ( $custom_statuses as $slug => $label ) {
        register_post_status( $slug, array(
            'label'                     => $label,
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                $label . ' <span class="count">(%s)</span>',
                $label . ' <span class="count">(%s)</span>'
            ),
        ) );
    }
} );

add_filter( 'wc_order_statuses', function ( $order_statuses ) {
    $new_statuses = array();
    foreach ( $order_statuses as $key => $label ) {
        if ( 'wc-processing' === $key ) {
            // Chèn Confirmed ngay trước Processing và đổi nhãn Processing thành Đang đóng gói
            $new_statuses['wc-confirmed']  = 'Đã xác nhận / Chờ đóng gói (Confirmed)';
            $new_statuses['wc-processing'] = 'Đang đóng gói (Processing)';
            $new_statuses['wc-shipping']   = 'Đang giao hàng (Shipping)';
            continue;
        }
        $new_statuses[ $key ] = $label;
        if ( 'wc-cancelled' === $key ) {
            $new_statuses['wc-refund-pending'] = 'Chờ hoàn tiền (Refund_Pending)';
        }
    }
    return $new_statuses;
} );

/**
 * =========================================================================
 * 2. KIỂM SOÁT TỒN KHO: KHÔNG TRỪ LÚC TẠO ĐƠN - CHỈ TRỪ 1 LẦN KHI PAID / DUYỆT COD
 * =========================================================================
 */

// 2.1. Ghi chú lúc tạo đơn: chưa trừ tồn kho để tránh đơn SePay chưa thanh toán giam hàng 24h
function mammart_note_no_stock_deduct_at_checkout( $order ) {
    if ( ! $order || 'yes' === $order->get_meta( '_mammart_checkout_noted' ) ) {
        return;
    }
    $order->update_meta_data( '_mammart_checkout_noted', 'yes' );
    $order->add_order_note( '[TỒN KHO] Chưa trừ tồn khi tạo đơn (chỉ kiểm tra & trừ 1 lần khi SePay báo Paid hoặc Admin duyệt đơn COD).' );
    $order->save();
}
add_action( 'woocommerce_checkout_order_created', 'mammart_note_no_stock_deduct_at_checkout', 10 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'mammart_note_no_stock_deduct_at_checkout', 10 );

// 2.2. Chặn WooCommerce tự động trừ kho mặc định; chỉ mở khóa khi hàm kiểm tra của Mầm Mart gọi
add_filter( 'woocommerce_can_reduce_order_stock', function ( $can_reduce, $order ) {
    if ( ! $order || 'yes' === $order->get_meta( '_mammart_stock_deducted' ) ) {
        return false;
    }
    return ! empty( $GLOBALS['mammart_allow_stock_reduce'] );
}, 10, 2 );

add_action( 'woocommerce_reduce_order_stock', function ( $order ) {
    if ( $order ) {
        $order->update_meta_data( '_mammart_stock_deducted', 'yes' );
        $order->save();
    }
} );

/**
 * Kiểm tra tồn kho thực tế và trừ kho 1 lần duy nhất.
 * Trả về true nếu đủ hàng & đã trừ thành công; trả về false nếu hết hàng.
 */
function mammart_check_and_deduct_stock_once( $order, $source_label ) {
    if ( ! $order ) {
        return false;
    }

    // Cờ chống trừ lặp 2 lần
    if ( 'yes' === $order->get_meta( '_mammart_stock_deducted' ) ) {
        return true;
    }

    // Kiểm tra số lượng tồn thực tế từng sản phẩm trong đơn
    foreach ( $order->get_items() as $item ) {
        if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
            continue;
        }
        $product = $item->get_product();
        $qty     = (int) $item->get_quantity();

        if ( $product && $product->managing_stock() ) {
            $available = (int) $product->get_stock_quantity();
            if ( $available < $qty ) {
                return false; // Không đủ hàng tại thời điểm thanh toán / duyệt đơn
            }
        }
    }

    // Đủ hàng -> Mở khóa và trừ kho đúng 1 lần
    $GLOBALS['mammart_allow_stock_reduce'] = true;
   wc_reduce_stock_levels( $order->get_id() );
    $GLOBALS['mammart_allow_stock_reduce'] = false;

    $order = wc_get_order( $order->get_id() );
    $order->update_meta_data( '_mammart_stock_deducted', 'yes' );
    $order->add_order_note( '[TỒN KHO - TRỪ 1 LẦN] Kiểm tra đủ hàng & trừ tồn kho thành công (' . $source_label . ').' );
    $order->save();

    return true;
}

/**
 * 2.3. Xử lý khi SePay báo Paid:
 * - Đủ hàng -> Trừ kho 1 lần & chuyển sang Confirmed (Chờ đóng gói)
 * - Hết hàng lúc tiền về -> Không trừ kho, chuyển thẳng Refund_Pending (hoàn 100% gồm phí ship)
 */
function mammart_process_sepay_paid_order( $order_id, $order = null ) {
    static $processed = array();
    if ( isset( $processed[ $order_id ] ) ) {
        return;
    }

    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order || 'Paid' !== $order->get_meta( '_mammart_payment_status' ) ) {
        return;
    }
    if ( 'yes' === $order->get_meta( '_mammart_sepay_paid_handled' ) ) {
        return;
    }

    $processed[ $order_id ] = true;
    $order->update_meta_data( '_mammart_sepay_paid_handled', 'yes' );
    $order->save();

    $has_stock = mammart_check_and_deduct_stock_once( $order, 'SePay báo Paid' );

    if ( $has_stock ) {
        $order->update_status(
            'confirmed',
            'SePay xác nhận thanh toán đủ -> Đã trừ tồn kho 1 lần và chuyển sang Đã xác nhận / Chờ đóng gói (Confirmed).'
        );
    } else {
        $refund_total = $order->get_total();
        $order->update_meta_data( '_mammart_refund_amount', $refund_total );
        $order->save();

        $order->update_status(
            'refund-pending',
            sprintf(
                '[HẾT HÀNG LÚC TIỀN VỀ] Đơn SePay đã thanh toán nhưng sản phẩm không đủ tồn kho -> Chuyển Chờ hoàn tiền (Refund_Pending), hoàn 100%% tổng thanh toán (%s).',
                wp_strip_all_tags( wc_price( $refund_total ) )
            )
        );
    }
}
add_action( 'woocommerce_order_status_on-hold_to_processing', 'mammart_process_sepay_paid_order', 20, 2 );
add_action( 'woocommerce_order_status_pending_to_processing', 'mammart_process_sepay_paid_order', 20, 2 );

/**
 * 2.4. Xử lý khi Admin duyệt đơn COD (chuyển sang Confirmed):
 * Kiểm tra lại tồn kho lúc Admin bấm duyệt -> Đủ hàng thì trừ kho 1 lần; hết hàng thì chặn duyệt.
 */
add_action( 'woocommerce_order_status_confirmed', function ( $order_id, $order ) {
    if ( ! $order ) {
        $order = wc_get_order( $order_id );
    }
    if ( ! $order || 'cod' !== $order->get_payment_method() ) {
        return;
    }
    if ( 'yes' === $order->get_meta( '_mammart_stock_deducted' ) ) {
        return;
    }

    $has_stock = mammart_check_and_deduct_stock_once( $order, 'Admin duyệt đơn COD' );

    if ( ! $has_stock ) {
        set_transient( 'mammart_cod_stock_error_' . get_current_user_id(), $order->get_id(), 60 );
        $order->update_status(
            'on-hold',
            '[CẢNH BÁO TỒN KHO] Không thể duyệt đơn COD #' . $order->get_id() . ' sang Confirmed vì sản phẩm đã hết hàng.'
        );
    }
}, 10, 2 );

add_action( 'admin_notices', function () {
    $failed_order_id = get_transient( 'mammart_cod_stock_error_' . get_current_user_id() );
    if ( $failed_order_id ) {
        delete_transient( 'mammart_cod_stock_error_' . get_current_user_id() );
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo '<strong>Không thể duyệt đơn COD #' . esc_html( $failed_order_id ) . ':</strong> Sản phẩm trong đơn hiện không đủ tồn kho. Đơn đã được giữ lại ở trạng thái Chờ xác nhận.';
        echo '</p></div>';
    }
} );

/**
 * 2.5. Hoàn tồn kho 1 lần duy nhất khi đơn bị Hủy / Chờ hoàn tiền (chỉ hoàn nếu trước đó đã trừ tồn)
 */
function mammart_restock_order_once( $order ) {
    if ( ! $order || 'yes' === $order->get_meta( '_mammart_stock_restocked' ) ) {
        return;
    }

    $was_deducted = ( 'yes' === $order->get_meta( '_mammart_stock_deducted' ) );
    $order->update_meta_data( '_mammart_stock_restocked', 'yes' );

    if ( $was_deducted ) {
        wc_increase_stock_levels( $order );
        $order->add_order_note( '[TỒN KHO - HOÀN 1 LẦN] Đơn đã trừ tồn trước đó bị hủy -> Đã hoàn lại tồn kho 1 lần duy nhất.' );
    } else {
        $order->add_order_note( '[TỒN KHO] Đơn chưa trừ tồn kho trước đó nên không cộng hoàn tồn.' );
    }

    $order->save();
}

add_action( 'woocommerce_order_status_cancelled', function ( $order_id, $order ) {
    mammart_restock_order_once( $order ? $order : wc_get_order( $order_id ) );
}, 20, 2 );

add_action( 'woocommerce_order_status_refund-pending', function ( $order_id, $order ) {
    mammart_restock_order_once( $order ? $order : wc_get_order( $order_id ) );
}, 20, 2 );

/**
 * =========================================================================
 * 3. METABOX ADMIN: XEM CỜ TỒN KHO, ĐỐI SOÁT TIỀN TRỄ & ADMIN HỦY KÈM LÝ DO
 * =========================================================================
 */
add_action( 'add_meta_boxes', function () {
    foreach ( array( 'woocommerce_page_wc-orders', 'shop_order' ) as $screen ) {
        add_meta_box(
            'mammart_order_workflow_box',
            'Mầm Mart: Kiểm Soát Tồn Kho, Hủy & Đối Soát',
            'mammart_render_admin_workflow_metabox',
            $screen,
            'side',
            'high'
        );
    }
} );

function mammart_render_admin_workflow_metabox( $post_or_order ) {
    $order = is_a( $post_or_order, 'WC_Order' ) ? $post_or_order : wc_get_order( $post_or_order->ID );
    if ( ! $order ) {
        return;
    }

    wp_nonce_field( 'mammart_admin_workflow_save', 'mammart_admin_workflow_nonce' );

    $is_deducted      = ( 'yes' === $order->get_meta( '_mammart_stock_deducted' ) );
    $is_restocked     = ( 'yes' === $order->get_meta( '_mammart_stock_restocked' ) );
    $manual_reconcile = ( 'yes' === $order->get_meta( '_mammart_manual_reconcile' ) );
    $refund_amount    = $order->get_meta( '_mammart_refund_amount' );
    $cancel_reason    = $order->get_meta( '_mammart_cancel_reason' );
    $cancelled_by     = $order->get_meta( '_mammart_cancelled_by' );
    ?>
    <div style="font-size:13px; line-height:1.5;">
        <p style="margin-bottom:6px;">
            <strong>Cờ tồn kho:</strong><br>
            • Đã trừ tồn (1 lần): <strong><?php echo $is_deducted ? '✅ Có' : '⏳ Chưa trừ'; ?></strong><br>
            • Đã hoàn tồn (1 lần): <strong><?php echo $is_restocked ? '✅ Đã xử lý' : '— Chưa'; ?></strong>
        </p>

        <?php if ( $manual_reconcile ) : ?>
            <div style="padding:8px; margin:8px 0; background:#fcf0f1; border-left:3px solid #d63638;">
                <strong>⚠️ CỜ ĐỐI SOÁT THỦ CÔNG:</strong><br>
                Tiền SePay về muộn sau khi đơn đã Cancelled (quá 24h) hoặc sai số tiền. Không tự mở lại đơn — Admin kiểm tra hoàn tiền thủ công.
            </div>
        <?php endif; ?>

        <?php if ( $refund_amount ) : ?>
            <p style="margin:6px 0; color:#b32d2e;">
                <strong>Mức hoàn tiền (100% gồm ship):</strong> <?php echo wp_kses_post( wc_price( $refund_amount ) ); ?>
            </p>
        <?php endif; ?>

        <hr>

        <?php if ( $cancel_reason ) : ?>
            <div style="padding:8px; background:#fff8e5; border-left:3px solid #ffb900;">
                <strong>Người hủy:</strong> <?php echo esc_html( 'admin' === $cancelled_by ? 'Admin' : 'Khách hàng' ); ?><br>
                <strong>Lý do hủy:</strong> <?php echo esc_html( $cancel_reason ); ?>
            </div>
        <?php elseif ( ! in_array( $order->get_status(), array( 'cancelled', 'refund-pending', 'completed' ), true ) ) : ?>
            <p style="margin-bottom:6px;"><strong>Admin hủy đơn & ghi lý do:</strong></p>
            <p style="margin-bottom:6px;">
                <label>
                    <input type="checkbox" name="mammart_admin_do_cancel" value="yes">
                    Xác nhận hủy đơn này khi bấm Cập nhật
                </label>
            </p>
            <p style="margin-bottom:4px;">
                <input type="text" name="mammart_admin_cancel_reason" placeholder="Nhập lý do hủy đơn..." style="width:100%;">
            </p>
        <?php endif; ?>
    </div>
    <?php
}

add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
    static $is_running = false;
    if ( $is_running ) {
        return;
    }

    if ( ! isset( $_POST['mammart_admin_workflow_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mammart_admin_workflow_nonce'] ) ), 'mammart_admin_workflow_save' ) ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    if ( isset( $_POST['mammart_admin_do_cancel'] ) && 'yes' === $_POST['mammart_admin_do_cancel'] ) {
        $is_running   = true;
        $admin_reason = sanitize_text_field( wp_unslash( $_POST['mammart_admin_cancel_reason'] ?? '' ) );
        if ( empty( $admin_reason ) ) {
            $admin_reason = 'Admin hủy đơn trên trang quản trị';
        }

        $order->update_meta_data( '_mammart_cancel_reason', $admin_reason );
        $order->update_meta_data( '_mammart_cancelled_by', 'admin' );

        if ( 'Paid' === $order->get_meta( '_mammart_payment_status' ) ) {
            $refund_total = $order->get_total();
            $order->update_meta_data( '_mammart_refund_amount', $refund_total );
            $order->save();
            $order->update_status(
                'refund-pending',
                sprintf( 'Admin hủy đơn (hoàn 100%% tổng thanh toán: %s). Lý do: %s', wp_strip_all_tags( wc_price( $refund_total ) ), $admin_reason )
            );
        } else {
            $order->save();
            $order->update_status( 'cancelled', 'Admin hủy đơn. Lý do: ' . $admin_reason );
        }
        $is_running = false;
    }
}, 50 );

/**
 * =========================================================================
 * 4. KHÁCH TỰ HỦY Ở Pending / On-Hold / Confirmed; KHÓA KHI SANG Processing
 * =========================================================================
 */
function mammart_can_customer_cancel_order( $order ) {
    if ( ! $order ) {
        return false;
    }
    // Confirmed = Đã xác nhận / Chờ đóng gói -> khách VẪN tự hủy được
    // Processing = Đang đóng gói -> KHÓA nút hủy
    return in_array( $order->get_status(), array( 'pending', 'on-hold', 'confirmed' ), true );
}

add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
    if ( ! is_user_logged_in() || (int) $order->get_user_id() !== get_current_user_id() ) {
        return;
    }

    $saved_reason = $order->get_meta( '_mammart_cancel_reason' );
    if ( $saved_reason ) {
        echo '<div style="padding:14px; background:#fff8e5; border-left:4px solid #ffb900; margin-top:16px;">';
        echo '<strong>Lý do hủy đơn:</strong> ' . esc_html( $saved_reason );
        if ( $order->has_status( 'refund-pending' ) ) {
            echo '<br><em>Trạng thái hoàn tiền: Mầm Mart đang xử lý hoàn 100% tổng thanh toán (' . wp_kses_post( wc_price( $order->get_total() ) ) . ', bao gồm cả phí vận chuyển).</em>';
        }
        echo '</div>';
        return;
    }

    if ( ! mammart_can_customer_cancel_order( $order ) ) {
        if ( in_array( $order->get_status(), array( 'processing', 'shipping' ), true ) ) {
            echo '<p style="margin-top:16px; padding:12px; background:#f6f7f7; border-left:4px solid #72aee6; color:#1d2327;">';
            echo '<em>Đơn hàng đã chuyển sang giai đoạn Đang đóng gói / Đang giao hàng nên không thể tự hủy trực tuyến. Vui lòng liên hệ Mầm Mart nếu cần hỗ trợ.</em>';
            echo '</p>';
        }
        return;
    }
    ?>
    <div style="margin-top:24px; padding:18px; border:1px solid #dcdcde; border-radius:8px; background:#f9f9f9;">
        <h3 style="margin-top:0;">Hủy đơn hàng</h3>
        <p>Đơn hàng của bạn <strong>chưa bắt đầu đóng gói</strong> nên bạn có thể yêu cầu hủy ngay bên dưới:</p>
        <form method="post">
            <?php wp_nonce_field( 'mammart_customer_cancel_' . $order->get_id(), 'mammart_cancel_nonce' ); ?>
            <input type="hidden" name="mammart_cancel_order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">

            <p style="margin-bottom:12px;">
                <label for="mammart_cancel_reason"><strong>Lý do hủy đơn (bắt buộc):</strong></label><br>
                <select name="mammart_cancel_reason" id="mammart_cancel_reason" required style="width:100%; max-width:440px; padding:8px; margin-top:6px;">
                    <option value="">-- Chọn lý do hủy đơn --</option>
                    <option value="Đổi ý / Không còn nhu cầu mua">Đổi ý / Không còn nhu cầu mua</option>
                    <option value="Đặt nhầm sản phẩm hoặc sai số lượng">Đặt nhầm sản phẩm hoặc sai số lượng</option>
                    <option value="Muốn thay đổi địa chỉ / số điện thoại nhận hàng">Muốn thay đổi địa chỉ / số điện thoại nhận hàng</option>
                    <option value="Muốn đổi phương thức thanh toán khác">Muốn đổi phương thức thanh toán khác</option>
                    <option value="Lý do khác">Lý do khác</option>
                </select>
            </p>

            <button type="submit" name="mammart_submit_cancel" class="button" style="background:#d63638; color:#fff; border:none; padding:10px 18px; border-radius:4px; cursor:pointer;" onclick="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?');">
                Xác nhận hủy đơn hàng
            </button>
        </form>
    </div>
    <?php
} );

add_action( 'template_redirect', function () {
    if ( ! isset( $_POST['mammart_submit_cancel'], $_POST['mammart_cancel_order_id'] ) ) {
        return;
    }

    $order_id = absint( $_POST['mammart_cancel_order_id'] );
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mammart_cancel_nonce'] ?? '' ) ), 'mammart_customer_cancel_' . $order_id ) ) {
        wc_add_notice( 'Phiên làm việc hết hạn, vui lòng thử lại.', 'error' );
        return;
    }

    $order  = wc_get_order( $order_id );
    $reason = sanitize_text_field( wp_unslash( $_POST['mammart_cancel_reason'] ?? '' ) );

    if ( ! $order || (int) $order->get_user_id() !== get_current_user_id() ) {
        wc_add_notice( 'Không tìm thấy đơn hàng hợp lệ.', 'error' );
        return;
    }

    if ( empty( $reason ) ) {
        wc_add_notice( 'Vui lòng chọn lý do hủy đơn hàng.', 'error' );
        return;
    }

    if ( ! mammart_can_customer_cancel_order( $order ) ) {
        wc_add_notice( 'Cửa hàng vừa chuyển đơn sang Đang đóng gói nên không thể tự hủy được nữa.', 'error' );
        return;
    }

    $order->update_meta_data( '_mammart_cancel_reason', $reason );
    $order->update_meta_data( '_mammart_cancelled_by', 'customer' );

    if ( 'Paid' === $order->get_meta( '_mammart_payment_status' ) ) {
        $refund_total = $order->get_total();
        $order->update_meta_data( '_mammart_refund_amount', $refund_total );
        $order->save();

        $order->update_status(
            'refund-pending',
            sprintf(
                'Khách tự hủy trước khi đóng gói (hoàn 100%% tổng thanh toán: %s, gồm cả phí ship). Lý do: %s',
                wp_strip_all_tags( wc_price( $refund_total ) ),
                $reason
            )
        );
        wc_add_notice( 'Đã hủy đơn hàng. Yêu cầu hoàn tiền 100% (bao gồm phí vận chuyển) đã được chuyển cho cửa hàng.', 'success' );
    } else {
        $order->save();
        $order->update_status(
            'cancelled',
            'Khách hàng tự hủy trước khi đóng gói. Lý do: ' . $reason
        );
        wc_add_notice( 'Đã hủy đơn hàng thành công.', 'success' );
    }

    wp_safe_redirect( $order->get_view_order_url() );
    exit;
} );