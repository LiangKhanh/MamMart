<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Thêm menu "Mô phỏng SePay" vào dưới mục WooCommerce trong wp-admin
add_action( 'admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Mô phỏng Webhook SePay (T2-NL)',
        'Mô phỏng SePay',
        'manage_woocommerce',
        'mammart-sepay-simulator',
        'mammart_render_sepay_simulator_page'
    );
} );

// 2. Xử lý logic Webhook SePay: Đủ tiền, Thiếu tiền, Trùng mã GD, Tiền về trễ sau khi đơn đã hủy
function mammart_handle_sepay_webhook_logic( $data, $simulation_action = '' ) {
    $source           = ! empty( $data['source'] ) ? sanitize_text_field( $data['source'] ) : 'MÔ PHỎNG';
    $transfer_content = isset( $data['content'] ) ? strtoupper( sanitize_text_field( $data['content'] ) ) : '';
    $amount_in        = isset( $data['transferAmount'] ) ? (float) $data['transferAmount'] : 0;
    $txn_ref          = isset( $data['referenceCode'] ) ? sanitize_text_field( $data['referenceCode'] ) : '';

    if ( empty( $txn_ref ) || empty( $transfer_content ) ) {
        return mammart_write_webhook_log( $source, 'ERROR_INVALID_PAYLOAD', $transfer_content, $amount_in, 0, $txn_ref, 'Thiếu nội dung chuyển khoản hoặc mã tham chiếu giao dịch.' );
    }

    // Tách mã đơn hàng từ tiền tố MM (ví dụ MM63 -> 63)
    if ( ! preg_match( '/MM(\d+)/i', $transfer_content, $matches ) ) {
        return mammart_write_webhook_log( $source, 'UNMATCHED_PREFIX', $transfer_content, $amount_in, 0, $txn_ref, 'Không tìm thấy mã đơn theo tiền tố MM trong nội dung CK.' );
    }

    $order_id = absint( $matches[1] );
    $lock = mammart_acquire_workflow_lock( 'order:' . $order_id );
    if ( ! $lock ) {
        return mammart_write_webhook_log( $source, 'RETRY_ORDER_BUSY', $transfer_content, $amount_in, 0, $txn_ref, 'Đơn đang được cập nhật. Chạy lại mô phỏng với cùng mã giao dịch.' );
    }

    try {
        return mammart_apply_sepay_transfer( $order_id, $source, $transfer_content, $amount_in, $txn_ref, $simulation_action );
    } finally {
        mammart_release_workflow_lock( $lock );
    }
}

