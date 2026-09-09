<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Task;

use App\Models\TaskStatus;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;

/**
 * Computed resource for task status index with due notification strategies.
 * This resource does not wrap a single Eloquent model.
 *
 * @property Collection<int, TaskStatus> $statuses
 * @property array<int, string> $dueNotifies
 */
#[SchemaName('TaskStatusIndex')]
class TaskStatusIndexResource extends JsonResource
{
    /**
     * @param  Collection<int, TaskStatus>  $statuses
     * @param  array<int, string>  $dueNotifies
     */
    public function __construct(
        private readonly Collection $statuses,
        private readonly array $dueNotifies,
    ) {
        parent::__construct($statuses);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            /**
             * List of available task statuses.
             */
            'statuses' => TaskStatusResource::collection($this->statuses),
            /**
             * List of supported notification strategies for due dates.
             * Allowed values: 1 Day Before, 2 Hours Before, 15 Minutes Before, 5 Minutes Before.
             */
            'due_notifies' => $this->dueNotifies,
        ];
    }
}
