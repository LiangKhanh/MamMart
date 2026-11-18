<?php
/**
 * Plugin Name: Mầm Mart Recurring Reminder Sandbox
 * Description: Sandbox độc lập cho module nhắc mua T2-LO của Mầm Mart.
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/FakeOrderEventAdapter.php';
require_once __DIR__ . '/src/SandboxScheduler.php';
