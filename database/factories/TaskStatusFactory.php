<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStatus>
 */
class TaskStatusFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TaskStatus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => 'Not Started',
            'color' => '#CCCCCC',
            'user_id' => User::factory(),

        ];
    }

    public function started(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'label' => 'Started',
                'color' => '#FFD700',
                'user_id' => User::factory(),

            ];
        });
    }

    public function progress(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'label' => 'In Progress',
                'color' => '#0000FF',
                'user_id' => User::factory(),

            ];
        });
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'label' => 'Completed',
                'color' => '#00FF00',
                'user_id' => User::factory(),

            ];
        });
    }
}
