<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use InvalidArgumentException;

final readonly class MeetingSummary
{
    /**
     * @param  array<string, string>  $trackingFields
     */
    public function __construct(
        public int|string $meetingId,
        public array $trackingFields,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $meetingId = $response['id'] ?? null;

        if (! is_int($meetingId) && ! is_string($meetingId)) {
            throw new InvalidArgumentException('Zoom meeting list item is missing an ID.');
        }

        return new self(
            meetingId: $meetingId,
            trackingFields: self::trackingFields($response),
        );
    }

    public function hasTrackingFields(): bool
    {
        return $this->trackingFields !== [];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, string>
     */
    private static function trackingFields(array $response): array
    {
        $trackingFields = $response['tracking_fields'] ?? [];

        if (! is_array($trackingFields)) {
            return [];
        }

        $normalized = [];

        foreach ($trackingFields as $trackingField) {
            if (! is_array($trackingField)) {
                continue;
            }

            $field = $trackingField['field'] ?? null;
            $value = $trackingField['value'] ?? null;

            if (is_string($field) && is_string($value)) {
                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }
}
