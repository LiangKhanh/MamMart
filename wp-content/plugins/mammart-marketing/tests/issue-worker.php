<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once dirname(__DIR__) . '/src/bootstrap.php';
$seed = json_decode((string) file_get_contents(dirname(__DIR__) . '/fixtures/scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
$config = require dirname(__DIR__) . '/config/sandbox.php';
$service = new \MamMart\Marketing\MarketingService(
    new \MamMart\Marketing\FakeRepository($argv[1], $seed),
    new \MamMart\Marketing\FakeClock($config['as_of'], $config['timezone']),
    new \MamMart\Marketing\FakeMailer(), $config
);
// Both workers must be alive before either attempts issuance.
if (($argv[2] ?? '') === 'barrier') {
    file_put_contents($argv[1] . '/ready-' . getmypid(), 'ready');
    $deadline = microtime(true) + 5;
    while (count(glob($argv[1] . '/ready-*') ?: []) < 2) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, 'Concurrency barrier timed out.');
            exit(1);
        }
        usleep(10000);
    }
}
echo json_encode($service->issue('B-DEMO-01'), JSON_THROW_ON_ERROR);
