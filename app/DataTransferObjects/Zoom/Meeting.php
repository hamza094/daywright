<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
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
            meeting_id: self::requiredInt($response, 'id'),
            topic: self::requiredString($response, 'topic'),
            agenda: self::optionalString($response, 'agenda'),
            created_at: self::requiredUtcDateTime($response, 'created_at'),
            duration: self::requiredInt($response, 'duration'),
            start_time: self::requiredUtcDateTime($response, 'start_time'),
            start_url: self::requiredString($response, 'start_url'),
            join_url: self::requiredString($response, 'join_url'),
            status: self::requiredString($response, 'status'),
            timezone: self::requiredString($response, 'timezone'),
            password: self::requiredString($response, 'password'),
            join_before_host: self::requiredBool($response, 'join_before_host'),
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function requiredInt(array $response, string $key): int
    {
        $value = $response[$key] ?? null;

        if (! is_int($value) && ! is_numeric($value)) {
            self::throwMalformedResponse($key);
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function requiredString(array $response, string $key): string
    {
        $value = $response[$key] ?? null;

        if (! is_string($value)) {
            self::throwMalformedResponse($key);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function optionalString(array $response, string $key): string
    {
        $value = $response[$key] ?? null;

        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            self::throwMalformedResponse($key);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function requiredBool(array $response, string $key): bool
    {
        if (! array_key_exists($key, $response) || ! is_bool($response[$key])) {
            self::throwMalformedResponse($key);
        }

        return $response[$key];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function requiredUtcDateTime(array $response, string $key): string
    {
        $value = $response[$key] ?? null;

        if (! is_string($value) || $value === '') {
            self::throwMalformedResponse($key);
        }

        try {
            return Carbon::parse($value)->utc()->toDateTimeString();
        } catch (Throwable) {
            self::throwMalformedResponse($key);
        }
    }

    private static function throwMalformedResponse(string $key): never
    {
        throw new ZoomExternalFailureException("Zoom meeting response is missing or invalid for [{$key}].");
    }
}
