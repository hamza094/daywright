<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\Http\Resources\Api\V1\User\AuthenticatedUserResource;
use App\Models\User;

final class AuthPayload
{
    public function __construct(
        public User $user,
        public ?string $accessToken = null,
    ) {}

    /**
     * Convert to array suitable for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'user' => (new AuthenticatedUserResource($this->user))->resolve(),
        ];

        if ($this->accessToken !== null) {
            $data['access_token'] = $this->accessToken;
        }

        return $data;
    }
}
