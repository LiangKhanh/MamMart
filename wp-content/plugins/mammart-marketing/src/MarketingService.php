<?php
declare(strict_types=1);

namespace MamMart\Marketing;

/** Domain rules for the independent trial. No WooCommerce APIs or hooks here. */
final class MarketingService
{
    private Repository $repository;
    private Clock $clock;
    private MailAdapter $mailer;
    private array $config;

    public function __construct(Repository $repository, Clock $clock, MailAdapter $mailer, array $config)
    {
        if (($config['mode'] ?? '') !== 'sandbox' || ($config['label'] ?? '') !== 'SIMULATION ONLY' ||
            ($config['discount_percent'] ?? null) !== 15 || !($mailer instanceof FakeMailer)) {
            throw new \InvalidArgumentException('This module accepts SIMULATION ONLY and FakeMailer only.');
        }
        $this->repository = $repository;
        $this->clock = $clock;
        $this->mailer = $mailer;
        $this->config = $config;
    }

    public function setConsent(string $customerId, bool $optIn): array
    {
        return $this->repository->transaction(function (array &$s) use ($customerId, $optIn): array {
            if (!$this->isDemoId($customerId, 'C-DEMO-')) {
                return $this->record($s, 'consent', ['status' => 'INVALID_DATA']);
            }
            $consent = ['marketing_opt_in' => $optIn, 'version' => ($s['consents'][$customerId]['version'] ?? 0) + 1,
                'changed_at' => $this->timestamp()];
            $s['consents'][$customerId] = $consent;
            $s['consent_history'][] = ['customer_id' => $customerId] + $consent;
            foreach ($s['babies'] as &$baby) {
                if ($baby['customer_id'] === $customerId) {
                    $baby['marketing_opt_in'] = $optIn;
                }
            }
            unset($baby);
            return $this->record($s, 'consent', ['status' => 'CONSENT_SAVED', 'customer_id' => $customerId] + $consent);
        });
    }

    public function issue(string $babyId): array
    {
        return $this->repository->transaction(function (array &$s) use ($babyId): array {
            $key = $this->issueKey($babyId);
            if (isset($s['issues'][$key])) {
                return $this->record($s, 'issue', ['status' => 'ALREADY_ISSUED', 'issue' => $s['issues'][$key]]);
            }
            $eligibility = $this->eligibility($s, $babyId);
            if ($eligibility['status'] !== 'ELIGIBLE') {
                return $this->record($s, 'issue', ['baby_id' => $babyId] + $eligibility);
            }
            $hash = strtoupper(substr(hash('sha256', $key), 0, 16));
            $issue = ['issue_id' => 'ISSUE-DEMO-' . $hash, 'campaign_id' => $this->config['campaign_id'],
                'baby_id' => $babyId, 'customer_id' => $s['babies'][$babyId]['customer_id'],
                'coupon_id' => 'COUPON-DEMO-' . $hash, 'code' => 'DEMO-' . $hash,
                'percent' => 15, 'dob_version_at_issue' => $s['babies'][$babyId]['dob_version'],
                'issued_at' => $this->timestamp(), 'expires_at_exclusive' => $eligibility['expires_at_exclusive'],
                'used_order_id' => null, 'status' => 'ISSUED', 'label' => 'SIMULATION ONLY'];
            // The repository lock covers both the uniqueness check and durable insertion.
            $s['issues'][$key] = $issue;
            return $this->record($s, 'issue', ['status' => 'ISSUED', 'issue' => $issue]);
        });
    }

    public function send(string $couponId): array
    {
        return $this->repository->transaction(function (array &$s) use ($couponId): array {
            $issue = $this->findIssue($s, $couponId);
            if (!$issue) {
                return $this->record($s, 'mail', ['status' => 'NOT_FOUND']);
            }
            // Consent is read under the same lock immediately before each fake delivery/retry.
            if (!$this->hasConsent($s, $issue['customer_id'])) {
                return $this->record($s, 'mail', ['status' => 'NO_CONSENT', 'coupon_id' => $couponId]);
            }
            if ($this->timestamp() >= $issue['expires_at_exclusive']) {
                return $this->record($s, 'mail', ['status' => 'EXPIRED', 'coupon_id' => $couponId]);
            }
            $delivery = $this->mailer->send(['to' => 'parent@example.invalid', 'coupon_code' => $issue['code']]);
            return $this->record($s, 'mail', ['coupon_id' => $couponId, 'issue_id' => $issue['issue_id']] + $delivery);
        });
    }

