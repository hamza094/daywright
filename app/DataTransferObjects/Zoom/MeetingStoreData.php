<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class MeetingStoreData
{
    public function __construct(
        public string $topic,
        public string $agenda,
        public int $duration,
        public string $startTime,
        public string $timezone,
        public string $password,
        public bool $joinBeforeHost,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            topic: $validated['topic'],
            agenda: $validated['agenda'],
            duration: (int) $validated['duration'],
            startTime: $validated['start_time'],
            timezone: $validated['timezone'],
            password: $validated['password'],
            joinBeforeHost: (bool) $validated['join_before_host'],
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'topic' => $this->topic,
            'agenda' => $this->agenda,
            'duration' => $this->duration,
            'start_time' => $this->startTime,
            'timezone' => $this->timezone,
            'password' => $this->password,
            'join_before_host' => $this->joinBeforeHost,
        ];
    }
}
