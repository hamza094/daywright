<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

use Illuminate\Support\Arr;

final readonly class TaskUpdateData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        private array $attributes,
        public ?int $version = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            attributes: Arr::only($payload, ['title', 'description', 'due_at', 'status_id', 'notified', 'notify_sent']),
            version: isset($payload['version']) && is_numeric($payload['version']) ? (int) $payload['version'] : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    public function version(): ?int
    {
        return $this->version;
    }

    /**
     * Check if the request has only version (no business changes).
     * Version alone is not considered an edit.
     */
    public function isVersionOnly(): bool
    {
        return $this->attributes === [];
    }

    public function hasDueAt(): bool
    {
        return array_key_exists('due_at', $this->attributes);
    }

    public function dueAt(): ?string
    {
        $dueAt = $this->attributes['due_at'] ?? null;

        return is_string($dueAt) ? $dueAt : null;
    }

    public function hasNotified(): bool
    {
        return array_key_exists('notified', $this->attributes);
    }

    public function notified(): ?string
    {
        $notified = $this->attributes['notified'] ?? null;

        return is_string($notified) ? $notified : null;
    }

    public function hasStatusUpdate(): bool
    {
        return array_key_exists('status_id', $this->attributes);
    }

    public function statusId(): ?int
    {
        $statusId = $this->attributes['status_id'] ?? null;

        return is_numeric($statusId) ? (int) $statusId : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesWithoutStatus(): array
    {
        return Arr::except($this->attributes, ['status_id']);
    }

    public function withNotificationReset(): self
    {
        return new self([
            ...$this->attributes,
            'notify_sent' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
