<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class MeetingUpdateData
{
    public function __construct(
        public ?string $topic = null,
        public ?string $agenda = null,
        public ?int $duration = null,
        public ?string $startTime = null,
        public ?string $timezone = null,
        public ?string $password = null,
        public ?bool $joinBeforeHost = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            topic: $validated['topic'] ?? null,
            agenda: $validated['agenda'] ?? null,
            duration: isset($validated['duration']) ? (int) $validated['duration'] : null,
            startTime: $validated['start_time'] ?? null,
            timezone: $validated['timezone'] ?? null,
            password: $validated['password'] ?? null,
            joinBeforeHost: isset($validated['join_before_host']) ? (bool) $validated['join_before_host'] : null,
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'topic' => $this->topic,
            'agenda' => $this->agenda,
            'duration' => $this->duration,
            'start_time' => $this->startTime,
            'timezone' => $this->timezone,
            'password' => $this->password,
            'join_before_host' => $this->joinBeforeHost,
        ], fn (int|string|bool|null $value): bool => $value !== null);
    }
}
