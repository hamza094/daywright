<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskSystemStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->text(55),
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'description' => $this->faker->text($maxNbChars = 250),
            'status_id' => TaskSystemStatus::Pending->value,
        ];
    }

    // Opt-in state to attach random assignees when needed in specific tests
    public function withRandomAssignees(int $min = 1, int $max = 3): Factory
    {
        return $this->afterCreating(function (Task $task) use ($min, $max) {
            $count = max(0, min($max, $min));
            if ($min !== $max) {
                $count = rand($min, $max);
            }
            if ($count > 0) {
                $assignees = User::inRandomOrder()->limit($count)->get();
                if ($assignees->isNotEmpty()) {
                    $task->assignee()->attach($assignees);
                }
            }
        });
    }

    public function overdue(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'due_at' => Carbon::now()->subDays($this->faker->numberBetween(3, 28)),
            ];
        });
    }

    public function completed(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status_id' => TaskSystemStatus::Completed->value,
                'due_at' => Carbon::now()->addDays($this->faker->numberBetween(1, 5)),
            ];
        });
    }

    public function remaining(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status_id' => TaskSystemStatus::InProgress->value,
                'due_at' => Carbon::now()->addDays($this->faker->numberBetween(5, 30)),
            ];
        });
    }
}
