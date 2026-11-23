<?php

defined( 'ABSPATH' ) || exit;

function mammart_schedule_sepay_expiry() {
    if ( ! wp_next_scheduled( 'mammart_expire_sepay_orders' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'mammart_expire_sepay_orders' );
    }
}
add_action( 'init', 'mammart_schedule_sepay_expiry' );

function mammart_unschedule_sepay_expiry() {
    wp_clear_scheduled_hook( 'mammart_expire_sepay_orders' );
}

// SePay dùng hạn riêng thay cho thời gian giữ tồn của WooCommerce.
add_filter( 'woocommerce_cancel_unpaid_order', function ( $cancel, $order ) {
    return 'mammart_sepay' === $order->get_payment_method() ? false : $cancel;
}, 10, 2 );

function mammart_expire_sepay_orders( $now = null ) {
    if ( ! function_exists( 'wc_get_orders' ) ) {
        return 0;
    }

    $cutoff = ( $now ?? time() ) - DAY_IN_SECONDS;
    $waiting_statuses = array( 'pending', 'on-hold' );
    $order_ids = wc_get_orders( array(
        'status'         => $waiting_statuses,
        'payment_method' => 'mammart_sepay',
        'date_created'   => '<=' . $cutoff,
        'return'         => 'ids',
        'limit'          => -1,
    ) );
    $cancelled = 0;

    foreach ( $order_ids as $order_id ) {
        $lock = mammart_acquire_workflow_lock( 'order:' . $order_id );
        if ( ! $lock ) {
            continue;
        }

        try {
            // Đọc lại đơn sau khi khóa cùng tài nguyên với xác nhận thanh toán và khách hủy.
            $order = wc_get_order( $order_id );
            if ( ! $order || 'mammart_sepay' !== $order->get_payment_method() || ! $order->has_status( $waiting_statuses ) ) {
                continue;
            }
            $order->read_meta_data( true );
            $created = $order->get_date_created();
            if ( ! $created || $created->getTimestamp() > $cutoff || $order->get_date_paid()
                || $order->get_meta( '_mammart_sample_code' )
                || ! in_array( $order->get_meta( '_mammart_payment_status' ), array( 'Unpaid', 'Unpaid_Partial' ), true ) ) {
                continue;
            }

            $reason = 'Quá hạn thanh toán SePay 24 giờ';
            $order->update_meta_data( '_mammart_cancel_reason', $reason );
            $order->update_meta_data( '_mammart_cancelled_by', 'system' );
            if ( $order->update_status( 'cancelled', $reason ) ) {
                ++$cancelled;
            }
        } finally {
            mammart_release_workflow_lock( $lock );
        }
    }

    return $cancelled;
}
add_action( 'mammart_expire_sepay_orders', 'mammart_expire_sepay_orders', 10, 0 );
