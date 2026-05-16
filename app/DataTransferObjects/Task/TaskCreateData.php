<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Task;

use Illuminate\Support\Arr;

final readonly class TaskCreateData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private array $attributes) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            attributes: Arr::only($payload, ['title', 'description', 'due_at', 'notified', 'status_id']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toCreateAttributes(int $userId): array
    {
        return $this->attributes + ['user_id' => $userId];
    }
}
