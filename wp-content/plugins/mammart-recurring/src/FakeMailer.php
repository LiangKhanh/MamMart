<?php

declare(strict_types=1);

namespace Mammart\Recurring;

/** Simulates delivery without sending anything over the network. */
final class FakeMailer
{
    private int $attempts = 0;

    public function __construct(private readonly ?string $failureReason = null)
    {
    }

    public function send(): ?string
    {
        $this->attempts++;
        return $this->failureReason;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }
}
