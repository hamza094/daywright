<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class ProjectInsightsQueryData
{
    private const array ALLOWED_SECTIONS = [
        'health',
        'task-health',
        'collaboration',
        'risk',
        'stage',
    ];

    /**
     * @param  array<int, string>  $sections
     */
    public function __construct(
        public array $sections,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $sections = $payload['sections'] ?? [];

        if (! is_array($sections) || $sections === []) {
            return new self(self::ALLOWED_SECTIONS);
        }

        return new self($sections);
    }

    /**
     * Convert the DTO to the exact array shape expected by the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sections' => $this->sections,
        ];
    }
}
