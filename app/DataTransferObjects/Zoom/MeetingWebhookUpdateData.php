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
        'duration',
        'password',
        'start_time',
        'timezone',
        'uuid',
        'join_url',
        'start_url',
        'agenda',
    ];

    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public int|string $meetingId,
        public array $changes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload): self
    {
        return new self(
            meetingId: $payload['id'] ?? 0,
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

        // Handle nested settings.join_before_host
        if (isset($changes['settings']['join_before_host'])) {
            $booleanValue = filter_var($changes['settings']['join_before_host'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($booleanValue !== null) {
                $normalized['join_before_host'] = $booleanValue;
            }
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
            case 'password':
            case 'join_url':
            case 'start_url':
            case 'timezone':
            case 'uuid':
            case 'agenda':
                if (is_string($value)) {
                    $normalized[$field] = $value;
                }

                return;

            case 'duration':
                if (is_numeric($value)) {
                    $normalized[$field] = (int) $value;
                }

                return;

            case 'start_time':
                $normalizedStartTime = self::normalizeStartTime($value);

                if ($normalizedStartTime !== null) {
                    $normalized[$field] = $normalizedStartTime;
                }

                return;

            default:
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
