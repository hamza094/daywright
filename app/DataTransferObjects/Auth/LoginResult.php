<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Auth;

use App\Models\User;

final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public bool $twoFactor,
    ) {}
}
