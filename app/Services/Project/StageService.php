<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Models\Stage;
use Illuminate\Database\Eloquent\Collection;

class StageService
{
    /**
     * @return Collection<int, Stage>
     */
    public function all(): Collection
    {
        return (new \App\Services\Admin\StageService)->all();
    }
}
