<?php

declare(strict_types=1);

namespace Tests\Support\Zoom;

class ZoomWebhookPayloadFactory
{
    public static function meetingStartedPayload(array $overrides = []): array
    {
        $defaultPayload = [
            'event' => 'meeting.started',
            'payload' => [
                'account_id' => 'HsTKzp8YTIWubRtgF7L_2w',
                'object' => [
                    'uuid' => 'aAcZTflfSPqz6TsDJ/lDKA==',
                    'id' => 813,
                    'topic' => 'Test Meeting',
                    'start_time' => '2024-06-24T12:00:00Z',
                    'timezone' => 'UTC',
                    'duration' => 30,
                    'type' => 2,
                ],
                'time_stamp' => 1719229788513,
            ],
            'event_ts' => 1719229788513,
        ];

        return self::mergeOverrides($defaultPayload, $overrides);
    }

    public static function meetingEndedPayload(array $overrides = []): array
    {
        $defaultPayload = [
            'event' => 'meeting.ended',
            'payload' => [
                'account_id' => 'HsTKzp8YTIWubRtgF7L_2w',
                'object' => [
                    'uuid' => 'aAcZTflfSPqz6TsDJ/lDKA==',
                    'id' => 813,
                    'topic' => 'Test Meeting',
                    'start_time' => '2024-07-30T11:00:00Z',
                    'end_time' => '2024-07-30T11:30:00Z',
                    'timezone' => 'UTC',
                    'duration' => 30,
                    'type' => 2,
                ],
                'time_stamp' => 1719229788513,
            ],
            'event_ts' => 1719229788513,
        ];

        return self::mergeOverrides($defaultPayload, $overrides);
    }

    public static function meetingUpdatedPayload(array $overrides = []): array
    {
        $defaultPayload = [
            'event' => 'meeting.updated',
            'payload' => [
                'account_id' => 'HsTKzp8YTIWubRtgF7L_2w',
                'operator' => 'test_operator@example.com',
                'operator_id' => 'tWcCtVTiTum7Ctdx1p0GWQ',
                'object' => [
                    'uuid' => 'aAcZTflfSPqz6TsDJ/lDKA==',
                    'id' => 813,
                    'topic' => 'Updated Topic',
                ],
                'old_object' => [
                    'uuid' => 'aAcZTflfSPqz6TsDJ/lDKA==',
                    'id' => 813,
                    'topic' => 'Original Topic',
                ],
                'time_stamp' => 1719229788513,
            ],
            'event_ts' => 1719229788513,
        ];

        return self::mergeOverrides($defaultPayload, $overrides);
    }

    public static function meetingDeletedPayload(array $overrides = []): array
    {
        $defaultPayload = [
            'event' => 'meeting.deleted',
            'payload' => [
                'account_id' => 'HsTKzp8YTIWubRtgF7L_2w',
                'operator' => 'test_operator@example.com',
                'operator_id' => 'tWcCtVTiTum7Ctdx1p0GWQ',
                'object' => [
                    'uuid' => 'aAcZTflfSPqz6TsDJ/lDKA==',
                    'id' => 813,
                    'type' => 3,
                ],
                'time_stamp' => 1719229788513,
            ],
            'event_ts' => 1719229788513,
        ];

        return self::mergeOverrides($defaultPayload, $overrides);
    }

    public static function endpointValidationPayload(string $plainToken): array
    {
        return [
            'event' => 'endpoint.url_validation',
            'payload' => [
                'plainToken' => $plainToken,
            ],
        ];
    }

    private static function mergeOverrides(array $default, array $overrides): array
    {
        return array_replace_recursive($default, $overrides);
    }
}
