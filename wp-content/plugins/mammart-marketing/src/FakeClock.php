<?php
declare(strict_types=1);

namespace MamMart\Marketing;

final class FakeClock implements Clock
{
    private \DateTimeImmutable $time;

    public function __construct(string $time, string $timezone = 'Asia/Ho_Chi_Minh')
    {
        $zone = new \DateTimeZone($timezone);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $time, $zone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors && ($errors['warning_count'] || $errors['error_count'])) ||
            $parsed->format('Y-m-d H:i:s') !== $time) {
            throw new \InvalidArgumentException('Invalid simulated clock; use Y-m-d H:i:s.');
        }
        $this->time = $parsed;
    }

    public function now(): \DateTimeImmutable
    {
        return $this->time;
    }
}
