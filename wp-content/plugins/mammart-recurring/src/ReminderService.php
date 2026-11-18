<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use DateTimeImmutable;

require_once __DIR__ . '/RecurringReminder.php';
require_once __DIR__ . '/FakeMailer.php';

final class ReminderService
{
    // ponytail: dedupe lasts for one sandbox run; enforce a unique key in persistent storage for T3.
    /** @var array<string, RecurringReminder> */
    private array $reminders = [];

    public function __construct(private readonly FakeMailer $mailer)
    {
    }

    public function createForCycle(string $planId, string $expectedDate, DateTimeImmutable $now): array
    {
        $key = RecurringReminder::keyForCycle($planId, $expectedDate);
        if (isset($this->reminders[$key])) {
            return [['event' => 'DUPLICATE_SKIPPED'] + $this->reminders[$key]->snapshot()];
        }

        $reminder = $this->reminders[$key] = new RecurringReminder($planId, $expectedDate);
        $created = ['event' => 'REMINDER_CREATED'] + $reminder->snapshot();
        $failure = $this->mailer->send();
        $reminder->recordDelivery($now, $failure);
        $delivery = ['event' => $failure === null ? 'EMAIL_SENT' : 'EMAIL_FAILED'] + $reminder->snapshot();

        return [$created, $delivery];
    }

    public function snapshots(): array
    {
        return array_values(array_map(static fn (RecurringReminder $reminder) => $reminder->snapshot(), $this->reminders));
    }
}
