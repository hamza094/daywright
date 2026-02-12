<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Events\PasswordUpdateEvent;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): void
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
