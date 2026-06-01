<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DataTransferObjects\Auth\RegisterUserData;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class RegisterUserService
{
    public function __construct(private readonly LoginUserService $loginUserService) {}

    public function register(RegisterUserData $data): User
    {
        $user = User::create($data->toUserAttributes(
            hashedPassword: bcrypt($data->password)
        ));

        event(new Registered($user));

        $this->loginUserService->dispatchTimezoneIfNeeded($user);

        return $user;
    }
}
