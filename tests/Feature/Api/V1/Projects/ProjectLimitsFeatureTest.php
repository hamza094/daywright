<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Projects;

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

        $response = $this->getJson(route('api.v1.projects.limits', $this->project))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertLimitItem($response, 'active_tasks_per_project', 'Active tasks', 'project', 2, $activeTaskLimit);
        $this->assertLimitItem($response, 'members_per_project', 'Members', 'project', 2, $memberLimit);
    }

    #[Test]
    public function project_member_cannot_get_project_limits_from_the_dedicated_endpoint(): void
    {
        /** @var User $member */
        $member = User::factory()->create();

        $this->project->members()->attach($member, ['active' => true]);

        Sanctum::actingAs($member);

        $this->getJson(route('api.v1.projects.limits', $this->project))
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

    /**
     * @param  \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     */
    private function assertLimitItem(
        $response,
        string $key,
        string $expectedLabel,
        string $expectedScope,
        int $expectedUsed,
        int $expectedMax,
    ): void {
        /** @var array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>|null $limits */
        $limits = $response->json('data');

        $item = collect($limits)->firstWhere('key', $key);

        $this->assertIsArray($item);
        $this->assertSame($expectedLabel, $item['label']);
        $this->assertSame($expectedScope, $item['scope']);
        $this->assertSame($expectedUsed, $item['limit']['used']);
        $this->assertSame($expectedMax, $item['limit']['max']);
    }
}
