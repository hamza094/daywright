<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

use App\Enums\ProjectStage;
use InvalidArgumentException;

final readonly class ProjectStageUpdateData
{
    public function __construct(
        public int $stageId,
        public ?string $postponedReason,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        if (! isset($payload['stage'])) {
            throw new InvalidArgumentException('stage is required');
        }

        return new self(
            stageId: (int) $payload['stage'],
            postponedReason: array_key_exists('postponed_reason', $payload)
                ? (string) ($payload['postponed_reason'] ?? '')
                : null,
        );
    }

    /**
     * @return array{stage: int, postponed_reason: string|null}
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stageId,
            'postponed_reason' => $this->postponedReason,
        ];
    }

    public function stage(): ProjectStage
    {
        return ProjectStage::from($this->stageId);
    }
}
