<?php
declare(strict_types=1);

// Isolated WordPress API stubs: regression checks only, NOT the live T30 result.
namespace MamMart\Marketing {
    final class Demo
    {
        public static int $runs = 0;
        public static function run(string $storage): array
        {
            self::$runs++;
            return ['label' => 'SIMULATION ONLY', 'escaped_probe' => '<script>unsafe</script>'];
        }
    }
}

namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
    define('ABSPATH', dirname(__DIR__) . '/');
    final class RequestStopped extends \RuntimeException {}
    $scenario = $argv[1] ?? '';
    if (!in_array($scenario, ['denied', 'missing', 'invalid', 'valid', 'get'], true)) {
        fwrite(STDERR, 'Choose denied, missing, invalid, valid or get.'); exit(1);
    }
    $hooks = [];
    $nonceCalls = 0;
    function current_user_can(string $capability): bool
    {
        global $scenario;
        return $capability === 'manage_options' && $scenario !== 'denied';
    }
    function add_management_page(...$args): string { return 'tools_page_mammart-marketing'; }
    function add_action(string $hook, callable $callback): void { global $hooks; $hooks[$hook] = $callback; }
    function wp_die($message, $title = '', $args = []): void { throw new RequestStopped('denied', $args['response'] ?? 500); }
    function check_admin_referer(string $action): int
    {
        global $nonceCalls;
        $nonceCalls++;
        if ($action !== 'mammart_marketing_run_demo' || ($_POST['_wpnonce'] ?? '') !== 'TEST-VALID') {
            throw new RequestStopped('invalid nonce', 403);
        }
        return 1;
    }
    function get_current_user_id(): int { return 1; }
    function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function wp_nonce_field(string $action): void { echo '<input name="_wpnonce">'; }
    function submit_button(string $text): void { echo '<button>Run</button>'; }
    function check(bool $condition, string $message): void
    {
        if (!$condition) { throw new \RuntimeException($message); }
    }
    require dirname(__DIR__) . '/src/SandboxPage.php';
    $_SERVER['REQUEST_METHOD'] = $scenario === 'get' ? 'GET' : 'POST';
    $_POST = [];
    if ($scenario === 'valid') { $_POST['_wpnonce'] = 'TEST-VALID'; }
    if ($scenario === 'invalid') { $_POST['_wpnonce'] = 'BAD'; }
    \MamMart\Marketing\SandboxPage::register();
    try {
        check(isset($hooks['load-tools_page_mammart-marketing']), 'Early page-load guard not registered.');
        $stopped = false;
        ob_start();
        try { $hooks['load-tools_page_mammart-marketing'](); }
        catch (RequestStopped $error) { $stopped = true; check($error->getCode() === 403, 'Rejection must use 403.'); }
        finally { $earlyOutput = ob_get_clean(); }
        check($earlyOutput === '', 'Guard must execute before any page output.');
        $mustStop = in_array($scenario, ['denied', 'missing', 'invalid'], true);
        check($stopped === $mustStop, 'Unexpected authorization result.');
        check(\MamMart\Marketing\Demo::$runs === ($scenario === 'valid' ? 1 : 0), 'Rejected/GET request must not run demo.');
        check($nonceCalls === (in_array($scenario, ['missing', 'invalid', 'valid'], true) ? 1 : 0), 'Unexpected nonce check order.');
        if (!$mustStop) {
            ob_start(); \MamMart\Marketing\SandboxPage::render(); $html = ob_get_clean();
            check(strpos($html, 'SIMULATION ONLY') !== false, 'Page did not render.');
            if ($scenario === 'valid') {
                check(strpos($html, '<script>unsafe</script>') === false, 'Unescaped demo output.');
                check(strpos($html, '&lt;script&gt;') !== false, 'Missing escaped demo output.');
            }
        }
        echo 'STUB REGRESSION PASS: ' . $scenario . ' (not live T30)' . PHP_EOL;
    } catch (\Throwable $error) {
        fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1);
    }
}
