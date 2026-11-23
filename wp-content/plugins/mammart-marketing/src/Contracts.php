<?php
declare(strict_types=1);

namespace MamMart\Marketing;

interface Clock
{
    public function now(): \DateTimeImmutable;
}

interface Repository
{
    /** Execute a read/modify/write operation atomically. The callback receives state by reference. */
    public function transaction(callable $operation): array;
    public function snapshot(): array;
}

interface MailAdapter
{
    /** Delivery acceptance is not evidence of receipt or opening. */
    public function send(array $message): array;
}

final class FakeMailer implements MailAdapter
{
    private bool $fail;

    public function __construct(bool $fail = false)
    {
        $this->fail = $fail;
    }

    public function send(array $message): array
    {
        // No network, wp_mail(), SMTP, address lookup, or real email delivery.
        return ['status' => $this->fail ? 'FAILED' : 'SIMULATED_SENT',
            'error_code' => $this->fail ? 'SIMULATED_MAIL_ERROR' : null,
            'transport' => 'fake', 'coupon_code' => $message['coupon_code']];
    }
}
