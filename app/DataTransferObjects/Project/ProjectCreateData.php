<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class ProjectCreateData
{
    /**
     * @param  array<int, array{title: string}>  $tasks
     */
    public function __construct(
        public string $name,
        public string $about,
        public int $stageId,
        public ?string $notes,
        private array $tasks,
        private bool $hasNotes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            about: (string) ($payload['about'] ?? ''),
            stageId: (int) ($payload['stage_id'] ?? 0),
            notes: array_key_exists('notes', $payload) ? (string) ($payload['notes'] ?? '') : null,
            tasks: self::normalizeTasks($payload['tasks'] ?? []),
            hasNotes: array_key_exists('notes', $payload),
        );
    }

    /**
     * @return array{name: string, about: string, stage_id: int, notes?: string|null}
     */
    public function projectAttributes(): array
    {
        $attributes = [
            'name' => $this->name,
            'about' => $this->about,
            'stage_id' => $this->stageId,
        ];

        if ($this->hasNotes) {
            $attributes['notes'] = $this->notes;
        }

        return $attributes;
    }

    /**
     * @return array<int, array{title: string}>
     */
    public function starterTasks(): array
    {
        return $this->tasks;
    }

    /**
     * @return array{name: string, about: string, stage_id: int, notes: string|null, tasks: array<int, array{title: string}>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'about' => $this->about,
            'stage_id' => $this->stageId,
            'notes' => $this->notes,
            'tasks' => $this->tasks,
        ];
    }

    /**
     * @return array<int, array{title: string}>
     */
    private static function normalizeTasks(mixed $tasks): array
    {
        /** @phpstan-ignore-next-line */
        return collect($tasks)
            ->map(fn (mixed $t) => is_array($t) ? ($t['title'] ?? null) : (is_object($t) ? ($t->title ?? null) : null))
            ->filter(fn ($title) => is_string($title) && $title !== '')
            ->map(fn ($title) => ['title' => $title])
            ->values()
            ->all();
    }
}
