<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

final class RegisterUserService
{
    public function __construct(private readonly LoginUserService $loginUserService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        $data['password'] = bcrypt($data['password']);

        $user = User::create($data);

        event(new Registered($user));

        $this->loginUserService->dispatchTimezoneIfNeeded($user);

        return $user;
    }
}