    public function approveDob(array $event): array
    {
        return $this->repository->transaction(function (array &$s) use ($event): array {
            $eventId = $event['event_id'] ?? '';
            $babyId = $event['baby_id'] ?? '';
            if (!is_string($eventId) || !$this->isDemoId($eventId, 'EV-DEMO-') ||
                ($event['type'] ?? '') !== 'BabyDobApproved' || !isset($s['babies'][$babyId]) ||
                ($event['customer_id'] ?? '') !== $s['babies'][$babyId]['customer_id']) {
                return $this->record($s, 'dob', ['status' => 'INVALID_EVENT']);
            }
            if (isset($s['events'][$eventId])) {
                return $this->record($s, 'dob', ['status' => 'DUPLICATE_EVENT']);
            }
            $version = $event['dob_version'] ?? null;
            if (!is_int($version) || $version <= $s['babies'][$babyId]['dob_version']) {
                return $this->record($s, 'dob', ['status' => 'STALE_VERSION']);
            }
            if (!$this->validBirthDate($event['birth_date'] ?? '')) {
                return $this->record($s, 'dob', ['status' => 'INVALID_DOB']);
            }
            $s['babies'][$babyId]['birth_date'] = $event['birth_date'];
            $s['babies'][$babyId]['dob_version'] = $version;
            $s['babies'][$babyId]['dob_approved'] = true;
            $s['events'][$eventId] = $event;
            // Existing expiry is not rewritten. Whether it should change is an open policy.
            return $this->record($s, 'dob', ['status' => 'DOB_APPROVED', 'baby_id' => $babyId,
                'existing_issue_policy' => isset($s['issues'][$this->issueKey($babyId)]) ? 'PENDING_POLICY' : null]);
        });
    }

    public function validate(string $code, array $cart): array
    {
        return $this->repository->transaction(function (array &$s) use ($code, $cart): array {
            return $this->record($s, 'validate', $this->checkCoupon($s, $code, $cart));
        });
    }

    public function useCoupon(array $event, array $cart): array
    {
        return $this->repository->transaction(function (array &$s) use ($event, $cart): array {
            $id = $event['event_id'] ?? '';
            if (!is_string($id) || !$this->isDemoId($id, 'EV-DEMO-') ||
                !is_string($event['order_id'] ?? null) || !$this->isDemoId($event['order_id'], 'O-DEMO-')) {
                return $this->record($s, 'use', ['status' => 'INVALID_EVENT']);
            }
            if (isset($s['events'][$id])) {
                return $this->record($s, 'use', ['status' => 'DUPLICATE_EVENT']);
            }
            if (($event['type'] ?? '') !== 'SimulatedRedemptionSucceeded') {
                // Paid, Completed, Cancelled and Refunded are not automatic redemption policies.
                $s['events'][$id] = $event;
                return $this->record($s, 'use', ['status' => 'PENDING_POLICY', 'reason' => 'ORDER_USAGE_MAPPING_UNCONFIRMED', 'event' => $event]);
            }
            if (($event['customer_id'] ?? '') !== ($cart['customer_id'] ?? null)) {
                return $this->record($s, 'use', ['status' => 'WRONG_OWNER']);
            }
            $check = $this->checkCoupon($s, (string) ($event['code'] ?? ''), $cart);
            if ($check['status'] !== 'VALID') {
                return $this->record($s, 'use', $check);
            }
            foreach ($s['issues'] as $other) {
                if ($other['used_order_id'] === $event['order_id']) {
                    return $this->record($s, 'use', ['status' => 'ONE_COUPON_PER_ORDER']);
                }
            }
            foreach ($s['issues'] as &$issue) {
                if ($issue['coupon_id'] === $check['coupon_id']) {
                    $issue['used_order_id'] = $event['order_id'];
                    $issue['status'] = 'SIMULATED_USED';
                }
            }
            unset($issue);
            $s['events'][$id] = $event;
            $s['orders'][$event['order_id']] = ['order_id' => $event['order_id'],
                'customer_id' => $cart['customer_id'], 'coupon_id' => $check['coupon_id'],
                'order_status' => 'SIMULATED', 'payment_status' => 'NO_REAL_PAYMENT',
                'lines' => $cart['lines'], 'shipping_vnd' => $cart['shipping_vnd'], 'pricing' => $check];
            return $this->record($s, 'use', ['status' => 'SIMULATED_USED', 'order_id' => $event['order_id']] + $check);
        });
    }

