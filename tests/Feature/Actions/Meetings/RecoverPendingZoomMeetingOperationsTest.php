<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Meetings;

use App\Actions\Meetings\RecoverPendingZoomMeetingOperations;
use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Jobs\RecoverZoomMeetingOperationJob;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;

final class RecoverPendingZoomMeetingOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    /** @test */
    public function it_claims_due_operation_and_dispatches_job(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_lease_expires_at' => now()->subMinute(),
        ]);

        Queue::fake();

        app(RecoverPendingZoomMeetingOperations::class)->execute();

        $meeting->refresh();

        $this->assertNotNull($meeting->sync_claim_token);
        $this->assertTrue($meeting->sync_lease_expires_at->isFuture());

        Queue::assertPushed(RecoverZoomMeetingOperationJob::class);
    }

    /** @test */
    public function it_skips_when_no_due_operations(): void
    {
        Queue::fake();

        app(RecoverPendingZoomMeetingOperations::class)->execute();

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_skips_when_another_worker_has_claim(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'another-worker-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        Queue::fake();

        app(RecoverPendingZoomMeetingOperations::class)->execute();

        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_skips_a_live_claim_and_claims_the_next_due_operation(): void
    {
        $alreadyClaimedMeeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-claimed',
            'sync_payload' => json_encode(['topic' => 'Claimed']),
            'sync_claim_token' => 'active-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
            'sync_available_at' => now()->subMinutes(2),
        ]);
        $claimableMeeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 456,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-ready',
            'sync_payload' => json_encode(['topic' => 'Ready']),
            'sync_available_at' => now()->subMinute(),
        ]);

        Queue::fake();

        app(RecoverPendingZoomMeetingOperations::class)->execute();

        $alreadyClaimedMeeting->refresh();
        $claimableMeeting->refresh();

        $this->assertSame('active-token', $alreadyClaimedMeeting->sync_claim_token);
        $this->assertNotNull($claimableMeeting->sync_claim_token);
        Queue::assertPushed(RecoverZoomMeetingOperationJob::class);
    }

    /** @test */
    public function it_claims_expired_leases(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_claim_token' => 'old-token',
            'sync_lease_expires_at' => now()->subMinute(),
        ]);

        Queue::fake();

        app(RecoverPendingZoomMeetingOperations::class)->execute();

        $meeting->refresh();

        $this->assertNotEquals('old-token', $meeting->sync_claim_token);
        Queue::assertPushed(RecoverZoomMeetingOperationJob::class);
    }

    /** @test */
    public function it_picks_up_unknown_operations_when_sync_available_at_is_due(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_status' => MeetingSyncStatus::Updating,
            'sync_operation_type' => MeetingSyncOperationType::Update,
            'sync_operation_id' => 'op-123',
            'sync_payload' => json_encode(['topic' => 'New Topic']),
            'sync_lease_expires_at' => null,
            'sync_available_at' => now()->subMinute(),
        ]);

        Queue::fake();

        app(RecoverPendingZoomMeetingOperations::class)->execute();

        Queue::assertPushed(RecoverZoomMeetingOperationJob::class);
    }
}
