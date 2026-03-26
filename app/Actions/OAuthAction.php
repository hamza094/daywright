<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OAuthProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class OAuthAction
{
    public function createUpdateUser(SocialiteUser $oAuthUser, OAuthProvider $provider): User
    {
        $user = User::where('email', $oAuthUser->getEmail())->first();

        /** @var \Laravel\Socialite\Two\User $oAuthUserConcrete */
        $oAuthUserConcrete = $oAuthUser;

        return $user = User::updateOrCreate(
            [
                'email' => $oAuthUser->getEmail(),
            ],
            [
                'name' => $user->name ?? $oAuthUser->getName(),
                'password' => $user->password ?? Hash::make(Str::random(50)),
                'username' => $user->username ?? ($oAuthUser->nickname ?? $oAuthUser->getNickname()),
                'oauth_id' => $oAuthUser->getId(),
                'oauth_provider' => $provider->value,
                'email_verified_at' => $user->email_verified_at ?? Carbon::now(),
                'avatar_path' => $user->avatar_path ?? $oAuthUser->getAvatar(),
                'oauth_token' => $oAuthUserConcrete->token,
                'oauth_refresh_token' => $oAuthUserConcrete->refreshToken,
            ]
        );
    }
}
