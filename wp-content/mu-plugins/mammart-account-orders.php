<?php
/**
 * Plugin Name: Mầm Mart – Đơn hàng của tôi
 * Description: Hiển thị thông tin đơn hàng trên hai màn hình My Account của WooCommerce.
 * Text Domain: mammart-account-orders
 */

defined( 'ABSPATH' ) || exit;

function mammart_account_order_payment_status( WC_Order $order ): string {
	return $order->get_date_paid()
		? __( 'Đã thanh toán', 'mammart-account-orders' )
		: __( 'Chưa thanh toán', 'mammart-account-orders' );
}

function mammart_account_order_fulfillment_status(): string {
	return __( 'Chưa có dữ liệu xử lý', 'mammart-account-orders' );
}

add_filter( 'woocommerce_account_menu_items', static function ( $items ) {
	if ( isset( $items['orders'] ) ) {
		$items['orders'] = __( 'Đơn hàng của tôi', 'mammart-account-orders' );
	}
	return $items;
} );

add_action( 'woocommerce_before_account_orders', static function () {
	echo '<h2>' . esc_html__( 'Đơn hàng của tôi', 'mammart-account-orders' ) . '</h2>';
} );

add_filter( 'woocommerce_account_orders_columns', static function ( $columns ) {
	unset( $columns['order-status'] );
	return array(
		'order-number'               => __( 'Mã đơn', 'mammart-account-orders' ),
		'order-date'                 => __( 'Ngày đặt', 'mammart-account-orders' ),
		'order-total'                => __( 'Tổng tiền', 'mammart-account-orders' ),
		'mammart-payment-status'     => __( 'Trạng thái thanh toán', 'mammart-account-orders' ),
		'mammart-fulfillment-status' => __( 'Trạng thái xử lý', 'mammart-account-orders' ),
		'order-actions'              => __( 'Thao tác', 'mammart-account-orders' ),
	) + $columns;
} );

add_action( 'woocommerce_my_account_my_orders_column_order-total', static function ( $order ) {
	echo wp_kses_post( $order->get_formatted_order_total() );
} );

add_action( 'woocommerce_my_account_my_orders_column_mammart-payment-status', static function ( $order ) {
	echo esc_html( mammart_account_order_payment_status( $order ) );
} );

add_action( 'woocommerce_my_account_my_orders_column_mammart-fulfillment-status', static function () {
	echo esc_html( mammart_account_order_fulfillment_status() );
} );

add_filter( 'woocommerce_my_account_my_orders_actions', static function ( $actions, $order ) {
	if ( isset( $actions['view'] ) ) {
		$actions['view']['name'] = __( 'Xem chi tiết', 'mammart-account-orders' );
		$actions['view']['aria-label'] = sprintf(
			/* translators: %s: order number. */
			__( 'Xem chi tiết đơn #%s', 'mammart-account-orders' ),
			$order->get_order_number()
		);
	}
	return $actions;
}, 10, 2 );

add_action( 'woocommerce_before_template_part', static function ( $template_name ) {
	// WooCommerce loads this template after its own View Order capability check.
	if ( 'myaccount/view-order.php' === $template_name ) {
		echo '<h2>' . esc_html__( 'Chi tiết đơn hàng', 'mammart-account-orders' ) . '</h2>';
	}
} );

add_filter( 'woocommerce_order_details_status', static function ( $status_text, $order ) {
	if ( ! is_account_page() || ! is_wc_endpoint_url( 'view-order' ) ) {
		return $status_text;
	}

	$fields = array(
		__( 'Mã đơn', 'mammart-account-orders' )                 => '#' . $order->get_order_number(),
		__( 'Ngày đặt', 'mammart-account-orders' )               => wc_format_datetime( $order->get_date_created() ),
		__( 'Trạng thái thanh toán', 'mammart-account-orders' ) => mammart_account_order_payment_status( $order ),
		__( 'Trạng thái xử lý', 'mammart-account-orders' )      => mammart_account_order_fulfillment_status(),
	);

	$lines = array();
	foreach ( $fields as $label => $value ) {
		$lines[] = '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value );
	}
	return implode( '<br>', $lines );
}, 10, 2 );

add_filter( 'woocommerce_get_order_item_totals', static function ( $totals, $order, $tax_display ) {
	// Keep emails, order tracking and checkout totals outside this presentation.
	if ( ! is_account_page() || ! is_wc_endpoint_url( 'view-order' ) || ! doing_action( 'woocommerce_view_order' ) ) {
		return $totals;
	}

	$labels = array(
		'cart_subtotal'  => __( 'Tiền hàng', 'mammart-account-orders' ),
		'discount'       => __( 'Giảm giá', 'mammart-account-orders' ),
		'shipping'       => __( 'Phí giao', 'mammart-account-orders' ),
		'order_total'    => __( 'Tổng tiền', 'mammart-account-orders' ),
		'payment_method' => __( 'Phương thức thanh toán', 'mammart-account-orders' ),
	);
	$totals['discount'] ??= array( 'value' => $order->get_discount_to_display( $tax_display ) );
	$totals['shipping'] ??= array( 'value' => $order->get_shipping_to_display( $tax_display ) );
	$totals['payment_method'] ??= array(
		'value' => esc_html( $order->get_payment_method_title() ?: __( 'Chưa ghi nhận', 'mammart-account-orders' ) ),
	);
	foreach ( $labels as $key => $label ) {
		if ( isset( $totals[ $key ] ) ) {
			$totals[ $key ]['label'] = $label;
		}
	}

	$first = array( 'cart_subtotal', 'discount', 'shipping' );
	$summary = array();
	foreach ( $first as $key ) {
		if ( isset( $totals[ $key ] ) ) {
			$summary[ $key ] = $totals[ $key ];
		}
	}
	return $summary + $totals;
}, 10, 3 );
