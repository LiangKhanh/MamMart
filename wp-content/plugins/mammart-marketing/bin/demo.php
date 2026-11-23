<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/Demo.php';

try {
    $result = \MamMart\Marketing\Demo::run(sys_get_temp_dir() . '/mammart-ph-demo-' . bin2hex(random_bytes(8)));
    $output = dirname(__DIR__) . '/reports';
    if (!is_dir($output)) { mkdir($output, 0700, true); }
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (file_put_contents($output . '/demo-log.json', $json . PHP_EOL) === false) {
        throw new RuntimeException('Cannot write demo log.');
    }
    echo "SIMULATION ONLY — demo complete; reports/demo-log.json\n";
    echo json_encode($result['results']['checkout'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
