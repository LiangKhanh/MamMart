<?php

declare(strict_types=1);

use Mammart\Recurring\FakeOrderEventAdapter;
use Mammart\Recurring\RecurringPlanService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/src/FakeOrderEventAdapter.php';

$fixture = json_decode(file_get_contents($root . '/fixtures/plan-simulation.json'), true, 512, JSON_THROW_ON_ERROR);
$makeCases = require $root . '/tests/plan-cases.php';
$cases = $makeCases($fixture['events']);

$results = [];
$audit = [];
$counts = ['PASS' => 0, 'FAIL' => 0, 'DEFERRED' => 0];

foreach ($cases as $id => $case) {
    $events = $case['events'] ?? [];
    $expected = $case['expected'];
    $registration = array_replace($fixture['registration'], $case['registration'] ?? []);
    $actual = null;

    if (isset($case['deferred'])) {
        $result = 'DEFERRED';
        $input = ['operation' => 'resume', 'status' => 'Cancelled'];
    } else {
        $input = ['registration' => $registration, 'events' => $events, 'actions' => $case['actions'] ?? []];
        $service = new RecurringPlanService($fixture['products']);
        $adapter = new FakeOrderEventAdapter($service);
        $lastEvent = [];

        try {
            $plan = $service->register(...$registration);
            foreach ($case['actions'] ?? [] as $action) {
                $service->manage($registration['planId'], $action, null, new DateTimeImmutable('2026-11-18T10:00:00Z'));
            }
            foreach ($events as $event) {
                $lastEvent = $adapter->handle($event);
                $audit[] = ['test_id' => $id, 'input' => $event] + $lastEvent;
            }

            $actual = array_intersect_key($plan->snapshot() + $lastEvent, $expected);
        } catch (InvalidArgumentException | OutOfBoundsException | DomainException $exception) {
            $actual = ['error' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $actual = ['unexpected_error' => $exception::class . ': ' . $exception->getMessage()];
        }

        ksort($actual);
        ksort($expected);
        $result = $actual === $expected ? 'PASS' : 'FAIL';
    }

    $counts[$result]++;
    $results[] = [
        'test_id' => $id,
        'input' => $input,
        'expected' => $expected,
        'actual' => $actual,
        'result' => $result,
    ] + (isset($case['deferred']) ? ['note' => $case['deferred']] : []);
    echo "$id $result\n";
}

$reports = [
    'plan-test-results.json' => ['label' => $fixture['label'], 'summary' => $counts, 'tests' => $results],
    'plan-test-audit-log.json' => ['label' => $fixture['label'], 'events' => $audit],
];

foreach ($reports as $file => $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($root . '/reports/' . $file, $json, LOCK_EX) !== strlen($json)) {
        fwrite(STDERR, "Could not write $file\n");
        exit(1);
    }
}

echo "{$counts['PASS']} PASS, {$counts['FAIL']} FAIL, {$counts['DEFERRED']} DEFERRED\n";
exit($counts['FAIL'] > 0 ? 1 : 0);
