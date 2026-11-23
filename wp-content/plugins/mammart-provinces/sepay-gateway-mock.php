<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Đăng ký cổng thanh toán SePay giả lập
add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
    $gateways[] = 'WC_Gateway_MamMart_SePay_Mock';
    return $gateways;
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Gateway_MamMart_SePay_Mock extends WC_Payment_Gateway {
        public function __construct() {
            $this->id                 = 'mammart_sepay';
            $this->method_title       = 'SePay Chuyển khoản QR (Mầm Mart)';
            $this->method_description = 'Cổng SePay giả lập trên localhost (tiền tố MM, tạo đơn On-Hold).';
            $this->has_fields         = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title', 'Chuyển khoản ngân hàng qua SePay (QR Code)' );
            $this->description = $this->get_option( 'description', 'Chuyển khoản đúng nội dung MM + Mã đơn hàng để xác nhận tự động.' );
            $this->enabled     = $this->get_option( 'enabled', 'yes' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Kích hoạt',
                    'type'    => 'checkbox',
                    'label'   => 'Bật cổng thanh toán SePay (Mầm Mart)',
                    'default' => 'yes',
                ),
                'title' => array(
                    'title'   => 'Tiêu đề hiển thị',
                    'type'    => 'text',
                    'default' => 'Chuyển khoản ngân hàng qua SePay (QR Code)',
                ),
                'description' => array(
                    'title'   => 'Mô tả',
                    'type'    => 'textarea',
                    'default' => 'Chuyển khoản đúng nội dung MM + Mã đơn hàng để xác nhận tự động.',
                ),
            );
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $order->update_status( 'on-hold', 'Chờ thanh toán chuyển khoản SePay. Nội dung CK: MM' . $order_id );
            $order->update_meta_data( '_mammart_payment_status', 'Unpaid' );
            $order->update_meta_data( '_mammart_transfer_code', 'MM' . $order_id );
            $order->save();

            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }
    }
} );

// 2. Hiển thị thông tin chuyển khoản MM<ID> trên trang Cảm ơn / Chi tiết đơn
add_action( 'woocommerce_thankyou_mammart_sepay', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    echo '<div style="padding:16px; border:2px solid #2271b1; border-radius:8px; margin:16px 0; background:#f0f6fc;">';
    echo '<h3>Thông tin chuyển khoản SePay</h3>';
    echo '<p><strong>Số tiền cần chuyển:</strong> ' . wc_price( $order->get_total() ) . '</p>';
    echo '<p><strong>Nội dung chuyển khoản bắt buộc:</strong> <code style="font-size:16px; color:#d63638;">MM' . esc_html( $order_id ) . '</code></p>';
    echo '<p><em>(Trên localhost: vào wp-admin → WooCommerce → Mô phỏng SePay để bắn webhook test cho mã đơn <strong>' . esc_html( $order_id ) . '</strong>)</em></p>';
    echo '</div>';
} );

// 3. Đăng ký trực tiếp với PaymentMethodRegistry của Block Checkout
add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    $registry->register( new class extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
        protected $name = 'mammart_sepay';

        public function initialize() {
            $this->settings = get_option( 'woocommerce_mammart_sepay_settings', array() );
        }

        public function is_active() {
            return true;
        }

        public function get_payment_method_script_handles() {
            wp_register_script(
                'mammart-sepay-blocks',
                plugins_url( 'sepay-block.js', __FILE__ ),
                array( 'wc-blocks-registry', 'wc-settings', 'wp-element' ),
                '1.0.4',
                true
            );
            return array( 'mammart-sepay-blocks' );
        }

        public function get_payment_method_data() {
            return array(
                'title'       => 'Chuyển khoản ngân hàng qua SePay (QR Code)',
                'description' => 'Chuyển khoản đúng nội dung MM + Mã đơn hàng để xác nhận tự động.',
                'supports'    => array( 'products' ),
            );
        }
    } );
} );