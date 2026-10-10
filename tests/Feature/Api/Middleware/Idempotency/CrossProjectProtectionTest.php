<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CrossProjectProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $projectA;

    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->create();
        $this->projectA = Project::factory()->for($this->owner)->create();
        $this->projectB = Project::factory()->for($this->owner)->create();
    }

    #[Test]
    public function the_same_key_can_be_used_for_different_projects(): void
    {
        $invitedUser = User::factory()->create();
        $token = $this->owner->createToken('test', ['team:write'])->plainTextToken;
        $headers = ['Idempotency-Key' => 'cross-project-invitation'];
        $payload = ['email' => $invitedUser->email];

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson($this->apiV1ProjectRoute('send.invitation', $this->projectA), $payload)
            ->assertCreated();

        $this->withToken($token)
            ->withHeaders($headers)
            ->postJson($this->apiV1ProjectRoute('send.invitation', $this->projectB), $payload)
            ->assertCreated();

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->projectA->id,
            'user_id' => $invitedUser->id,
            'active' => false,
        ]);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->projectB->id,
            'user_id' => $invitedUser->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function the_same_key_replays_within_one_project(): void
    {
        $invitedUser = User::factory()->create();
        $token = $this->owner->createToken('test', ['team:write'])->plainTextToken;
        $headers = ['Idempotency-Key' => 'same-project-invitation'];
        $payload = ['email' => $invitedUser->email];
        $route = $this->apiV1ProjectRoute('send.invitation', $this->projectA);

        $firstResponse = $this->withToken($token)
            ->withHeaders($headers)
            ->postJson($route, $payload)
            ->assertCreated();

        $secondResponse = $this->withToken($token)
            ->withHeaders($headers)
            ->postJson($route, $payload)
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($firstResponse->json(), $secondResponse->json());
        $this->assertSame(
            1,
            $this->projectA->members()->whereKey($invitedUser->id)->count(),
        );
    }
}
