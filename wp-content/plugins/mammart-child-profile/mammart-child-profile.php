<?php
/**
 * Plugin Name: MamMart Child Profile
 * Description: T2-YL - Hồ sơ bé, tính tuổi, bộ lọc theo nhãn tuổi SKU.
 * Version: 0.1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'MM_CP_DIR', plugin_dir_path( __FILE__ ) );
define( 'MM_CP_TZ', 'Asia/Ho_Chi_Minh' ); // Đ14 (đề xuất GMT+7)

require_once MM_CP_DIR . 'includes/age.php';
require_once MM_CP_DIR . 'includes/db.php';
require_once MM_CP_DIR . 'includes/profile.php';
require_once MM_CP_DIR . 'includes/dob-request.php';
require_once MM_CP_DIR . 'includes/products.php';
require_once MM_CP_DIR . 'includes/audience.php';
register_activation_hook( __FILE__, function () { mm_cp_install(); mm_cp_add_endpoint(); flush_rewrite_rules(); } );