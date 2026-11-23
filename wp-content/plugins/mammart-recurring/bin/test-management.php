<?php

declare(strict_types=1);

use Mammart\Recurring\FakeMailer;
use Mammart\Recurring\FakeOrderEventAdapter;
use Mammart\Recurring\RecurringPlanService;
use Mammart\Recurring\ReminderService;
use Mammart\Recurring\SandboxScheduler;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
require_once $root . '/src/FakeOrderEventAdapter.php';
require_once $root . '/src/SandboxScheduler.php';

$management = json_decode(file_get_contents($root . '/fixtures/management-simulation.json'), true, 512, JSON_THROW_ON_ERROR);
$eventFixture = json_decode(file_get_contents($root . '/fixtures/event-contract-simulation.json'), true, 512, JSON_THROW_ON_ERROR);
$managementCases = require $root . '/tests/management-cases.php';
$eventCases = require $root . '/tests/event-contract-cases.php';
$results = [];
$audit = [];
$counts = ['MGMT' => ['PASS' => 0, 'FAIL' => 0, 'DEFERRED' => 0], 'EVT' => ['PASS' => 0, 'FAIL' => 0, 'DEFERRED' => 0]];

foreach ($managementCases + $eventCases as $id => $case) {
    $group = str_starts_with($id, 'MGMT') ? 'MGMT' : 'EVT';
    $fixture = $group === 'MGMT' ? $management : $eventFixture;
    $plans = new RecurringPlanService($fixture['products']);
    $plan = $plans->register(...$fixture['registration']);
    $planId = $fixture['registration']['planId'];
    $adapter = new FakeOrderEventAdapter($plans);
    $mailer = new FakeMailer();
    $reminders = new ReminderService($mailer);
    $scheduler = new SandboxScheduler($plans, $reminders);
    $now = new DateTimeImmutable($case['now'] ?? $management['now']);
    $status = $case['status'] ?? ($group === 'MGMT' ? 'Active' : 'PendingActivation');
    $input = ['registration' => $fixture['registration'], 'initial_status' => $status, 'now' => $now->format(DATE_ATOM), 'operations' => []];

    if ($status !== 'PendingActivation') {
        $adapter->handle(array_replace($eventFixture['events']['initial'], [
            'event_id' => 'SETUP-' . $id, 'plan_id' => $planId,
        ]));
        $input['setup_event'] = $adapter->auditLog()[0];
        if ($status === 'Paused' || $status === 'Cancelled') {
            $plans->manage($planId, $status === 'Paused' ? 'pause' : 'cancel', null, $now);
        }
    }
    $managementOffset = count($plans->auditLog());
    $eventOffset = count($adapter->auditLog());
    $schedulerLog = [];
    $error = null;
    $dispatch = static function (mixed $spec) use ($eventFixture, $adapter, &$input): void {
        $event = is_string($spec)
            ? $eventFixture['events'][$spec]
            : array_replace($eventFixture['events'][$spec[0]], $spec[1]);
        $input['operations'][] = ['fake_event' => $event];
        $adapter->handle($event);
    };

    try {
        foreach ($case['events'] ?? [] as $event) {
            $dispatch($event);
        }
        foreach ($case['operations'] ?? [] as [$action, $value]) {
            $input['operations'][] = ['action' => $action, 'value' => $value, 'now' => $now->format(DATE_ATOM)];
            if ($action === 'run') {
                $now = new DateTimeImmutable($value);
                array_push($schedulerLog, ...$scheduler->run($now));
            } else {
                $plans->manage($planId, $action, $value, $now);
            }
        }
        foreach ($case['after_events'] ?? [] as $event) {
            $dispatch($event);
        }
    } catch (DomainException | InvalidArgumentException | OutOfBoundsException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'UNEXPECTED: ' . $exception::class . ': ' . $exception->getMessage();
    }

    $actions = array_slice($plans->auditLog(), $managementOffset);
    $events = array_slice($adapter->auditLog(), $eventOffset);
    foreach (array_merge($actions, $events, $schedulerLog) as $entry) {
        $audit[] = ['test_id' => $id] + $entry;
    }
    if ($error !== null && $group === 'EVT') {
        $lastInput = end($input['operations']);
        $rejectedEvent = $lastInput['fake_event'] ?? [];
        $audit[] = [
            'test_id' => $id, 'plan_id' => $planId, 'event' => 'ORDER_EVENT_REJECTED',
            'event_id' => $rejectedEvent['event_id'] ?? null,
            'reason' => $error, 'previous' => $plan->snapshot(), 'new' => $plan->snapshot(),
            'timestamp' => $rejectedEvent['event_time'] ?? $now->format(DATE_ATOM), 'rule_type' => 'CHOT',
        ];
    }
    $lastEvent = end($events) ?: [];
    $lastAction = end($actions) ?: [];
    $actual = $plan->snapshot() + [
        'error' => $error, 'last_reason' => $lastEvent['reason'] ?? null,
        'last_action' => $lastAction['event'] ?? null,
        'transition_count' => count(array_filter($actions, static fn (array $entry) => $entry['event'] !== 'PLAN_ACTION_REJECTED')),
        'recalculation_count' => count(array_filter($events, static fn (array $entry) => $entry['event'] === 'RECURRING_CYCLE_RECALCULATED')),
        'reminder_count' => count($reminders->snapshots()),
        'reminder_keys' => array_column($reminders->snapshots(), 'dedupe_key'),
    ];
    $actual = array_intersect_key($actual, $case['expected']);
    $expected = $case['expected'];
    ksort($actual);
    ksort($expected);
    $result = $actual === $expected ? 'PASS' : 'FAIL';
    $counts[$group][$result]++;
    $results[$group][] = [
        'test_id' => $id, 'input' => $input, 'expected' => $expected, 'actual' => $actual,
        'result' => $result, 'rule_type' => $case['rule_type'] ?? 'CHOT',
    ];
    echo "$id $result\n";
}

$json = static fn (array $data): string => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$markdown = "# Management tests\n\nSIMULATION DATA ONLY\n\nPASS cho OPEN_RULE_GUARD và SANDBOX_ASSUMPTION xác nhận hành vi tạm của sandbox.\n";
foreach ($results['MGMT'] as $row) {
    $markdown .= "\n## {$row['test_id']} — {$row['result']} ({$row['rule_type']})\n\n"
        . "\x60\x60\x60json\n" . $json($row) . "\x60\x60\x60\n";
}
$reports = [
    'management-test-results.json' => $json(['label' => $management['label'], 'summary' => $counts['MGMT'], 'tests' => $results['MGMT']]),
    'management-test-results.md' => $markdown,
    'management-test-audit-log.json' => $json(['label' => $management['label'], 'events' => $audit]),
    'event-contract-test-results.json' => $json(['label' => $eventFixture['label'], 'summary' => $counts['EVT'], 'tests' => $results['EVT']]),
];
foreach ($reports as $file => $body) {
    if (file_put_contents($root . '/reports/' . $file, $body, LOCK_EX) !== strlen($body)) {
        fwrite(STDERR, "Could not write $file\n");
        exit(1);
    }
}
foreach ($counts as $group => $summary) {
    echo "$group {$summary['PASS']} PASS, {$summary['FAIL']} FAIL, {$summary['DEFERRED']} DEFERRED\n";
}
exit($counts['MGMT']['FAIL'] + $counts['EVT']['FAIL'] > 0 ? 1 : 0);
