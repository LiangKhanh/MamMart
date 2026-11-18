<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use DateTimeImmutable;

final class RecurringReminder
{
    public const CREATED = 'CREATED';
    public const SENT = 'SENT';
    public const FAILED = 'FAILED';

    private string $status = self::CREATED;
    private ?string $sentAt = null;
    private ?string $failureReason = null;

    public function __construct(
        private readonly string $planId,
        private readonly string $expectedDate,
    ) {
    }

    public static function keyForCycle(string $planId, string $expectedDate): string
    {
        return $planId . '@' . $expectedDate;
    }

    public static function dateForCycle(string $expectedDate): string
    {
        return (new DateTimeImmutable($expectedDate))->modify('-2 days')->format('Y-m-d');
    }

    public function recordDelivery(DateTimeImmutable $now, ?string $failureReason): void
    {
        if ($this->status !== self::CREATED) {
            return;
        }

        $this->status = $failureReason === null ? self::SENT : self::FAILED;
        $this->sentAt = $failureReason === null ? $now->format(DATE_ATOM) : null;
        $this->failureReason = $failureReason;
    }

    public function snapshot(): array
    {
        $key = self::keyForCycle($this->planId, $this->expectedDate);

        return [
            'reminder_id' => 'REM-' . $key,
            'plan_id' => $this->planId,
            'expected_date' => $this->expectedDate,
            'reminder_date' => self::dateForCycle($this->expectedDate),
            'dedupe_key' => $key,
            'status' => $this->status,
            'sent_at' => $this->sentAt,
            'failure_reason' => $this->failureReason,
        ];
    }
}
