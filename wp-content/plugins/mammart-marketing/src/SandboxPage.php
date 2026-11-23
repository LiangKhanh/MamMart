<?php
declare(strict_types=1);

namespace MamMart\Marketing;

final class SandboxPage
{
    private static bool $prepared = false;
    private static ?array $result = null;
    private static ?string $error = null;

    public static function register(): void
    {
        $hook = add_management_page('Mầm Mart Marketing Sandbox', 'Mầm Mart Sandbox', 'manage_options',
            'mammart-marketing', [self::class, 'render']);
        if ($hook) {
            // WordPress fires load-$hook before admin-header.php sends page output.
            add_action('load-' . $hook, [self::class, 'prepare']);
        }
    }

    public static function prepare(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Bạn không có quyền chạy sandbox.', '', ['response' => 403]);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            // Reject before output so WordPress can send the correct HTTP 403 status.
            check_admin_referer('mammart_marketing_run_demo');
            try {
                $scope = substr(hash('sha256', ABSPATH . ':' . get_current_user_id()), 0, 16);
                $storage = sys_get_temp_dir() . '/mammart-marketing-' . $scope . '/' . bin2hex(random_bytes(8));
                self::$result = Demo::run($storage);
            } catch (\Throwable $error) {
                self::$error = $error->getMessage();
            }
        }
        self::$prepared = true;
    }

    public static function render(): void
    {
        if (!self::$prepared || !current_user_can('manage_options')) {
            wp_die('Bạn không có quyền chạy sandbox.', '', ['response' => 403]);
            return;
        }
        echo '<div class="wrap"><h1>Mầm Mart — SIMULATION ONLY</h1>';
        echo '<p>Dữ liệu giả. Không tạo coupon, đơn hàng, thanh toán hoặc gửi email thật. '
            . 'Phép tính demo bỏ qua ngưỡng/trần chưa chốt; không phải thể lệ chính thức.</p>';
        if (self::$result !== null) {
            echo '<h2>Kết quả bộ demo cố định</h2><pre>'
                . esc_html((string) json_encode(self::$result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        }
        if (self::$error !== null) {
            echo '<p>' . esc_html('Sandbox không chạy được: ' . self::$error) . '</p>';
        }
        echo '<form method="post">';
        wp_nonce_field('mammart_marketing_run_demo');
        submit_button('Chạy bộ dữ liệu giả mới');
        echo '</form></div>';
    }
}
