<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use InvalidArgumentException;
use DomainException;

require_once __DIR__ . '/RecurringPlanService.php';

/** T2 fake event boundary. No WooCommerce hooks are registered. */
final class FakeOrderEventAdapter
{
    // ponytail: receipts last for this adapter instance; use durable receipts for T3 workers.
    private array $receipts = [];
    private array $audit = [];

    public function __construct(private readonly RecurringPlanService $plans)
    {
    }

    public function handle(array $event): array
    {
        $event = $this->normalize($event);
        $plan = $this->plans->get($event['plan_id']);
        $previous = $plan->snapshot();
        if ($event['customer_id'] !== $previous['customer_id']) {
            throw new DomainException('CUSTOMER_MISMATCH');
        }
        $id = $event['event_id'];
        $fingerprint = hash('sha256', json_encode($event, JSON_THROW_ON_ERROR));
        $at = SandboxDate::parse($event['event_time'], 'event_time');
        $action = 'ORDER_EVENT_ACCEPTED';
        if (isset($this->receipts[$id])) {
            if ($this->receipts[$id] !== $fingerprint) {
                throw new DomainException('EVENT_ID_CONFLICT');
            }
            $reason = 'duplicate_event_skipped';
            $action = 'ORDER_EVENT_DUPLICATE_SKIPPED';
        } else {
            $reason = $event['event_type'] === 'PaymentConfirmed'
                ? 'payment_confirmed_ignored'
                : $plan->applyOrderEvent($event, SandboxDate::parse(
                    $event['completed_at'] ?? $event['cancelled_at'] ?? $event['event_time'],
                    $event['event_type'] === 'OrderCompleted' ? 'completed_at' : 'cancelled_at',
                ));
            $this->receipts[$id] = $fingerprint;
            if ($reason === 'recurring_cycle_recalculated') {
                $action = 'RECURRING_CYCLE_RECALCULATED';
            }
        }
        $new = $plan->snapshot();
        $entry = $event + [
            'event' => $action, 'reason' => $reason,
            'previous' => $previous, 'new' => $new,
            'from' => $previous['status'], 'to' => $new['status'],
            'next_expected_date' => $new['next_expected_date'],
            'timestamp' => $at->format(DATE_ATOM),
            'rule_type' => $event['contract_mode'] === 'LEGACY_SANDBOX_NORMALIZED' ? 'SANDBOX_ASSUMPTION' : 'CHOT',
        ];
        $this->audit[] = $entry;
        return $entry;
    }

    /** Normalize old date-level T2 fixtures and validate the complete T1-NL envelope. */
    public function normalize(array $event): array
    {
        $type = $this->requiredString($event['event_type'] ?? null, 'event_type');
        if (!in_array($type, ['OrderPlaced', 'OrderCompleted', 'OrderCancelled', 'PaymentConfirmed'], true)) {
            throw new InvalidArgumentException('Unsupported fake event type.');
        }
        $planId = $this->requiredString($event['plan_id'] ?? $event['source_ref'] ?? null, 'plan_id or source_ref');
        if (isset($event['plan_id'], $event['source_ref']) && $event['source_ref'] !== $planId) {
            throw new InvalidArgumentException('plan_id and source_ref disagree.');
        }
        $plan = $this->plans->get($planId)->snapshot();
        $orderId = $this->requiredString($event['order_id'] ?? null, 'order_id');
        $legacy = !array_key_exists('event_id', $event);
        if ($legacy) {
            // SANDBOX_ASSUMPTION: old fixtures link the initial order by its explicit source reference.
            $initial = $orderId === $plan['source_order_id'];
            $event += [
                'event_id' => 'LEGACY-' . hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)),
                'event_time' => $event['completed_at'] ?? $event['cancelled_at'] ?? '1970-01-01',
                'customer_id' => $plan['customer_id'],
                'period_id' => $initial ? 'INITIAL-' . $orderId : $planId . '@' . $plan['next_expected_date'],
                'is_first_order' => $initial,
            ];
        }
        $normalized = ['event_type' => $type, 'plan_id' => $planId];
        foreach (['event_id', 'event_time', 'order_id', 'customer_id', 'period_id'] as $field) {
            $normalized[$field] = $this->requiredString($event[$field] ?? null, $field);
        }
        if (!is_bool($event['is_first_order'] ?? null)) {
            throw new InvalidArgumentException('is_first_order must be boolean.');
        }
        $normalized['is_first_order'] = $event['is_first_order'];
        SandboxDate::parse($normalized['event_time'], 'event_time');
        if ($type === 'OrderCompleted' || $type === 'OrderCancelled') {
            $field = $type === 'OrderCompleted' ? 'completed_at' : 'cancelled_at';
            $normalized[$field] = $this->requiredString($event[$field] ?? $event['event_time'], $field);
            SandboxDate::parse($normalized[$field], $field);
        }
        $normalized['contract_mode'] = $legacy ? 'LEGACY_SANDBOX_NORMALIZED' : 'T1_NL';
        return $normalized;
    }

    public function auditLog(): array
    {
        return $this->audit;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($field . ' must be a non-empty string.');
        }
        return $value;
    }
}
