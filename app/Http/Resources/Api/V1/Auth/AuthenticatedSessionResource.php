<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Http\Resources\Api\V1\User\AuthenticatedUserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

#[SchemaName('AuthenticatedSession')]
class AuthenticatedSessionResource extends JsonResource
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
        ];
    }
}
