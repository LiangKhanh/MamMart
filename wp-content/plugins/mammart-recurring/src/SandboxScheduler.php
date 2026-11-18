<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use DateTimeImmutable;
use DateTimeZone;

require_once __DIR__ . '/RecurringPlanService.php';
require_once __DIR__ . '/ReminderService.php';

final class SandboxScheduler
{
    public function __construct(
        private readonly RecurringPlanService $plans,
        private readonly ReminderService $reminders,
    ) {
    }

    /** @param list<string> $confirmedCycles Dedupe keys with a simulated confirmation or completion. */
    public function run(DateTimeImmutable $now, array $confirmedCycles = []): array
    {
        $now = $now->setTimezone(new DateTimeZone('UTC'));
        $today = $now->format('Y-m-d');
        $audit = [];

        foreach ($this->plans->all() as $plan) {
            $data = $plan->snapshot();
            $expected = $data['next_expected_date'];
            $planId = $data['plan_id'];
            if ($data['status'] !== RecurringPlan::ACTIVE || $expected === null) {
                continue;
            }

            $key = RecurringReminder::keyForCycle($planId, $expected);
            if (in_array($key, $confirmedCycles, true) || $plan->hasOpenOrder()) {
                continue;
            }

            if ($plan->pauseIfMissed($today)) {
                $audit[] = [
                    'event' => 'PLAN_PAUSED_MISSED_DATE',
                    'reason' => 'MISSED_EXPECTED_DATE',
                    'plan_id' => $planId,
                    'expected_date' => $expected,
                    'dedupe_key' => $key,
                    'from' => RecurringPlan::ACTIVE,
                    'to' => RecurringPlan::PAUSED,
                ];
            } elseif ($today === RecurringReminder::dateForCycle($expected)) {
                array_push($audit, ...$this->reminders->createForCycle($planId, $expected, $now));
            }
        }

        return array_map(static fn (array $entry) => $entry + ['run_at' => $now->format(DATE_ATOM)], $audit);
    }
}
