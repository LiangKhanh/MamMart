<?php
/**
 * Plugin Name: Mầm Mart - Tỉnh/Thành Việt Nam
 * Description: Thêm danh sách 34 tỉnh/thành vào form checkout, bắt buộc chọn và tích hợp mô phỏng SePay.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Nạp danh sách 34 tỉnh/thành Việt Nam
add_filter( 'woocommerce_states', function ( $states ) {
    $states['VN'] = array(
        'HANOI'      => 'Hà Nội',
        'HCM'        => 'Hồ Chí Minh',
        'HAIPHONG'   => 'Hải Phòng',
        'DANANG'     => 'Đà Nẵng',
        'CANTHO'     => 'Cần Thơ',
        'HUE'        => 'Huế',
        'ANGIANG'    => 'An Giang',
        'BACNINH'    => 'Bắc Ninh',
        'CAMAU'      => 'Cà Mau',
        'CAOBANG'    => 'Cao Bằng',
        'DAKLAK'     => 'Đắk Lắk',
        'DIENBIEN'   => 'Điện Biên',
        'DONGNAI'    => 'Đồng Nai',
        'DONGTHAP'   => 'Đồng Tháp',
        'GIALAI'     => 'Gia Lai',
        'HATINH'     => 'Hà Tĩnh',
        'HUNGYEN'    => 'Hưng Yên',
        'KHANHHOA'   => 'Khánh Hòa',
        'LAICHAU'    => 'Lai Châu',
        'LANGSON'    => 'Lạng Sơn',
        'LAOCAI'     => 'Lào Cai',
        'LAMDONG'    => 'Lâm Đồng',
        'NGHEAN'     => 'Nghệ An',
        'NINHBINH'   => 'Ninh Bình',
        'PHUTHO'     => 'Phú Thọ',
        'QUANGNGAI'  => 'Quảng Ngãi',
        'QUANGNINH'  => 'Quảng Ninh',
        'QUANGTRI'   => 'Quảng Trị',
        'SONLA'      => 'Sơn La',
        'TAYNINH'    => 'Tây Ninh',
        'THAINGUYEN' => 'Thái Nguyên',
        'THANHHOA'   => 'Thanh Hóa',
        'TUYENQUANG' => 'Tuyên Quang',
        'VINHLONG'   => 'Vĩnh Long',
    );
    return $states;
} );

// 2. Bắt buộc chọn Tỉnh/Thành phố ở form Checkout
add_filter( 'woocommerce_get_country_locale', function ( $locale ) {
    $locale['VN']['state'] = array(
        'required' => true,
        'hidden'   => false,
        'label'    => 'Tỉnh / Thành phố',
    );
    return $locale;
} );

require_once plugin_dir_path( __FILE__ ) . 'sepay-gateway-mock.php';
require_once plugin_dir_path( __FILE__ ) . 'sepay-simulator.php';
require_once plugin_dir_path( __FILE__ ) . 'order-workflow.php';
require_once plugin_dir_path( __FILE__ ) . 'contract-events-and-samples.php';
require_once plugin_dir_path( __FILE__ ) . 'sepay-expiry.php';

register_activation_hook( __FILE__, 'mammart_schedule_sepay_expiry' );
register_deactivation_hook( __FILE__, 'mammart_unschedule_sepay_expiry' );
