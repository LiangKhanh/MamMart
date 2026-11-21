<?php
/**
 * T2-AL — Cấu hình nền sau khi clone: bật theme, đặt tên/slug trang, cấu hình giỏ.
 * Chạy: php Docs/T2-AL/setup_t2al.php   (từ thư mục gốc repo; WooCommerce đã bật, DB đã dựng)
 * Không lưu mật khẩu/khóa nào. An toàn chạy lại.
 */
require dirname( __DIR__, 2 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) { activate_plugin( 'woocommerce/woocommerce.php' ); }
switch_theme( 'mammart-ui' );

// Tiền tệ VND, giỏ, tài khoản
update_option( 'woocommerce_currency', 'VND' );
update_option( 'woocommerce_price_num_decimals', 0 );
update_option( 'woocommerce_price_thousand_sep', '.' );
update_option( 'woocommerce_price_decimal_sep', ',' );
update_option( 'woocommerce_currency_pos', 'right_space' );
update_option( 'woocommerce_default_country', 'VN' );
update_option( 'woocommerce_manage_stock', 'yes' );
update_option( 'woocommerce_enable_guest_checkout', 'no' );          // BR-AL-07: đăng nhập khi thanh toán
update_option( 'woocommerce_enable_checkout_login_reminder', 'yes' );
update_option( 'woocommerce_enable_myaccount_registration', 'yes' );
update_option( 'woocommerce_cart_redirect_after_add', 'no' );
update_option( 'woocommerce_notify_low_stock_amount', 5 );
update_option( 'woocommerce_stock_format', '' );

// Trang WooCommerce: tên + slug tiếng Việt
$pages = [ 'shop' => [ 'Cửa hàng', 'cua-hang' ], 'cart' => [ 'Giỏ hàng', 'gio-hang' ], 'checkout' => [ 'Thanh toán', 'thanh-toan' ], 'myaccount' => [ 'Tài khoản', 'tai-khoan' ] ];
foreach ( $pages as $key => [ $title, $slug ] ) {
	$id = wc_get_page_id( $key );
	if ( $id > 0 ) { wp_update_post( [ 'ID' => $id, 'post_title' => $title, 'post_name' => $slug ] ); }
}
update_option( 'show_on_front', 'posts' );   // front-page.html của theme tự hiển thị trang chủ
update_option( 'blogdescription', 'Mẹ & bé — Mầm Mart' );
update_option( 'blogname', 'Mầm Mart' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
flush_rewrite_rules();
echo "T2-AL setup xong. Theme: " . wp_get_theme()->get( 'Name' ) . "\n";
