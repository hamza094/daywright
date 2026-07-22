<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Meeting;

final readonly class MeetingNotificationData
{
    /**
     * @param  array<string, mixed>  $notifier
     */
    public function __construct(
        public string $projectName,
        public string $projectSlug,
        public string $meetingTopic,
        public string $meetingTimezone,
        public ?string $meetingJoinUrl,
        public string $startTime,
        public ?string $endTime,
        public array $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            projectName: (string) ($payload['project_name'] ?? ''),
            projectSlug: (string) ($payload['project_slug'] ?? ''),
            meetingTopic: (string) ($payload['meeting_topic'] ?? ''),
            meetingTimezone: (string) ($payload['meeting_timezone'] ?? ''),
            meetingJoinUrl: isset($payload['meeting_join_url']) ? (string) $payload['meeting_join_url'] : null,
            startTime: (string) ($payload['start_time'] ?? ''),
            endTime: isset($payload['end_time']) ? (string) $payload['end_time'] : null,
            notifier: (array) ($payload['notifier'] ?? []),
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'project_name' => $this->projectName,
            'project_slug' => $this->projectSlug,
            'meeting_topic' => $this->meetingTopic,
            'meeting_timezone' => $this->meetingTimezone,
            'meeting_join_url' => $this->meetingJoinUrl,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'notifier' => $this->notifier,
        ];
    }
}
