<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserLogin
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly ?string $ip = null,
    ) {}
}
