<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name;

        return [
            'name' => $name,
            'username' => $this->faker->userName,
            'avatar_path' => 'https://eu.ui-avatars.com/api/?name='.$name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => Hash::make('Berry@999'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'is_admin' => true,
        ]);
    }

    public function connectedToZoom(
        ?DateTimeInterface $expiresAt = null,
        string $accessToken = 'access-token-here',
        string $refreshToken = 'refresh-token-here'
    ): static {
        return $this->afterCreating(function (User $user) use ($accessToken, $expiresAt, $refreshToken): void {
            $user->zoomConnection()->create([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_at' => $expiresAt ?? now()->addWeek(),
            ]);
        });
    }
}
