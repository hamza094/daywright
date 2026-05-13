<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\User;

use App\Http\Resources\Api\V1\FeatureFlagsResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class CurrentUserResource extends JsonResource
{
    public function __construct(private readonly User $user)
    {
        parent::__construct($user);
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
            'user' => new AuthenticatedUserResource($this->user),
            'features' => new FeatureFlagsResource($this->user),
        ];
    }
}
