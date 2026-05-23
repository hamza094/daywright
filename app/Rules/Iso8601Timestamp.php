<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;

final class Iso8601Timestamp implements ValidationRule
{
    private const ISO_8601_PATTERN = '/^(?<date>\d{4}-\d{2}-\d{2})T(?<time>\d{2}:\d{2}:\d{2})(?:\.(?<fraction>\d{1,6}))?(?<timezone>Z|[+-]\d{2}:\d{2})$/';

    public static function normalizeToUtc(string $value): ?string
    {
        $dateTime = self::parse($value);

        if ($dateTime === null) {
            return null;
        }

        return $dateTime->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::parse($value) === null) {
            $fail('The :attribute must be a valid ISO 8601 timestamp with a timezone offset.');
        }
    }

    private static function parse(string $value): ?DateTimeImmutable
    {
        // @phpstan-ignore-next-line - using preg_match for strict ISO pattern matching
        $matched = preg_match(self::ISO_8601_PATTERN, $value, $matches);
        if ($matched !== 1) {
            return null;
        }

        $timezone = $matches['timezone'] === 'Z' ? '+00:00' : $matches['timezone'];
        $fraction = $matches['fraction'];

        if ($fraction !== '') {
            $normalizedValue = $matches['date'].'T'.$matches['time'].'.'.mb_str_pad($fraction, 6, '0').$timezone;
            $format = '!Y-m-d\TH:i:s.uP';
        } else {
            $normalizedValue = $matches['date'].'T'.$matches['time'].$timezone;
            $format = '!Y-m-d\TH:i:sP';
        }

        $dateTime = DateTimeImmutable::createFromFormat($format, $normalizedValue);
        $errors = DateTimeImmutable::getLastErrors();

        if ($dateTime === false) {
            return null;
        }

        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $dateTime;
    }
}
