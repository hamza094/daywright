<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use App\Http\Resources\Api\V1\User\AuthenticatedUserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

#[SchemaName('AuthenticatedToken')]
class AuthenticatedTokenResource extends JsonResource
{
    public function __construct(
        private readonly User $user,
        private readonly string $accessToken,
    ) {
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
            /**
             * Authenticated user profile for the issued token.
             */
            'user' => new AuthenticatedUserResource($this->user),
            /**
             * Newly issued personal access token.
             *
             * @example 1|wKfQJcB0h2M7tokenExample
             */
            'access_token' => $this->accessToken,
        ];
    }
}
