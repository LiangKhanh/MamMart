<?php

declare(strict_types=1);

return static function (array $fixture): array {
    $key = $fixture['plan_id'] . '@' . $fixture['expected_date'];
    $sent = [
        'count' => 1,
        'attempts' => 1,
        'reminder_date' => $fixture['reminder_date'],
        'sent_at' => $fixture['runs']['t_minus_2'],
        'reminder_status' => 'SENT',
        'keys' => [$key],
    ];
    $failed = [
        'count' => 1,
        'attempts' => 1,
        'reminder_status' => 'FAILED',
        'failure_reason' => $fixture['failure_reason'],
        'sent_at' => null,
    ];
    $quiet = ['count' => 0, 'attempts' => 0, 'events' => []];
    $paused = ['plan_status' => 'Paused', 'expected_date' => $fixture['expected_date']];
    $t2 = ['t_minus_2'];

    // [description, fake scheduler runs, expected subset, optional scenario overrides]
    return [
        'REM-01' => ['Active plan sends one T-2 reminder', $t2, $sent + ['events' => ['REMINDER_CREATED', 'EMAIL_SENT']]],
        'REM-02' => ['Three runs during T-2 send once', [...$t2, 't_minus_2_repeat', 't_minus_2_noon'], $sent + [
            'events' => ['REMINDER_CREATED', 'EMAIL_SENT', 'DUPLICATE_SKIPPED', 'DUPLICATE_SKIPPED'],
        ]],
        'REM-03' => ['Before T-2 there is no reminder', ['early'], $quiet],
        'REM-04' => ['Paused plan is skipped', $t2, $quiet + ['plan_status' => 'Paused'], ['status' => 'Paused']],
        'REM-05' => ['Cancelled plan is skipped', $t2, $quiet + ['plan_status' => 'Cancelled'], ['status' => 'Cancelled']],
        'REM-06' => ['Pending plan is skipped', $t2, $quiet + ['plan_status' => 'PendingActivation'], ['status' => 'PendingActivation']],
        'REM-07' => ['Fake mail failure is logged', $t2, $failed + ['events' => ['REMINDER_CREATED', 'EMAIL_FAILED']], ['fail' => true]],
        'REM-08' => ['Failed mail is neither retried nor duplicated', [...$t2, 't_minus_2_repeat'], $failed + [
            'events' => ['REMINDER_CREATED', 'EMAIL_FAILED', 'DUPLICATE_SKIPPED'],
        ], ['fail' => true]],
        'REM-09' => ['Expected date has not ended at 23:59:59 UTC', ['expected'], $quiet + [
            'plan_status' => 'Active', 'expected_date' => $fixture['expected_date'],
        ]],
        'REM-10' => ['The day after expected date pauses the plan without advancing the cycle', ['expired'], $paused + [
            'count' => 0, 'attempts' => 0, 'events' => ['PLAN_PAUSED_MISSED_DATE'], 'pause_reasons' => ['MISSED_EXPECTED_DATE'],
        ]],
        'REM-11' => ['Repeating an expired run logs only one pause', ['expired', 'expired_repeat'], $paused + [
            'count' => 0, 'attempts' => 0, 'events' => ['PLAN_PAUSED_MISSED_DATE'],
        ]],
        'REM-12' => ['Two plans on the same date have distinct keys', $t2, [
            'count' => 2, 'attempts' => 2, 'keys' => [$key, 'PLAN-REM-002@' . $fixture['expected_date']],
        ], ['second_plan' => true]],
        'REM-13' => ['Two cycles for one plan have distinct keys', [...$t2, 'cycle_2_t_minus_2'], [
            'count' => 2, 'attempts' => 2, 'keys' => [$key, $fixture['plan_id'] . '@' . $fixture['cycle_2_expected_date']],
        ]],
        'REM-14' => ['A confirmed cycle does not pause after its expected date', ['expired'], $quiet + [
            'plan_status' => 'Active', 'expected_date' => $fixture['expected_date'],
        ], ['confirmed_cycles' => [$key]]],
        'REM-15' => ['Missing the T-2 run does not send a late reminder at T-1', ['t_minus_1'], $quiet + ['plan_status' => 'Active']],
    ];
};
