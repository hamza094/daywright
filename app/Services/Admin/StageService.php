<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Stage;
use Illuminate\Database\Eloquent\Collection;

class StageService
{
    /**
     * @return Collection<int, Stage>
     */
    public function all(): Collection
    {
        return Stage::query()->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Stage
    {
        return Stage::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Stage $stage, array $attributes): Stage
    {
        $stage->update($attributes);

        return $stage;
    }

    public function delete(Stage $stage): void
    {
        $stage->delete();
    }
}
