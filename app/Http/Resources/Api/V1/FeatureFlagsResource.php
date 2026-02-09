<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\FeatureFlag;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Pennant\Feature;

class FeatureFlagsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, bool>
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        if (! $user instanceof User) {
            return $this->defaultFlags();
        }

        return $this->resolveFlags($user);
    }

    /**
     * @return array<string, bool>
     */
    private function defaultFlags(): array
    {
        return [];
    }

    /**
     * @return array<string, bool>
     */
    private function resolveFlags(User $user): array
    {
        $map = collect(FeatureFlag::cases())
            ->mapWithKeys(fn (FeatureFlag $feature) => [
                $feature->key() => Feature::for($user)->active($feature->pennantName()),
            ]);

        if ($user->isAdmin()) {
            return $map->toArray();
        }

        return collect(FeatureFlag::cases())
            ->filter(fn (FeatureFlag $feature) => $feature->visibleToClient() && $map->get($feature->key(), false))
            ->mapWithKeys(fn (FeatureFlag $feature) => [$feature->key() => true])
            ->toArray();
    }
}
