<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OAuthProvider;
use App\Models\User;
use App\Models\UserSocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSocialAccount>
 */
class UserSocialAccountFactory extends Factory
{
    protected $model = UserSocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => OAuthProvider::GitHub,
            'provider_user_id' => 'provider-user-'.$this->faker->uuid,
            'access_token' => 'access-token-'.$this->faker->uuid,
            'refresh_token' => 'refresh-token-'.$this->faker->uuid,
            'token_expires_at' => now()->addHour(),
        ];
    }

    public function forProvider(OAuthProvider $provider, ?string $providerUserId = null): static
    {
        return $this->state(fn (): array => [
            'provider' => $provider,
            'provider_user_id' => $providerUserId ?? mb_strtolower($provider->name).'-'.$this->faker->uuid,
        ]);
    }
}
