<?php

declare(strict_types=1);

namespace App\Services\User;

use App\DataTransferObjects\User\PasswordUpdateData;
use App\DataTransferObjects\User\UpdateUserData;
use App\Events\PasswordUpdateEvent;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function loadAuthenticatedUser(User $user): User
    {
        $user->loadMissing('twoFactorAuth');

        return $user;
    }

    public function loadProfile(User $user): User
    {
        $user->loadMissing('info');

        return $user;
    }

    public function updateUser(User $user, UpdateUserData $data): User
    {
        DB::transaction(function () use ($user, $data): void {
            $user->update($data->userAttributes());

            $user->info?->update($data->infoAttributes());
        });

        $user->refresh();

        return $this->loadProfile($user);
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    public function updatePassword(User $user, PasswordUpdateData $data): void
    {
        try {
            $user->password = Hash::make($data->password);
            $user->save();

            // Invalidate other web sessions for security
            Auth::guard('web')->logoutOtherDevices($data->password);

            event(new PasswordUpdateEvent($user, now()->toDayDateTimeString()));
        } catch (Exception) {
            throw ValidationException::withMessages([
                'password' => 'Unable to update password. Please try again later.',
            ]);
        }
    }
}
