<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Collection;

class StatusService
{
    /**
     * @return Collection<int, TaskStatus>
     */
    public function all(): Collection
    {
        return TaskStatus::query()->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TaskStatus
    {
        return TaskStatus::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TaskStatus $status, array $attributes): TaskStatus
    {
        $status->update($attributes);

        return $status;
    }

    public function delete(TaskStatus $status): void
    {
        $status->delete();
    }
}
