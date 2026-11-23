<?php
/** Tổng quan tài khoản (T2-AL). Danh sách đơn thuộc T2-LI. */
defined( 'ABSPATH' ) || exit;
$u = wp_get_current_user();
?>
<p>Xin chào <strong><?php echo esc_html( $u->display_name ); ?></strong> (không phải bạn? <a href="<?php echo esc_url( wc_logout_url() ); ?>">Đăng xuất</a>)</p>
<p>Từ khung tài khoản bạn có thể xem <a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">đơn hàng</a>, quản lý <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>">địa chỉ giao hàng</a> và <a href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>">thông tin tài khoản</a>.</p>
<?php do_action( 'woocommerce_account_dashboard' ); ?>
