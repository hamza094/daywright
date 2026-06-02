<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use Carbon\Carbon;
use Throwable;

final readonly class MeetingWebhookUpdateData
{
    /**
     * @var list<string>
     */
    private const array ALLOWED_FIELDS = [
        'topic',
        'agenda',
        'duration',
        'password',
        'join_url',
        'start_url',
        'start_time',
        'join_before_host',
        'timezone',
    ];

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int $meetingId,
        public array $changes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload): self
    {
        return new self(
            meetingId: (int) ($payload['id'] ?? 0),
            changes: self::normalizeChanges($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public static function normalizeChanges(array $changes): array
    {
        $normalized = [];

        foreach (self::ALLOWED_FIELDS as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            self::addNormalizedField($normalized, $field, $changes[$field]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private static function addNormalizedField(array &$normalized, string $field, mixed $value): void
    {
        switch ($field) {
            case 'topic':
            case 'agenda':
            case 'password':
            case 'join_url':
            case 'start_url':
            case 'timezone':
                if (is_string($value)) {
                    $normalized[$field] = $value;
                }

                return;

            case 'duration':
                if (is_numeric($value)) {
                    $normalized[$field] = (int) $value;
                }

                return;

            case 'join_before_host':
                $booleanValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                if ($booleanValue !== null) {
                    $normalized[$field] = $booleanValue;
                }

                return;

            case 'start_time':
                $normalizedStartTime = self::normalizeStartTime($value);

                if ($normalizedStartTime !== null) {
                    $normalized[$field] = $normalizedStartTime;
                }

                return;
        }
    }

    private static function normalizeStartTime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
