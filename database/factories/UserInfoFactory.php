<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserInfoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UserInfo::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'mobile' => (string) $this->faker->e164PhoneNumber,
            'company' => $this->faker->company,
            'position' => $this->faker->jobTitle,
            'address' => $this->faker->address,
            'bio' => $this->faker->text(1000),
        ];
    }
}
