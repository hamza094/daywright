<?php

declare(strict_types=1);

namespace App\Services\User;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): User
    {
        DB::transaction(function () use ($user, $data): void {
            $data = collect($data);
            $userKeys = ['name', 'email', 'username', 'timezone'];

            $user->update($data->only($userKeys)->toArray());

            $user->info?->update($data->except(array_merge($userKeys, ['password', 'current_password']))->toArray());

            if ($data->get('password')) {
                $this->updatePassword($user, $data->get('password'));
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
