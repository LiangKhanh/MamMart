<?php

declare(strict_types=1);

/** @return array<string, array> */
return static function (array $events): array {
    $pending = ['status' => 'PendingActivation', 'next_expected_date' => null];
    $completed = $events['completed'];
    $initialCancelled = $events['initial_cancelled'];

    return [
        'PLAN-01' => ['expected' => ['frequency_days' => 28] + $pending],
        'PLAN-02' => ['registration' => ['frequencyDays' => 21], 'expected' => ['frequency_days' => 21]],
        'PLAN-03' => ['registration' => ['frequencyDays' => 35], 'expected' => ['frequency_days' => 35]],
        'PLAN-04' => ['registration' => ['frequencyDays' => 30], 'expected' => ['error' => 'Frequency must be 21, 28 or 35 days.']],
        'PLAN-05' => ['registration' => ['quantity' => 0], 'expected' => ['error' => 'Quantity must be positive.']],
        'PLAN-06' => [
            'registration' => ['frequencyDays' => 21],
            'events' => [$completed],
            'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-09'],
        ],
        'PLAN-07' => [
            'events' => [$completed],
            'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-16'],
        ],
        'PLAN-08' => ['events' => [$events['payment_confirmed']], 'expected' => $pending],
        'PLAN-09' => [
            'events' => [$completed, $events['reorder_cancelled']],
            'expected' => ['status' => 'Paused', 'next_expected_date' => '2026-12-16'],
        ],
        'PLAN-10' => ['events' => [$initialCancelled], 'expected' => $pending],
        'PLAN-11' => [
            'actions' => ['cancel', 'resume'],
            'expected' => ['error' => 'PLAN_CANCELLED'],
        ],
        'PLAN-12' => [
            'registration' => ['productId' => 'P003'],
            'expected' => ['error' => 'Product is unknown or ineligible in the sandbox.'],
        ],
        'PLAN-13' => [
            'registration' => ['productId' => 'P999'],
            'expected' => ['error' => 'Product is unknown or ineligible in the sandbox.'],
        ],
        'PLAN-14' => [
            'events' => [$initialCancelled, $completed],
            'expected' => $pending + ['reason' => 'activation_not_allowed'],
        ],
        'PLAN-15' => [
            'events' => [array_replace($completed, ['order_id' => 'FAKE-WRONG-ORDER'])],
            'expected' => $pending + ['reason' => 'not_initial_order'],
        ],
        'PLAN-16' => [
            'events' => [$completed, array_replace($completed, ['event_id' => 'PLAN-INITIAL-COMPLETED-SECOND', 'completed_at' => '2026-11-19'])],
            'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-16', 'reason' => 'activation_not_allowed'],
        ],
        'PLAN-17' => [
            'events' => [array_replace($completed, ['plan_id' => 'PLAN-MISSING'])],
            'expected' => ['error' => 'Plan does not exist.'],
        ],
        'PLAN-18' => [
            'events' => [array_replace($completed, ['completed_at' => '2026-02-30'])],
            'expected' => ['error' => 'completed_at must be a valid YYYY-MM-DD date or ISO-8601 timestamp.'],
        ],
        'PLAN-19' => [
            'events' => [array_replace($completed, ['plan_id' => null, 'source_ref' => $completed['plan_id']])],
            'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-16'],
        ],
    ];
};
