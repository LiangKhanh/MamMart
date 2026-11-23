<?php

declare(strict_types=1);

use Mammart\Recurring\FakeMailer;
use Mammart\Recurring\FakeOrderEventAdapter;
use Mammart\Recurring\RecurringPlan;
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

$fixture = json_decode(file_get_contents($root . '/fixtures/reminder-simulation.json'), true, 512, JSON_THROW_ON_ERROR);
$planFixture = json_decode(file_get_contents($root . '/fixtures/plan-simulation.json'), true, 512, JSON_THROW_ON_ERROR);
$makeCases = require $root . '/tests/reminder-cases.php';

$register = static function (RecurringPlanService $plans, string $id, string $status, string $completedAt) use ($fixture): RecurringPlan {
    $orderId = 'FAKE-ORDER-' . $id;
    $plan = $plans->register($id, $fixture['customer_id'], $fixture['product_id'], $orderId, 1, $fixture['frequency_days']);
    if ($status === RecurringPlan::PENDING) {
        return $plan;
    }

    $adapter = new FakeOrderEventAdapter($plans);
    $adapter->handle(['event_type' => 'OrderCompleted', 'plan_id' => $id, 'order_id' => $orderId, 'completed_at' => $completedAt]);
    if ($status === RecurringPlan::PAUSED) {
        $adapter->handle(['event_type' => 'OrderCancelled', 'plan_id' => $id, 'order_id' => 'FAKE-REORDER-' . $id, 'cancelled_at' => $completedAt]);
    } elseif ($status === RecurringPlan::CANCELLED) {
        $plans->manage($id, 'cancel', null, new DateTimeImmutable($completedAt));
    }

    return $plan;
};

$results = [];
$audit = [];
$counts = ['PASS' => 0, 'FAIL' => 0, 'DEFERRED' => 0];

foreach ($makeCases($fixture) as $id => $case) {
    [$description, $runNames, $expected] = $case;
    $options = $case[3] ?? [];
    $status = $options['status'] ?? RecurringPlan::ACTIVE;
    $confirmed = $options['confirmed_cycles'] ?? [];
    $failureReason = ($options['fail'] ?? false) ? $fixture['failure_reason'] : null;
    $input = [
        'plan_id' => $fixture['plan_id'],
        'initial_status' => $status,
        'frequency_days' => $fixture['frequency_days'],
        'completed_at' => $status === RecurringPlan::PENDING ? null : $fixture['completed_at'],
        'expected_date' => $status === RecurringPlan::PENDING ? null : $fixture['expected_date'],
        'runs' => array_map(static fn (string $name) => $fixture['runs'][$name], $runNames),
        'fake_mail_failure_reason' => $failureReason,
        'confirmed_cycles' => $confirmed,
    ];

    try {
        $plans = new RecurringPlanService($planFixture['products']);
        $plan = $register($plans, $fixture['plan_id'], $status, $fixture['completed_at']);
        if ($options['second_plan'] ?? false) {
            $input['second_plan_id'] = 'PLAN-REM-002';
            $register($plans, $input['second_plan_id'], RecurringPlan::ACTIVE, $fixture['completed_at']);
        }

        $mailer = new FakeMailer($failureReason);
        $reminders = new ReminderService($mailer);
        $scheduler = new SandboxScheduler($plans, $reminders);
        $entries = [];
        foreach ($runNames as $name) {
            if ($name === 'cycle_2_t_minus_2') {
                // Use a fresh plan projection and the same reminder store to model the next cycle.
                $input['cycle_2_completed_at'] = $fixture['cycle_2_completed_at'];
                $input['cycle_2_expected_date'] = $fixture['cycle_2_expected_date'];
                $nextCycle = new RecurringPlanService($planFixture['products']);
                $plan = $register($nextCycle, $fixture['plan_id'], RecurringPlan::ACTIVE, $input['cycle_2_completed_at']);
                $scheduler = new SandboxScheduler($nextCycle, $reminders);
            }
            array_push($entries, ...$scheduler->run(new DateTimeImmutable($fixture['runs'][$name]), $confirmed));
        }

        foreach ($entries as $entry) {
            $audit[] = ['test_id' => $id] + $entry;
        }
        $records = $reminders->snapshots();
        $first = $records[0] ?? [];
        $planData = $plan->snapshot();
        $actual = [
            'count' => count($records),
            'attempts' => $mailer->attempts(),
            'reminder_date' => $first['reminder_date'] ?? null,
            'sent_at' => $first['sent_at'] ?? null,
            'failure_reason' => $first['failure_reason'] ?? null,
            'reminder_status' => $first['status'] ?? null,
            'keys' => array_column($records, 'dedupe_key'),
            'events' => array_column($entries, 'event'),
            'pause_reasons' => array_column($entries, 'reason'),
            'plan_status' => $planData['status'],
            'expected_date' => $planData['next_expected_date'],
        ];
        $actual = array_intersect_key($actual, $expected);
    } catch (Throwable $error) {
        $actual = ['error' => $error::class . ': ' . $error->getMessage()];
    }

    ksort($expected);
    ksort($actual);
    $result = $actual === $expected ? 'PASS' : 'FAIL';
    $counts[$result]++;
    $results[] = ['test_id' => $id, 'description' => $description, 'input' => $input, 'expected' => $expected, 'actual' => $actual, 'result' => $result];
    echo "$id $result\n";
}

$json = static fn (array $value, bool $pretty = false): string => json_encode(
    $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | ($pretty ? JSON_PRETTY_PRINT : 0),
);
$summary = "{$counts['PASS']} PASS, {$counts['FAIL']} FAIL, {$counts['DEFERRED']} DEFERRED";
$markdown = "# Reminder tests\n\n{$fixture['label']}\n\n$summary\n";
foreach ($results as $row) {
    $markdown .= "\n## {$row['test_id']} — {$row['result']}\n\n{$row['description']}\n\n"
        . '- Input: `' . $json($row['input']) . "`\n"
        . '- Expected: `' . $json($row['expected']) . "`\n"
        . '- Actual: `' . $json($row['actual']) . "`\n";
}

$reports = [
    'reminder-test-results.json' => $json(['label' => $fixture['label'], 'summary' => $counts, 'tests' => $results], true) . "\n",
    'reminder-test-results.md' => $markdown,
    'reminder-test-audit-log.json' => $json(['label' => $fixture['label'], 'events' => $audit], true) . "\n",
];
foreach ($reports as $name => $body) {
    if (file_put_contents($root . '/reports/' . $name, $body, LOCK_EX) !== strlen($body)) {
        fwrite(STDERR, "Could not write $name\n");
        exit(1);
    }
}

echo "$summary\n";
exit($counts['FAIL'] > 0 ? 1 : 0);
