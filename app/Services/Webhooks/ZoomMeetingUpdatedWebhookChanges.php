<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Models\Meeting;
use Carbon\Carbon;
use JsonException;
use Throwable;

use function Safe\json_decode;

/**
 * Keeps webhook fields safe and compares them with the values we asked Zoom to save.
 */
final readonly class ZoomMeetingUpdatedWebhookChanges
{
    public const array ALLOWED_FIELDS = [
        'topic',
        'duration',
        'agenda',
        'start_time',
        'timezone',
        'password',
        'join_before_host',
        'join_url',
    ];

    /**
     * Return the saved request fields only when the webhook contains every requested value.
     *
     * @param  array<string, mixed>  $webhookChanges
     * @return array<string, mixed>|null
     */
    public function matchingOperationFields(Meeting $meeting, array $webhookChanges): ?array
    {
        $payload = $this->decodePayload($meeting->sync_payload);

        if ($payload === null) {
            return null;
        }

        return $this->validateMatchingFields($payload, $webhookChanges);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function zoomHasRequestedFields(array $payload, ZoomMeeting $meeting): bool
    {
        foreach ($payload as $field => $expected) {
            if (! in_array($field, self::ALLOWED_FIELDS, true)
                || ! property_exists($meeting, $field)
                || ! $this->fieldValuesMatch($field, $expected, $meeting->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $operationPayload
     * @param  array<string, mixed>  $webhookChanges
     * @return array<string, mixed>
     */
    public function otherAllowedFields(array $operationPayload, array $webhookChanges): array
    {
        $additional = [];

        // Keep other safe Zoom fields, but never let webhook data set unapproved fields.
        foreach ($webhookChanges as $field => $value) {
            if (in_array($field, self::ALLOWED_FIELDS, true)
                && ! array_key_exists($field, $operationPayload)) {
                $additional[$field] = $value;
            }
        }

        return $additional;
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldsFromZoom(ZoomMeeting $meeting): array
    {
        return [
            'topic' => $meeting->topic,
            'duration' => $meeting->duration,
            'agenda' => $meeting->agenda,
            'start_time' => $meeting->start_time,
            'timezone' => $meeting->timezone,
            'password' => $meeting->password,
            'join_before_host' => $meeting->join_before_host,
            'join_url' => $meeting->join_url,
        ];
    }

    /**
     * @param  array<string, mixed>  $updateData
     */
    public function hasChanges(Meeting $meeting, array $updateData): bool
    {
        foreach ($updateData as $field => $value) {
            if ($this->hasChanged($meeting, $field, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(mixed $syncPayload): ?array
    {
        if (! $this->isValidPayloadString($syncPayload)) {
            return null;
        }

        $payload = $this->decodeJsonPayload($syncPayload);

        if (! $this->isValidPayloadArray($payload)) {
            return null;
        }

        return $payload;
    }

    private function isValidPayloadString(mixed $syncPayload): bool
    {
        return is_string($syncPayload) && trim($syncPayload) !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonPayload(string $syncPayload): ?array
    {
        try {
            $payload = json_decode($syncPayload, true);
        } catch (JsonException) {
            return null;
        }

        return $payload;
    }

    private function isValidPayloadArray(mixed $payload): bool
    {
        return is_array($payload) && $payload !== [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $webhookChanges
     * @return array<string, mixed>|null
     */
    private function validateMatchingFields(array $payload, array $webhookChanges): ?array
    {
        // Every field we asked Zoom to change must appear with the expected value.
        foreach ($payload as $field => $expectedValue) {
            if (! in_array($field, self::ALLOWED_FIELDS, true)
                || ! array_key_exists($field, $webhookChanges)
                || ! $this->fieldValuesMatch($field, $expectedValue, $webhookChanges[$field])) {
                return null;
            }
        }

        return $payload;
    }

    private function fieldValuesMatch(string $field, mixed $expected, mixed $actual): bool
    {
        // Zoom may send times, numbers, and booleans in different formats; compare their meaning.
        return match ($field) {
            'start_time' => $this->timeValuesMatch($expected, $actual),
            'duration' => $this->durationValuesMatch($expected, $actual),
            'join_before_host' => $this->booleanValuesMatch($expected, $actual),
            default => $this->stringValuesMatch($expected, $actual),
        };
    }

    private function timeValuesMatch(mixed $expected, mixed $actual): bool
    {
        $expectedTime = $this->normalizedTime($expected);

        return $expectedTime !== null && $expectedTime === $this->normalizedTime($actual);
    }

    private function durationValuesMatch(mixed $expected, mixed $actual): bool
    {
        return is_numeric($expected) && is_numeric($actual) && (int) $expected === (int) $actual;
    }

    private function booleanValuesMatch(mixed $expected, mixed $actual): bool
    {
        $expectedBoolean = filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $expectedBoolean !== null
            && $expectedBoolean === filter_var($actual, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function stringValuesMatch(mixed $expected, mixed $actual): bool
    {
        return is_string($expected) && is_string($actual) && $expected === $actual;
    }

    private function normalizedTime(mixed $value): ?string
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

    private function hasChanged(Meeting $meeting, string $field, mixed $value): bool
    {
        $current = $meeting->getAttribute($field);

        if ($field === 'start_time') {
            return $this->timeHasChanged($current, $value);
        }

        $isBoolean = is_bool($current) || is_bool($value);
        $isNumeric = is_numeric($current) && is_numeric($value);

        return match (true) {
            $isBoolean => $this->booleanHasChanged($current, $value),
            $isNumeric => $this->numericHasChanged($current, $value),
            default => $this->valueHasChanged($current, $value),
        };
    }

    private function timeHasChanged(mixed $current, mixed $value): bool
    {
        $currentIso = $current ? Carbon::parse($current)->toISOString() : null;
        $valueIso = $value ? Carbon::parse($value)->toISOString() : null;

        return $currentIso !== $valueIso;
    }

    private function booleanHasChanged(mixed $current, mixed $value): bool
    {
        return (bool) $value !== (bool) $current;
    }

    private function numericHasChanged(mixed $current, mixed $value): bool
    {
        return (int) $value !== (int) $current;
    }

    private function valueHasChanged(mixed $current, mixed $value): bool
    {
        return $value !== $current;
    }
}
