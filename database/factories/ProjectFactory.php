<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stage_id' => Stage::factory(),
            'name' => $this->faker->catchPhrase,
            'slug' => $this->faker->slug,
            'about' => $this->faker->text($maxNbChars = 250),
            'stage_updated_at' => Carbon::now(),
        ];
    }
}