    private function eligibility(array $s, string $babyId): array
    {
        $baby = $s['babies'][$babyId] ?? null;
        if (!$baby || !$this->validBirthDate($baby['birth_date'] ?? '') || ($baby['dob_approved'] ?? false) !== true ||
            !is_int($baby['dob_version'] ?? null) || $baby['dob_version'] < 1) {
            return ['status' => 'INVALID_DOB'];
        }
        if (!$this->hasConsent($s, $baby['customer_id'])) {
            return ['status' => 'NO_CONSENT'];
        }
        $zone = $this->clock->now()->getTimezone();
        $dob = new \DateTimeImmutable($baby['birth_date'], $zone);
        $targetMonth = $dob->modify('first day of this month')->modify('+6 months');
        $day = (int) $dob->format('d');
        if ($day > (int) $targetMonth->format('t')) {
            return ['status' => 'PENDING_POLICY', 'reason' => 'DOB_DAY_29_31'];
        }
        $milestone = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('m'), $day);
        $expiry = $targetMonth->modify('first day of next month');
        $now = $this->clock->now();
        if ($now >= $expiry) {
            return ['status' => 'OUTSIDE_CAMPAIGN_MONTH'];
        }
        if ($now < $milestone) {
            return ['status' => 'NOT_DUE'];
        }
        $registered = $this->parseTimestamp($baby['registered_at'] ?? '');
        $consentAt = $this->parseTimestamp($s['consents'][$baby['customer_id']]['changed_at'] ?? '');
        if (!$registered || !$consentAt || $registered > $now || $consentAt > $now) {
            return ['status' => 'INVALID_DATA', 'reason' => 'REGISTRATION_OR_CONSENT_TIME'];
        }
        // Re-consent timing and the registration anchor are trial conventions, never production defaults.
        $effectiveRegistration = $registered > $consentAt ? $registered : $consentAt;
        if ($effectiveRegistration->format('Y-m-d') > $milestone->format('Y-m-d')) {
            if (($this->config['late_registration_policy'] ?? '') !== 'SIMULATION_CALENDAR_NEXT_DAY') {
                return ['status' => 'PENDING_POLICY', 'reason' => 'LATE_REGISTRATION_CLOCK_AND_ANCHOR'];
            }
            $due = $effectiveRegistration->modify('+1 day')->setTime(0, 0);
            if ($due >= $expiry) {
                return ['status' => 'OUTSIDE_CAMPAIGN_MONTH'];
            }
            if ($now < $due) {
                return ['status' => 'WAIT_NEXT_DAY'];
            }
        }
        return ['status' => 'ELIGIBLE', 'expires_at_exclusive' => $expiry->format('Y-m-d H:i:s')];
    }

    private function checkCoupon(array $s, string $code, array $cart): array
    {
        $issue = null;
        foreach ($s['issues'] as $candidate) {
            if (hash_equals($candidate['code'], $code)) {
                $issue = $candidate;
                break;
            }
        }
        if (!$issue) { return ['status' => 'NOT_FOUND']; }
        if (($cart['customer_id'] ?? '') !== $issue['customer_id']) { return ['status' => 'WRONG_OWNER']; }
        if ($this->timestamp() < $issue['issued_at']) { return ['status' => 'NOT_YET_ISSUED']; }
        if ($this->timestamp() >= $issue['expires_at_exclusive']) { return ['status' => 'EXPIRED']; }
        if ($issue['used_order_id'] !== null) { return ['status' => 'ALREADY_USED']; }
        if (!isset($cart['coupon_ids']) || !is_array($cart['coupon_ids'])) { return ['status' => 'INVALID_DATA']; }
        if (count($cart['coupon_ids']) > 1 ||
            (count($cart['coupon_ids']) === 1 && $cart['coupon_ids'][0] !== $issue['coupon_id'])) {
            return ['status' => 'ONE_COUPON_PER_ORDER'];
        }
        if (!isset($cart['lines']) || !is_array($cart['lines']) || !$cart['lines'] ||
            !is_int($cart['shipping_vnd'] ?? null) || $cart['shipping_vnd'] < 0 || $cart['shipping_vnd'] > 100000000) {
            return ['status' => 'INVALID_DATA'];
        }
        $subtotal = 0;
        $eligible = 0;
        $breakdown = [];
        foreach ($cart['lines'] as $line) {
            $product = $s['products'][$line['sku'] ?? ''] ?? null;
            if (!$product || !is_int($line['quantity'] ?? null) || $line['quantity'] < 1 || $line['quantity'] > 1000 ||
                !is_int($product['unit_price_vnd'] ?? null) || $product['unit_price_vnd'] < 0 || $product['unit_price_vnd'] > 100000000) {
                return ['status' => 'INVALID_DATA'];
            }
            if (($product['eligible'] ?? null) === null) {
                return ['status' => 'PENDING_POLICY', 'reason' => 'SKU_OR_VARIATION_ELIGIBILITY_UNCONFIRMED'];
            }
            $amount = $product['unit_price_vnd'] * $line['quantity'];
            // Category alone cannot opt a product in; food is always excluded.
            $allowed = $product['eligible'] === true && $product['category'] === 'weaning-gear';
            $subtotal += $amount;
            $eligible += $allowed ? $amount : 0;
            $breakdown[] = ['sku' => $product['sku'], 'subtotal_vnd' => $amount, 'eligible' => $allowed];
        }
        if ($eligible === 0) { return ['status' => 'NO_ELIGIBLE_ITEMS']; }
        $policy = $this->config['pricing_policy'];
        if (!in_array($policy, ['SIMULATION_BYPASS_MIN_CAP', 'SIMULATION_EXPLICIT_MIN_CAP'], true)) {
            return ['status' => 'PENDING_POLICY', 'reason' => 'MINIMUM_CAP_TAX_ROUNDING_UNCONFIRMED'];
        }
        $minimum = 0;
        $cap = null;
        if ($policy === 'SIMULATION_EXPLICIT_MIN_CAP') {
            $minimum = $this->config['simulation_min_eligible_vnd'];
            $cap = $this->config['simulation_cap_vnd'];
            if (!is_int($minimum) || $minimum < 0 || !is_int($cap) || $cap < 0) {
                return ['status' => 'PENDING_POLICY', 'reason' => 'INVALID_SIMULATION_PRICING'];
            }
        }
        if ($eligible < $minimum) { return ['status' => 'MINIMUM_NOT_MET']; }
        $numerator = $eligible * 15;
        if ($numerator % 100 !== 0 && ($this->config['simulation_rounding'] ?? '') !== 'SIMULATION_HALF_UP_VND') {
            return ['status' => 'PENDING_POLICY', 'reason' => 'ROUNDING_UNCONFIRMED'];
        }
        $discount = intdiv($numerator + 50, 100);
        if ($cap !== null) { $discount = min($discount, $cap); }
        return ['status' => 'VALID', 'coupon_id' => $issue['coupon_id'], 'subtotal_vnd' => $subtotal,
            'eligible_subtotal_vnd' => $eligible, 'discount_vnd' => $discount,
            'shipping_vnd' => $cart['shipping_vnd'], 'total_vnd' => $subtotal - $discount + $cart['shipping_vnd'],
            'lines' => $breakdown, 'pricing_policy' => $policy, 'tax_policy' => 'SIMULATION_NO_TAX'];
    }

    private function hasConsent(array $s, string $customerId): bool
    {
        return ($s['consents'][$customerId]['marketing_opt_in'] ?? false) === true;
    }

    private function issueKey(string $babyId): string { return $this->config['campaign_id'] . ':' . $babyId; }
    private function timestamp(): string { return $this->clock->now()->format('Y-m-d H:i:s'); }
    private function isDemoId(string $value, string $prefix): bool { return strpos($value, $prefix) === 0 && strlen($value) > strlen($prefix); }

    private function parseTimestamp(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $this->clock->now()->getTimezone());
        return $date && $date->format('Y-m-d H:i:s') === $value ? $date : null;
    }

    private function validBirthDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->clock->now()->getTimezone());
        return $date && $date->format('Y-m-d') === $value && $date->format('Y-m-d') <= $this->clock->now()->format('Y-m-d');
    }

    private function findIssue(array $s, string $couponId): ?array
    {
        foreach ($s['issues'] as $issue) {
            if ($issue['coupon_id'] === $couponId) { return $issue; }
        }
        return null;
    }

    private function record(array &$s, string $action, array $result): array
    {
        $result['label'] = 'SIMULATION ONLY';
        $s['logs'][] = ['sequence' => count($s['logs']) + 1, 'at' => $this->timestamp(),
            'timezone' => $this->config['timezone'], 'action' => $action, 'result' => $result];
        return $result;
    }
}
