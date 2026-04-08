<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OAuthProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class OAuthAction
{
    public function createUpdateUser(SocialiteUser $oAuthUser, OAuthProvider $provider): User
    {
        $user = User::where('email', $oAuthUser->getEmail())->first();

        if (! $oAuthUser instanceof \Laravel\Socialite\Two\User) {
            throw new InvalidArgumentException('Unsupported Socialite user implementation.');
        }

        return $user = User::updateOrCreate(
            [
                'email' => $oAuthUser->getEmail(),
            ],
            [
                'name' => $user->name ?? $oAuthUser->getName(),
                'password' => $user->password ?? Hash::make(Str::random(50)),
                'username' => $user->username ?? ($oAuthUser->getNickname() ?? $oAuthUser->nickname),
                'oauth_id' => $oAuthUser->getId(),
                'oauth_provider' => $provider->value,
                'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
                'avatar_path' => $user->avatar_path ?? $oAuthUser->getAvatar(),
                'oauth_token' => $oAuthUser->token,
                'oauth_refresh_token' => $oAuthUser->refreshToken,
            ]
        );
    }
}
