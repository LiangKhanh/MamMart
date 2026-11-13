<?php
/**
 * Plugin Name: Mầm Mart Marketing Sandbox
 * Description: T2-PH independent trial. SIMULATION ONLY; no real coupons, orders, or mail.
 * Version: 0.1.0
 * Requires PHP: 8.0
 * Text Domain: mammart-marketing
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Demo.php';
require_once __DIR__ . '/src/SandboxPage.php';

// No activation migration, scheduler, checkout filter, WC_Coupon creation or wp_mail hook.
add_action('admin_menu', [\MamMart\Marketing\SandboxPage::class, 'register']);
