<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class AuthorizationBeforeReplayTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function revoked_project_access_is_checked_before_a_cached_response_is_replayed(): void
    {
        $member = User::factory()->create();
        $this->project->members()->attach($member, ['active' => true]);

        $meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'meeting_id' => 123456789,
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        Sanctum::actingAs($member, ['projects:read']);

        $route = route('api.v1.meetings.zoom-tokens.join', [
            'project' => $this->project->slug,
            'meeting' => $meeting->id,
        ]);
        $headers = $this->idempotencyHeaders('revoked-project-access');

        $this->withHeaders($headers)
            ->postJson($route)
            ->assertOk();

        $this->project->members()->detach($member);

        $this->withHeaders($headers)
            ->postJson($route)
            ->assertForbidden()
            ->assertHeaderMissing('Idempotency-Replayed');
    }
}
