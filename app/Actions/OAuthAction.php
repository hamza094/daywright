<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OAuthProvider;
use App\Models\User;
use App\Models\UserSocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Two\User as SocialiteUser;

class OAuthAction
{
    public function resolveUserFromProvider(SocialiteUser $oAuthUser, OAuthProvider $provider): User
    {
        return DB::transaction(function () use ($oAuthUser, $provider): User {
            $providerUserId = (string) $oAuthUser->getId();

            $user = $this->resolveExistingUser($oAuthUser, $provider, $providerUserId);

            if ($user === null) {
                $user = $this->createUserFromProvider($oAuthUser);
            } else {
                $this->updateUserFromProvider($user, $oAuthUser);
            }

            $this->syncSocialAccount($user, $oAuthUser, $provider, $providerUserId);

            return $user;
        });
    }

    private function resolveExistingUser(
        SocialiteUser $oAuthUser,
        OAuthProvider $provider,
        string $providerUserId
    ): ?User {
        return $this->findUserByProviderAccount($provider, $providerUserId)
            ?? $this->findUserByEmail($oAuthUser);
    }

    private function findUserByProviderAccount(OAuthProvider $provider, string $providerUserId): ?User
    {
        $linkedAccount = UserSocialAccount::query()
            ->with('user')
            ->where('provider', $provider->value)
            ->where('provider_user_id', $providerUserId)
            ->first();

        return $linkedAccount?->user;
    }

    private function findUserByEmail(SocialiteUser $oAuthUser): ?User
    {
        if (! filled($oAuthUser->getEmail())) {
            return null;
        }

        return User::query()->where('email', $oAuthUser->getEmail())->first();
    }

    private function createUserFromProvider(SocialiteUser $oAuthUser): User
    {
        if (! filled($oAuthUser->getEmail())) {
            throw new InvalidArgumentException('OAuth provider did not return an email address.');
        }

        return User::query()->create([
            'name' => $oAuthUser->getName(),
            'email' => $oAuthUser->getEmail(),
            'password' => Hash::make(Str::random(50)),
            'username' => $this->resolveUsername($oAuthUser),
            'email_verified_at' => Carbon::now(),
            'avatar_path' => $oAuthUser->getAvatar(),
        ]);
    }

    private function updateUserFromProvider(User $user, SocialiteUser $oAuthUser): void
    {
        $user->fill([
            'name' => $user->name ?: $oAuthUser->getName(),
            'username' => $user->username ?: $this->resolveUsername($oAuthUser),
            'avatar_path' => $user->avatar_path ?: $oAuthUser->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
        ]);

        if ($user->isDirty()) {
            $user->save();
        }
    }

    private function syncSocialAccount(
        User $user,
        SocialiteUser $oAuthUser,
        OAuthProvider $provider,
        string $providerUserId
    ): void {
        $user->socialAccounts()->updateOrCreate(
            ['provider' => $provider->value],
            [
                'provider_user_id' => $providerUserId,
                'access_token' => $oAuthUser->token,
                'refresh_token' => $oAuthUser->refreshToken,
                'token_expires_at' => $this->resolveTokenExpiry($oAuthUser),
            ]
        );

        if ($user->relationLoaded('socialAccounts')) {
            $user->unsetRelation('socialAccounts');
        }
    }

    private function resolveTokenExpiry(SocialiteUser $oAuthUser): ?Carbon
    {
        $expiresIn = $oAuthUser->expiresIn ?? null;

        if (! filled($expiresIn)) {
            return null;
        }

        return Carbon::now()->addSeconds((int) $expiresIn);
    }

    private function resolveUsername(SocialiteUser $oAuthUser): ?string
    {
        return $oAuthUser->getNickname() ?? $oAuthUser->nickname;
    }
}
