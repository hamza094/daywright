<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use Carbon\Carbon;
use Throwable;

final readonly class Meeting
{
    public function __construct(
        public int $meeting_id,
        public string $topic,
        public string $agenda,
        public string $created_at,
        public int $duration,
        public string $start_time,
        public string $start_url,
        public string $join_url,
        public string $status,
        public string $timezone,
        public string $password,
        public bool $join_before_host,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): static
    {
        return new self(
            meeting_id: self::intValue($response, 'id'),
            topic: self::stringValue($response, 'topic'),
            agenda: self::stringValue($response, 'agenda'),
            created_at: self::parseUtcDateTime($response['created_at'] ?? null),
            duration: self::intValue($response, 'duration'),
            start_time: self::parseUtcDateTime($response['start_time'] ?? null),
            start_url: self::stringValue($response, 'start_url'),
            join_url: self::stringValue($response, 'join_url'),
            status: self::stringValue($response, 'status'),
            timezone: self::stringValue($response, 'timezone'),
            password: self::stringValue($response, 'password'),
            join_before_host: self::boolValue($response, 'join_before_host'),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function intValue(array $response, string $key): int
    {
        return isset($response[$key]) ? (int) $response[$key] : 0;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function stringValue(array $response, string $key): string
    {
        return (string) ($response[$key] ?? '');
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function boolValue(array $response, string $key): bool
    {
        return isset($response[$key]) ? (bool) $response[$key] : false;
    }

    private static function parseUtcDateTime(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->utc()->toDateTimeString();
        } catch (Throwable) {
            return '';
        }
    }
}
