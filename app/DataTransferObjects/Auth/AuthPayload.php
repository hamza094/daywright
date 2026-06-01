<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

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
            // Return plain data — resource resolution belongs in the controller/response layer
            'user' => $this->user,
        ];

        if ($this->accessToken !== null) {
            $data['access_token'] = $this->accessToken;
        }

        return $data;
    }
}
