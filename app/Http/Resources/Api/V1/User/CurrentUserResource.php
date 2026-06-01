<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\FeatureFlagsResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

#[SchemaName('CurrentUser')]
class CurrentUserResource extends JsonResource
{
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
             * Fully authenticated user payload for the current account.
             */
            'user' => new AuthenticatedUserResource($this->resource),
            /**
             * Client-visible feature flags for the authenticated user.
             */
            'features' => new FeatureFlagsResource($this->resource),
        ];
    }
}
