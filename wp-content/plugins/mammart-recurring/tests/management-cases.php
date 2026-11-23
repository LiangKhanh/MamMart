<?php

declare(strict_types=1);

return [
    'MGMT-01' => ['operations' => [['quantity', 2]], 'expected' => ['quantity' => 2, 'status' => 'Active']],
    'MGMT-02' => ['operations' => [['quantity', 0]], 'expected' => ['quantity' => 1, 'error' => 'Quantity must be positive.']],
    'MGMT-03' => ['operations' => [['variation', 'L']], 'expected' => ['variation_id' => 'L']],
    'MGMT-04' => ['operations' => [['variation', 'UNKNOWN']], 'expected' => ['variation_id' => 'M', 'error' => 'INVALID_VARIATION']],
    'MGMT-05' => ['operations' => [['run', '2026-12-14T08:00:00Z'], ['cycle', 21]], 'rule_type' => 'SANDBOX_ASSUMPTION',
        'expected' => ['frequency_days' => 21, 'next_expected_date' => '2026-12-16', 'last_action' => 'CYCLE_CHANGED_WITH_CURRENT_SCHEDULE_PRESERVED']],
    'MGMT-06' => ['operations' => [['cycle', 30]], 'expected' => ['frequency_days' => 28, 'error' => 'Frequency must be 21, 28 or 35 days.']],
    'MGMT-07' => ['operations' => [['pause', null], ['run', '2026-12-14T08:00:00Z']], 'expected' => ['status' => 'Paused', 'reminder_count' => 0]],
    'MGMT-08' => ['operations' => [['pause', null], ['pause', null]], 'expected' => ['status' => 'Paused', 'transition_count' => 1]],
    'MGMT-09' => ['status' => 'Paused', 'operations' => [['resume', null]], 'expected' => ['status' => 'Active']],
    'MGMT-10' => ['status' => 'Paused', 'now' => '2026-12-17T08:00:00Z', 'operations' => [['resume', null]], 'rule_type' => 'OPEN_RULE_GUARD',
        'expected' => ['status' => 'Paused', 'error' => 'NEW_EXPECTED_DATE_REQUIRED']],
    'MGMT-11' => ['status' => 'Paused', 'now' => '2026-12-17T08:00:00Z', 'operations' => [['postpone', '2026-12-23'], ['resume', null]], 'rule_type' => 'OPEN_RULE_GUARD',
        'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-23']],
    'MGMT-12' => ['operations' => [['postpone', '2026-12-23']], 'expected' => ['next_expected_date' => '2026-12-23']],
    'MGMT-13' => ['operations' => [['postpone', '2026-11-30']], 'expected' => ['next_expected_date' => '2026-12-16', 'error' => 'EXPECTED_DATE_IN_PAST']],
    'MGMT-14' => ['operations' => [['cancel', null]], 'expected' => ['status' => 'Cancelled']],
    'MGMT-15' => ['status' => 'Paused', 'operations' => [['cancel', null]], 'expected' => ['status' => 'Cancelled']],
    'MGMT-16' => ['status' => 'PendingActivation', 'operations' => [['cancel', null]], 'expected' => ['status' => 'Cancelled']],
    'MGMT-17' => ['status' => 'Cancelled', 'operations' => [['resume', null]], 'expected' => ['status' => 'Cancelled', 'error' => 'PLAN_CANCELLED']],
    'MGMT-18' => ['operations' => [['cancel', null]], 'expected' => ['status' => 'Cancelled', 'source_order_id' => 'O-1001']],
    'MGMT-19' => ['status' => 'Cancelled', 'operations' => [['run', '2026-12-14T08:00:00Z']], 'expected' => ['status' => 'Cancelled', 'reminder_count' => 0]],
    'MGMT-20' => ['now' => '2026-12-14T08:00:00Z', 'operations' => [
        ['run', '2026-12-14T08:00:00Z'], ['postpone', '2026-12-23'], ['run', '2026-12-21T08:00:00Z'],
    ], 'expected' => ['reminder_count' => 2, 'reminder_keys' => ['PLAN-MGMT-001@2026-12-16', 'PLAN-MGMT-001@2026-12-23']]],
    'MGMT-21' => ['operations' => [['cancel', null], ['cancel', null]], 'expected' => ['status' => 'Cancelled', 'transition_count' => 1]],
    'MGMT-22' => ['operations' => [['postpone', '2026-02-30']], 'expected' => ['next_expected_date' => '2026-12-16',
        'error' => 'next_expected_date must be a valid YYYY-MM-DD date or ISO-8601 timestamp.']],
    'MGMT-23' => ['status' => 'PendingActivation', 'operations' => [['resume', null]], 'expected' => ['status' => 'PendingActivation', 'error' => 'PLAN_NOT_PAUSED']],
    'MGMT-24' => ['status' => 'Cancelled', 'operations' => [['quantity', 2]], 'expected' => ['status' => 'Cancelled', 'quantity' => 1, 'error' => 'PLAN_CANCELLED']],
    'MGMT-25' => ['status' => 'Paused', 'now' => '2026-12-16T08:00:00Z', 'operations' => [['resume', null]], 'rule_type' => 'OPEN_RULE_GUARD',
        'expected' => ['status' => 'Paused', 'error' => 'NEW_EXPECTED_DATE_REQUIRED']],
];