function mammart_apply_sepay_transfer( $order_id, $source, $transfer_content, $amount_in, $txn_ref, $simulation_action ) {
    // Chống xử lý lặp giao dịch (Idempotency theo referenceCode)
    $processed_refs = get_option( 'mammart_processed_sepay_refs', array() );
    if ( in_array( $txn_ref, $processed_refs, true ) ) {
        return mammart_write_webhook_log(
            $source,
            'IGNORED_DUPLICATE',
            $transfer_content,
            $amount_in,
            0,
            $txn_ref,
            'Giao dịch trùng mã tham chiếu (' . $txn_ref . ') -> Bỏ qua, không cộng tiền/đổi trạng thái lần 2.'
        );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        return mammart_write_webhook_log( $source, 'ORDER_NOT_FOUND', $transfer_content, $amount_in, 0, $txn_ref, 'Không tồn tại đơn hàng #' . $order_id );
    }

    // Lưu mã giao dịch đã tiếp nhận để chặn trùng lặp
    $processed_refs[] = $txn_ref;
    update_option( 'mammart_processed_sepay_refs', $processed_refs, false );

    $order_total = (float) $order->get_total();
    if ( '' !== $simulation_action ) {
        $amount_in = $order_total;
        if ( 'underpaid' === $simulation_action ) {
            $amount_in = max( 1000, $order_total - 10000 );
        } elseif ( 'late_after_cancel' === $simulation_action ) {
            $order->update_status( 'cancelled', 'Giả lập đơn hết hạn 24h tự hủy trước khi tiền về.' );
        }
    }

    // Kịch bản 4: Tiền về trễ khi đơn đã bị hủy (Cancelled) -> Không mở lại đơn, gắn cờ đối soát thủ công
    if ( $order->has_status( 'cancelled' ) ) {
        $order->update_meta_data( '_mammart_payment_status', 'Refund_Pending' );
        $order->update_meta_data( '_mammart_payment_anomaly', 'late_after_cancel' );
        $order->update_meta_data( '_mammart_manual_reconcile', 'yes' );
        $order->update_meta_data( '_mammart_refund_amount', $order_total );
        $order->update_meta_data( '_mammart_sepay_ref', $txn_ref );
        $order->add_order_note( sprintf(
            '[%s] SePay nhận %s đ (Mã GD: %s) NHƯNG đơn đã Hủy trước đó. Không tự mở lại đơn -> Gắn cờ chờ Admin đối soát / hoàn 100%% thủ công.',
            $source,
            number_format( $amount_in, 0, ',', '.' ),
            $txn_ref
        ) );
        $order->save();

        return mammart_write_webhook_log(
            $source,
            'LATE_AFTER_CANCEL -> REFUND_PENDING',
            $transfer_content,
            $amount_in,
            $order_total,
            $txn_ref,
            'Đơn #' . $order_id . ' đã Cancelled -> Giữ nguyên Cancelled, gắn cờ chờ Admin đối soát & hoàn tiền thủ công 100%.'
        );
    }

    // Kịch bản 2: Chuyển thiếu tiền (amount_in < order_total)
    if ( $amount_in < $order_total ) {
        $shortfall = $order_total - $amount_in;
        $order->update_meta_data( '_mammart_payment_status', 'Unpaid_Partial' );
        $order->update_meta_data( '_mammart_payment_anomaly', 'underpaid' );
        $order->update_meta_data( '_mammart_manual_reconcile', 'yes' );
        $order->update_meta_data( '_mammart_sepay_ref', $txn_ref );
        $order->add_order_note( sprintf(
            '[%s] SePay nhận THIẾU TIỀN: nhận %s đ / tổng đơn %s đ (thiếu %s đ, Mã GD: %s). Giữ nguyên trạng thái chờ, gắn cờ đối soát.',
            $source,
            number_format( $amount_in, 0, ',', '.' ),
            number_format( $order_total, 0, ',', '.' ),
            number_format( $shortfall, 0, ',', '.' ),
            $txn_ref
        ) );
        $order->save();

        return mammart_write_webhook_log(
            $source,
            'ANOMALY_UNDERPAID',
            $transfer_content,
            $amount_in,
            $order_total,
            $txn_ref,
            'Đơn #' . $order_id . ' chuyển thiếu ' . number_format( $shortfall, 0, ',', '.' ) . ' đ -> Không xác nhận thanh toán, gắn cờ đối soát.'
        );
    }

    // Kịch bản 1: Thanh toán đủ tiền (amount_in >= order_total)
    $order->update_meta_data( '_mammart_payment_status', 'Paid' );
    $order->update_meta_data( '_mammart_sepay_ref', $txn_ref );
    $order->update_meta_data( '_mammart_paid_at', current_time( 'mysql' ) );
    $order->add_order_note( sprintf(
        '[%s] SePay xác nhận ĐÃ THANH TOÁN ĐỦ %s đ (Nội dung: %s | Mã GD: %s).',
        $source,
        number_format( $amount_in, 0, ',', '.' ),
        $transfer_content,
        $txn_ref
    ) );
    $order->update_status( 'processing', 'Cập nhật trạng thái sau khi nhận thanh toán SePay.' );

    return mammart_write_webhook_log(
        $source,
        'PAID_CONFIRMED',
        $transfer_content,
        $amount_in,
        $order_total,
        $txn_ref,
        'Đơn #' . $order_id . ' thanh toán đủ -> Đánh dấu Paid và chuyển kiểm tra tồn kho / Confirmed.'
    );
}

// 3. Ghi log phân biệt rõ [THẬT] / [MÔ PHỎNG] ra file wp-content/uploads/mammart-sepay-webhook.log
function mammart_write_webhook_log( $source, $status, $content, $amount, $expected, $txn_ref, $message ) {
    $upload_dir = wp_upload_dir();
    $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'mammart-sepay-webhook.log';
    $line       = sprintf(
        "[%s] [%s] [%s] ND: %s | Nhận: %s đ / Cần: %s đ | Ref: %s | KQ: %s\n",
        current_time( 'Y-m-d H:i:s' ),
        $source,
        $status,
        $content,
        number_format( $amount, 0, ',', '.' ),
        number_format( $expected, 0, ',', '.' ),
        $txn_ref,
        $message
    );
    file_put_contents( $log_file, $line, FILE_APPEND );
    return $line;
}

