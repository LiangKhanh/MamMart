<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use InvalidArgumentException;
use OutOfBoundsException;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

require_once __DIR__ . '/RecurringPlan.php';

final class RecurringPlanService
{
    // ponytail: plans last for one sandbox run; add persistent storage when T3 needs it.
    /** @var array<string, RecurringPlan> */
    private array $plans = [];
    private array $audit = [];

    public function __construct(private readonly array $products)
    {
    }

    public function register(
        string $planId,
        string $customerId,
        string $productId,
        string $initialOrderId,
        int $quantity,
        int $frequencyDays = 28,
        ?string $variationId = null,
    ): RecurringPlan {
        if (($this->products[$productId]['_is_recurring_eligible'] ?? null) !== 1) {
            throw new InvalidArgumentException('Product is unknown or ineligible in the sandbox.');
        }

        if (isset($this->plans[$planId])) {
            throw new InvalidArgumentException('Plan ID already exists.');
        }

        return $this->plans[$planId] = new RecurringPlan(
            $planId, $customerId, $productId, $initialOrderId, $quantity, $frequencyDays, $variationId,
        );
    }

    public function get(string $planId): RecurringPlan
    {
        return $this->plans[$planId] ?? throw new OutOfBoundsException('Plan does not exist.');
    }

    /** @return list<RecurringPlan> */
    public function all(): array
    {
        return array_values($this->plans);
    }

    public function manage(string $planId, string $action, mixed $value, DateTimeImmutable $now): array
    {
        $plan = $this->get($planId);
        $previous = $plan->snapshot();
        $ruleType = $action === 'cycle' && $previous['next_expected_date'] !== null
            ? 'SANDBOX_ASSUMPTION' : 'CHOT';
        try {
            $plan->manage($action, $value, $now, $this->products[$previous['product_id']]['variations'] ?? []);
        } catch (DomainException | InvalidArgumentException $error) {
            $this->audit[] = [
                'plan_id' => $planId, 'action' => $action, 'event' => 'PLAN_ACTION_REJECTED',
                'previous' => $previous, 'new' => $plan->snapshot(),
                'timestamp' => $now->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
                'reason' => $error->getMessage(),
                'rule_type' => $error->getMessage() === 'NEW_EXPECTED_DATE_REQUIRED' ? 'OPEN_RULE_GUARD' : $ruleType,
            ];
            throw $error;
        }
        $new = $plan->snapshot();
        if ($new === $previous) {
            return ['changed' => false];
        }
        $event = match ($action) {
            'quantity' => 'PLAN_QUANTITY_CHANGED',
            'variation' => 'PLAN_VARIATION_CHANGED',
            'cycle' => $ruleType === 'SANDBOX_ASSUMPTION' ? 'CYCLE_CHANGED_WITH_CURRENT_SCHEDULE_PRESERVED' : 'PLAN_CYCLE_CHANGED',
            'postpone' => 'PLAN_POSTPONED',
            'pause' => 'PLAN_PAUSED_BY_CUSTOMER',
            'resume' => 'PLAN_RESUMED',
            'cancel' => 'PLAN_CANCELLED_BY_CUSTOMER',
        };
        $entry = [
            'plan_id' => $planId, 'action' => $action, 'event' => $event,
            'previous' => $previous, 'new' => $new, 'rule_type' => $ruleType,
            'timestamp' => $now->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
        ];
        $this->audit[] = $entry;
        return ['changed' => true] + $entry;
    }

    public function auditLog(): array
    {
        return $this->audit;
    }
}
