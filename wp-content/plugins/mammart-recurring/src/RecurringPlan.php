<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use DateTimeImmutable;
use InvalidArgumentException;
use DomainException;
use DateTimeZone;

require_once __DIR__ . '/SandboxDate.php';

final class RecurringPlan
{
    public const PENDING = 'PendingActivation';
    public const ACTIVE = 'Active';
    public const PAUSED = 'Paused';
    public const CANCELLED = 'Cancelled';

    private string $status = self::PENDING;
    private ?string $nextExpectedDate = null;
    private bool $initialOrderCancelled = false;
    private array $orderPeriods = [];
    private array $finishedOrders = [];

    public function __construct(
        private readonly string $planId,
        private readonly string $customerId,
        private readonly string $productId,
        private readonly string $initialOrderId,
        private int $quantity,
        private int $frequencyDays = 28,
        private ?string $variationId = null,
    ) {
        if (trim($planId) === '' || trim($customerId) === '' || trim($productId) === '' || trim($initialOrderId) === '') {
            throw new InvalidArgumentException('Plan, customer, product and initial order IDs are required.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        if (!in_array($frequencyDays, [21, 28, 35], true)) {
            throw new InvalidArgumentException('Frequency must be 21, 28 or 35 days.');
        }
    }

    public function completeInitialOrder(string $orderId, DateTimeImmutable $completedAt): string
    {
        if ($orderId !== $this->initialOrderId) {
            return 'not_initial_order';
        }

        if ($this->status !== self::PENDING || $this->initialOrderCancelled) {
            return 'activation_not_allowed';
        }

        $this->nextExpectedDate = $completedAt->modify("+{$this->frequencyDays} days")->format('Y-m-d');
        $this->status = self::ACTIVE;

        return 'initial_order_completed';
    }

    public function recordOrderCancellation(string $orderId): string
    {
        if ($this->status === self::PENDING && $orderId === $this->initialOrderId) {
            $this->initialOrderCancelled = true;

            return 'initial_order_cancelled';
        }

        if ($this->status === self::ACTIVE) {
            $this->status = self::PAUSED;

            return 'active_plan_order_cancelled';
        }

        return 'cancellation_no_transition';
    }

    public function applyOrderEvent(array $event, DateTimeImmutable $at): string
    {
        $orderId = $event['order_id'];
        $periodId = $event['period_id'];
        $type = $event['event_type'];
        if ($event['is_first_order']) {
            if ($orderId !== $this->initialOrderId) {
                return 'not_initial_order';
            }
            if (isset($this->orderPeriods[$orderId]) && $this->orderPeriods[$orderId]['period_id'] !== $periodId) {
                throw new DomainException('ORDER_PERIOD_MISMATCH');
            }
            $this->orderPeriods[$orderId] = ['period_id' => $periodId, 'expected_date' => null];
            return match ($type) {
                'OrderPlaced' => 'initial_order_placed',
                'OrderCompleted' => $this->completeInitialOrder($orderId, $at),
                'OrderCancelled' => $this->recordOrderCancellation($orderId),
            };
        }

        if ($orderId === $this->initialOrderId) {
            throw new DomainException('INITIAL_ORDER_FLAG_MISMATCH');
        }
        if ($this->status !== self::ACTIVE) {
            throw new DomainException('RECURRING_PLAN_NOT_ACTIVE');
        }
        if (isset($this->finishedOrders[$orderId])) {
            return 'order_already_processed';
        }

        $reference = $this->orderPeriods[$orderId] ?? null;
        if ($reference === null) {
            if ($type !== 'OrderPlaced' && $periodId !== $this->planId . '@' . $this->nextExpectedDate) {
                throw new DomainException('ORDER_PERIOD_NOT_LINKED');
            }
            foreach ($this->orderPeriods as $linked) {
                if ($linked['period_id'] === $periodId || $linked['expected_date'] === $this->nextExpectedDate) {
                    throw new DomainException('PERIOD_ALREADY_LINKED');
                }
            }
            $reference = ['period_id' => $periodId, 'expected_date' => $this->nextExpectedDate];
        }
        if ($reference['period_id'] !== $periodId || $reference['expected_date'] !== $this->nextExpectedDate) {
            throw new DomainException('ORDER_PERIOD_MISMATCH');
        }
        $this->orderPeriods[$orderId] = $reference;
        if ($type === 'OrderPlaced') {
            return 'recurring_order_placed';
        }
        $this->finishedOrders[$orderId] = true;
        if ($type === 'OrderCancelled') {
            return $this->recordOrderCancellation($orderId);
        }

        $this->nextExpectedDate = $at->modify("+{$this->frequencyDays} days")->format('Y-m-d');
        return 'recurring_cycle_recalculated';
    }

    public function hasOpenOrder(): bool
    {
        foreach ($this->orderPeriods as $orderId => $reference) {
            if ($orderId !== $this->initialOrderId && !isset($this->finishedOrders[$orderId]) && $reference['expected_date'] === $this->nextExpectedDate) {
                return true;
            }
        }
        return false;
    }

    public function manage(string $action, mixed $value, DateTimeImmutable $now, array $validVariations): void
    {
        $today = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
        if ($action === 'cancel') {
            $this->status = self::CANCELLED;
            return;
        }
        if ($this->status === self::CANCELLED) {
            throw new DomainException('PLAN_CANCELLED');
        }
        switch ($action) {
            case 'quantity':
                if (!is_int($value) || $value <= 0) {
                    throw new InvalidArgumentException('Quantity must be positive.');
                }
                $this->quantity = $value;
                break;
            case 'variation':
                if (!is_string($value) || !in_array($value, $validVariations, true)) {
                    throw new InvalidArgumentException('INVALID_VARIATION');
                }
                $this->variationId = $value;
                break;
            case 'cycle':
                if (!is_int($value) || !in_array($value, [21, 28, 35], true)) {
                    throw new InvalidArgumentException('Frequency must be 21, 28 or 35 days.');
                }
                $this->frequencyDays = $value;
                break;
            case 'postpone':
                if ($this->nextExpectedDate === null) {
                    throw new DomainException('PLAN_NOT_ACTIVATED');
                }
                $date = SandboxDate::parse($value, 'next_expected_date')->format('Y-m-d');
                if (strlen((string) $value) !== 10 || $date < $today) {
                    throw new DomainException('EXPECTED_DATE_IN_PAST');
                }
                $this->nextExpectedDate = $date;
                break;
            case 'pause':
                if ($this->status !== self::ACTIVE && $this->status !== self::PAUSED) {
                    throw new DomainException('PLAN_NOT_ACTIVE');
                }
                $this->status = self::PAUSED;
                break;
            case 'resume':
                if ($this->status !== self::PAUSED) {
                    throw new DomainException('PLAN_NOT_PAUSED');
                }
                if ($this->nextExpectedDate === null || $this->nextExpectedDate <= $today) {
                    throw new DomainException('NEW_EXPECTED_DATE_REQUIRED');
                }
                $this->status = self::ACTIVE;
                break;
            default:
                throw new InvalidArgumentException('UNKNOWN_PLAN_ACTION');
        }
    }

    public function pauseIfMissed(string $today): bool
    {
        if ($this->status !== self::ACTIVE || $this->nextExpectedDate === null || $today <= $this->nextExpectedDate) {
            return false;
        }

        $this->status = self::PAUSED;

        return true;
    }

    public function snapshot(): array
    {
        return [
            'plan_id' => $this->planId,
            'customer_id' => $this->customerId,
            'product_id' => $this->productId,
            'variation_id' => $this->variationId,
            'quantity' => $this->quantity,
            'frequency_days' => $this->frequencyDays,
            'status' => $this->status,
            'next_expected_date' => $this->nextExpectedDate,
            'source_order_id' => $this->initialOrderId,
        ];
    }
}