// 4. Giao diện chạy thử 4 kịch bản trong wp-admin
function mammart_render_sepay_simulator_page() {
    $last_result = '';

    if ( isset( $_POST['mammart_sim_action'] ) && check_admin_referer( 'mammart_sepay_sim_nonce' ) ) {
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $action   = sanitize_text_field( $_POST['mammart_sim_action'] );
        $txn_ref  = sanitize_text_field( $_POST['txn_ref'] ?? '' );

        if ( $action === 'clear_log' ) {
            $upload_dir = wp_upload_dir();
            $log_file   = trailingslashit( $upload_dir['basedir'] ) . 'mammart-sepay-webhook.log';
            if ( file_exists( $log_file ) ) {
                unlink( $log_file );
            }
            delete_option( 'mammart_processed_sepay_refs' );
            $last_result = 'Đã xóa log cũ và đặt lại danh sách mã giao dịch.';
        } else {
            $last_result = mammart_handle_sepay_webhook_logic( array(
                'source'         => 'MÔ PHỎNG',
                'content'        => 'MM' . $order_id,
                'referenceCode'  => $txn_ref,
            ), $action );
        }
    }

    $upload_dir  = wp_upload_dir();
    $log_file    = trailingslashit( $upload_dir['basedir'] ) . 'mammart-sepay-webhook.log';
    $log_content = file_exists( $log_file ) ? file_get_contents( $log_file ) : "Chưa có bản ghi webhook nào.\n";
    ?>
    <div class="wrap">
        <h1>Mô phỏng Webhook SePay & Log đối soát (Bước 3 - T2-NL)</h1>
        <p>Dùng công cụ này để kiểm thử 4 kịch bản webhook SePay trên localhost (đủ tiền, thiếu tiền, trùng mã GD, tiền về trễ sau khi đơn đã hủy).</p>

        <?php if ( $last_result ) : ?>
            <div class="notice notice-info"><p><strong>Kết quả vừa chạy:</strong> <code><?php echo esc_html( $last_result ); ?></code></p></div>
        <?php endif; ?>

        <form method="post" style="background:#fff; padding:16px; border:1px solid #ccd0d4; max-width:720px; margin-bottom:20px;">
            <?php wp_nonce_field( 'mammart_sepay_sim_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="order_id">Mã đơn hàng WooCommerce (ID)</label></th>
                    <td><input type="number" name="order_id" id="order_id" value="<?php echo esc_attr( $_POST['order_id'] ?? '' ); ?>" placeholder="Ví dụ: 63" required></td>
                </tr>
                <tr>
                    <th><label for="txn_ref">Mã tham chiếu GD (referenceCode)</label></th>
                    <td>
                        <input type="text" name="txn_ref" id="txn_ref" value="<?php echo esc_attr( $_POST['txn_ref'] ?? 'FT263180001' ); ?>" required>
                        <p class="description">Để test trùng giao dịch: giữ nguyên mã giao dịch đã chạy thành công trước đó và bấm nút "Test 3".</p>
                    </td>
                </tr>
            </table>
            <p style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="submit" name="mammart_sim_action" value="exact" class="button button-primary">Test 1: Chuyển ĐỦ tiền (MM<ID>)</button>
                <button type="submit" name="mammart_sim_action" value="underpaid" class="button">Test 2: Chuyển THIẾU tiền (-10.000đ)</button>
                <button type="submit" name="mammart_sim_action" value="duplicate" class="button">Test 3: Gửi TRÙNG mã GD</button>
                <button type="submit" name="mammart_sim_action" value="late_after_cancel" class="button">Test 4: Tiền về TRỄ (đơn đã Hủy)</button>
                <button type="submit" name="mammart_sim_action" value="clear_log" class="button button-link-delete" onclick="return confirm('Xóa toàn bộ log và danh sách mã GD đã lưu?');">Xóa log test</button>
            </p>
        </form>

        <h2>Nhật ký Webhook (Tự động lưu tại <code>wp-content/uploads/mammart-sepay-webhook.log</code>)</h2>
        <textarea readonly rows="14" style="width:100%; max-width:960px; font-family:monospace; background:#1e1e1e; color:#d4d4d4; padding:12px;"><?php echo esc_textarea( $log_content ); ?></textarea>
    </div>
    <?php
}
