<?php

declare(strict_types=1);

return [
    'EVT-01' => ['events' => ['initial'], 'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-16']],
    'EVT-02' => ['events' => ['initial', 'placed', 'recurring'], 'expected' => [
        'next_expected_date' => '2027-01-14', 'status' => 'Active', 'last_reason' => 'recurring_cycle_recalculated',
    ]],
    'EVT-03' => ['events' => ['initial', 'placed', 'recurring', 'recurring'], 'expected' => [
        'next_expected_date' => '2027-01-14', 'last_reason' => 'duplicate_event_skipped', 'recalculation_count' => 1,
    ]],
    'EVT-04' => ['events' => ['initial', 'placed', 'cancelled'], 'expected' => ['status' => 'Paused', 'next_expected_date' => '2026-12-16']],
    'EVT-05' => ['events' => ['initial', 'payment'], 'expected' => ['next_expected_date' => '2026-12-16', 'last_reason' => 'payment_confirmed_ignored']],
    'EVT-06' => ['events' => ['initial', ['placed', ['customer_id' => 'WRONG']]], 'expected' => ['error' => 'CUSTOMER_MISMATCH', 'next_expected_date' => '2026-12-16']],
    'EVT-07' => ['events' => ['initial', 'placed', ['recurring', ['period_id' => 'WRONG']]], 'expected' => ['error' => 'ORDER_PERIOD_MISMATCH', 'next_expected_date' => '2026-12-16']],
    'EVT-08' => ['events' => ['initial', ['initial', ['completed_at' => '2026-11-19']]], 'expected' => ['error' => 'EVENT_ID_CONFLICT', 'next_expected_date' => '2026-12-16']],
    'EVT-09' => ['events' => ['initial', 'placed'], 'operations' => [['run', '2026-12-17T08:00:00Z']], 'expected' => ['status' => 'Active', 'next_expected_date' => '2026-12-16', 'reminder_count' => 0]],
    'EVT-10' => ['events' => ['initial', ['recurring', ['is_first_order' => true]]], 'expected' => ['next_expected_date' => '2026-12-16', 'last_reason' => 'not_initial_order']],
    'EVT-11' => ['events' => ['initial', ['placed', ['is_first_order' => 'false']]], 'expected' => ['error' => 'is_first_order must be boolean.', 'next_expected_date' => '2026-12-16']],
    'EVT-12' => ['events' => ['initial', 'placed', 'recurring', ['recurring', ['event_id' => 'SAME-ORDER-NEW-EVENT']]], 'expected' => [
        'next_expected_date' => '2027-01-14', 'recalculation_count' => 1, 'last_reason' => 'order_already_processed',
    ]],
    'EVT-13' => ['events' => ['initial', 'placed'], 'operations' => [['postpone', '2026-12-23']], 'after_events' => ['recurring'],
        'expected' => ['next_expected_date' => '2026-12-23', 'error' => 'ORDER_PERIOD_MISMATCH']],
    'EVT-14' => ['events' => ['initial', 'placed'], 'operations' => [['cancel', null]], 'after_events' => ['recurring'],
        'expected' => ['status' => 'Cancelled', 'error' => 'RECURRING_PLAN_NOT_ACTIVE']],
    'EVT-15' => ['events' => ['initial', ['placed', ['event_time' => '2026-02-30T08:00:00Z']]], 'expected' => [
        'next_expected_date' => '2026-12-16', 'error' => 'event_time must be a valid YYYY-MM-DD date or ISO-8601 timestamp.',
    ]],
];
