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
        if (! is_string($meeting->sync_payload) || trim($meeting->sync_payload) === '') {
            return null;
        }

        try {
            $payload = json_decode($meeting->sync_payload, true);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        // Every field we asked Zoom to change must appear with the expected value.
        foreach ($payload as $field => $expectedValue) {
            if (! is_string($field)
                || ! in_array($field, self::ALLOWED_FIELDS, true)
                || ! array_key_exists($field, $webhookChanges)
                || ! $this->fieldValuesMatch($field, $expectedValue, $webhookChanges[$field])) {
                return null;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function zoomHasRequestedFields(array $payload, ZoomMeeting $meeting): bool
    {
        foreach ($payload as $field => $expected) {
            if (! is_string($field)
                || ! in_array($field, self::ALLOWED_FIELDS, true)
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

    private function fieldValuesMatch(string $field, mixed $expected, mixed $actual): bool
    {
        // Zoom may send times, numbers, and booleans in different formats; compare their meaning.
        if ($field === 'start_time') {
            $expectedTime = $this->normalizedTime($expected);

            return $expectedTime !== null && $expectedTime === $this->normalizedTime($actual);
        }

        if ($field === 'duration') {
            return is_numeric($expected) && is_numeric($actual) && (int) $expected === (int) $actual;
        }

        if ($field === 'join_before_host') {
            $expectedBoolean = filter_var($expected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $expectedBoolean !== null
                && $expectedBoolean === filter_var($actual, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

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
            $currentIso = $current ? Carbon::parse($current)->toISOString() : null;
            $valueIso = $value ? Carbon::parse($value)->toISOString() : null;

            return $currentIso !== $valueIso;
        }

        if (is_bool($current) || is_bool($value)) {
            return (bool) $value !== (bool) $current;
        }

        if (is_numeric($current) && is_numeric($value)) {
            return (int) $value !== (int) $current;
        }

        return $value !== $current;
    }
}
