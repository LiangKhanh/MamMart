<?php

declare(strict_types=1);

namespace Mammart\Recurring;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class SandboxDate
{
    public static function parse(mixed $value, string $field): DateTimeImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))?$/D', $value)) {
            $format = strlen($value) === 10 ? '!Y-m-d' : '!Y-m-d\TH:i:sP';
            $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && substr($value, 0, 4) !== '0000') {
                return $date->setTimezone(new DateTimeZone('UTC'));
            }
        }

        throw new InvalidArgumentException($field . ' must be a valid YYYY-MM-DD date or ISO-8601 timestamp.');
    }
}
