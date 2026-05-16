<?php

declare(strict_types=1);

namespace App\Services\User;

use App\DataTransferObjects\User\UpdateUserData;
use App\Events\PasswordUpdateEvent;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @return Collection<int, User>
     */
    public function allUsers(): Collection
    {
        return User::query()->get();
    }

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

            if ($data->hasPasswordUpdate()) {
                $this->updatePassword($user, $data->password);
            }
        });

        $user->refresh();

        return $this->loadProfile($user);
    }

    public function deleteUser(User $user): void
    {
        $user->delete();
    }

    public function updatePassword(User $user, string $password): void
    {
        try {
            $user->password = Hash::make($password);
            $user->save();
            event(new PasswordUpdateEvent($user, now()->toDayDateTimeString()));
        } catch (Exception) {
            throw ValidationException::withMessages([
                'password' => 'Unable to update password. Please try again later.',
            ]);
        }
    }
}
