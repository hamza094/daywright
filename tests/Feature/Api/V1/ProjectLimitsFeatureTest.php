<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\Subscription\PlanLimitType;
use App\Enums\Subscription\SubscriptionPlan;
use App\Enums\TaskStatus as TaskStatusEnum;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class ProjectLimitsFeatureTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    #[Test]
    public function project_owner_can_get_project_scoped_limits_from_the_dedicated_endpoint(): void
    {
        $activeTaskLimit = $this->freePlanLimit(PlanLimitType::ActiveTasksPerProject);
        $memberLimit = $this->freePlanLimit(PlanLimitType::MembersPerProject);

        Task::factory()->count(2)->for($this->user, 'owner')->for($this->project)->create([
            'status_id' => TaskStatusEnum::PENDING,
        ]);
        Task::factory()->for($this->user, 'owner')->for($this->project)->completed()->create();

        $this->project->members()->attach(User::factory()->count(2)->create(), ['active' => true]);
        $this->project->members()->attach(User::factory()->create(), ['active' => false]);

        $this->getJson(route('projects.limits', $this->project))
            ->assertOk()
            ->assertJsonPath('message', 'Project limits retrieved successfully')
            ->assertJsonPath('limits.active_tasks_per_project.used', 2)
            ->assertJsonPath('limits.active_tasks_per_project.max', $activeTaskLimit)
            ->assertJsonPath('limits.members_per_project.used', 2)
            ->assertJsonPath('limits.members_per_project.max', $memberLimit)
            ->assertJsonMissingPath('limits.projects')
            ->assertJsonMissingPath('limits.created_meetings')
            ->assertJsonMissingPath('limits.api_tokens');
    }

    #[Test]
    public function project_member_cannot_get_project_limits_from_the_dedicated_endpoint(): void
    {
        /** @var User $member */
        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);

        Sanctum::actingAs($member);

        $this->getJson(route('projects.limits', $this->project))
            ->assertForbidden()
            ->assertJson([
                'message' => 'This action is unauthorized.',
            ]);
    }

    private function freePlanLimit(PlanLimitType $type): int
    {
        $limit = SubscriptionPlan::Free->maxFor($type);

        if ($limit === null) {
            throw new RuntimeException("Expected a configured free-plan limit for [{$type->value}].");
        }

        return $limit;
    }
}
