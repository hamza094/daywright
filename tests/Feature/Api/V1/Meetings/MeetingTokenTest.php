<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Meetings;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class MeetingTokenTest extends TestCase
{
    use InteractsWithZoom, ProjectSetup, RefreshDatabase {
        ProjectSetup::setUp as projectSetUp;
    }

    private User $owner;

    private Meeting $meeting;

    private User $member;

    private User $outsider;

    #[Override]
    protected function setUp(): void
    {
        $this->projectSetUp();

        $this->owner = $this->user;
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project->members()->attach($this->member, ['active' => true]);

        $this->meeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'meeting_id' => 123456789,
            'sync_status' => MeetingSyncStatus::Active,
        ]);
    }

    /** @test */
    public function owner_can_start_and_receives_jwt_token_and_zak_token(): void
    {
        $this->fakeZoom();

        $response = $this
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.start', [
                'project' => $this->project->slug,
                'meeting' => $this->meeting->id,
            ]));

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['jwt_token', 'zak_token']]);
        $response->assertJsonPath('data.zak_token', 'zak&token');
    }

    /** @test */
    public function active_member_can_join_and_receives_jwt_token_with_no_zak_token(): void
    {
        $response = $this->actingAs($this->member)
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.join', [
                'project' => $this->project->slug,
                'meeting' => $this->meeting->id,
            ]));

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['jwt_token', 'zak_token']]);
        $response->assertJsonPath('data.zak_token', null);
    }

    /** @test */
    public function member_cannot_start(): void
    {
        $response = $this->actingAs($this->member)
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.start', [
                'project' => $this->project->slug,
                'meeting' => $this->meeting->id,
            ]));

        $response->assertForbidden();
    }

    /** @test */
    public function outsider_cannot_get_any_token(): void
    {
        $response = $this->actingAs($this->outsider)
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.join', [
                'project' => $this->project->slug,
                'meeting' => $this->meeting->id,
            ]));

        $response->assertForbidden();
    }

    /** @test */
    public function unsynced_meeting_cannot_issue_tokens(): void
    {
        $unsyncedMeeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'meeting_id' => 987654321,
            'sync_status' => MeetingSyncStatus::Pending,
        ]);

        $response = $this
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.start', [
                'project' => $this->project->slug,
                'meeting' => $unsyncedMeeting->id,
            ]));

        $response->assertForbidden();
    }

    /** @test */
    public function inactive_meeting_cannot_issue_tokens(): void
    {
        $inactiveMeeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->owner->id,
            'meeting_id' => 987654321,
            'sync_status' => MeetingSyncStatus::Failed,
        ]);

        $response = $this
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.start', [
                'project' => $this->project->slug,
                'meeting' => $inactiveMeeting->id,
            ]));

        $response->assertForbidden();
    }

    /** @test */
    public function project_owner_can_start_even_if_not_meeting_owner(): void
    {
        $otherOwnerMeeting = Meeting::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->member->id,
            'meeting_id' => 555555555,
            'sync_status' => MeetingSyncStatus::Active,
        ]);

        $this->fakeZoom();

        $response = $this
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.start', [
                'project' => $this->project->slug,
                'meeting' => $otherOwnerMeeting->id,
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.zak_token', 'zak&token');
    }

    /** @test */
    public function inactive_member_cannot_join(): void
    {
        $inactiveMember = User::factory()->create();
        $this->project->members()->attach($inactiveMember, ['active' => false]);

        $response = $this->actingAs($inactiveMember)
            ->withHeaders($this->idempotencyHeaders())
            ->postJson(route('api.v1.meetings.zoom-tokens.join', [
                'project' => $this->project->slug,
                'meeting' => $this->meeting->id,
            ]));

        $response->assertForbidden();
    }
}
