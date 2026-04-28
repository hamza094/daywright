<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserZoomConnection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserZoomConnection>
 */
class UserZoomConnectionFactory extends Factory
{
    protected $model = UserZoomConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => 'zoom-access-token-'.$this->faker->uuid,
            'refresh_token' => 'zoom-refresh-token-'.$this->faker->uuid,
            'expires_at' => now()->addWeek(),
        ];
    }

    public function expired(?DateTimeInterface $expiresAt = null): static
    {
        return $this->state(fn (): array => [
            'expires_at' => $expiresAt ?? now()->subMinute(),
        ]);
    }
}
