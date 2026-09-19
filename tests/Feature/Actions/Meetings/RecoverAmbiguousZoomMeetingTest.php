<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Meetings;

use App\Actions\Meetings\FinalizeZoomMeetingRecovery;
use App\Actions\Meetings\RecoverAmbiguousZoomMeeting;
use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\DataTransferObjects\Zoom\MeetingSummary;
use App\Enums\Meeting\MeetingRecoveryOutcome;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;

final class RecoverAmbiguousZoomMeetingTest extends TestCase
{
    use InteractsWithZoom;
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    #[Test]
    public function it_finalizes_a_known_remote_meeting(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'operation-123'));

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::Recovered, $outcome);
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertSame(123, $meeting->meeting_id);
        $this->assertSame('https://zoom.us/j/123', $meeting->join_url);
    }

    #[Test]
    public function it_uses_an_exact_operation_id_from_a_listed_meeting(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
        ]);
        $this->fakeZoom()
            ->listsMeetings([new MeetingSummary(123, ['Daywright Operation ID' => 'operation-123'])])
            ->findsMeeting($this->zoomMeeting(123, 'operation-123'));

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::Recovered, $outcome);
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
    }

    #[Test]
    public function it_schedules_a_bounded_retry_when_no_match_exists(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
            'sync_attempts' => 0,
        ]);
        $this->fakeZoom();

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::Unresolved, $outcome);
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->sync_status);
        $this->assertSame(1, $meeting->sync_attempts);
        $this->assertTrue($meeting->sync_available_at->isFuture());
        $this->assertNull($meeting->sync_claim_token);
    }

    #[Test]
    public function it_reclaims_an_expired_claim(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
            'sync_claim_token' => '00000000-0000-4000-8000-000000000002',
            'sync_lease_expires_at' => now()->subSecond(),
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(123, 'operation-123'));

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::Recovered, $outcome);
        $this->assertSame(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertNull($meeting->sync_claim_token);
    }

    #[Test]
    public function it_stops_at_manual_review_instead_of_marking_the_meeting_failed(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
            'sync_attempts' => 4,
        ]);
        $this->fakeZoom();

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::ManualReview, $outcome);
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->sync_status);
        $this->assertSame('manual_review_required', $meeting->sync_error);
        $this->assertNull($meeting->sync_available_at);
    }

    #[Test]
    public function it_skips_a_meeting_with_an_active_claim(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
            'sync_claim_token' => '00000000-0000-4000-8000-000000000002',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $this->assertSame(MeetingRecoveryOutcome::Skipped, $outcome);
        $this->assertSame('00000000-0000-4000-8000-000000000002', $meeting->fresh()->sync_claim_token);
    }

    #[Test]
    public function it_stops_when_multiple_exact_matches_exist(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
        ]);
        $zoom = $this->fakeZoom()
            ->listsMeetings([
                new MeetingSummary(123, ['Daywright Operation ID' => 'operation-123']),
                new MeetingSummary(456, ['Daywright Operation ID' => 'operation-123']),
            ])
            ->findsMeeting($this->zoomMeeting(123, 'operation-123'));

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::ManualReview, $outcome);
        $this->assertSame('multiple_exact_operation_matches', $meeting->sync_error);
        $this->assertTrue($meeting->awaitsManualZoomRecovery());
        $zoom->assertNoMeetingsCreated();
    }

    #[Test]
    public function it_schedules_a_retry_when_zoom_lookup_fails(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
        ]);
        $this->fakeZoom()->shouldFailWithException(new ZoomExternalFailureException('Zoom request failed.'));

        $outcome = app(RecoverAmbiguousZoomMeeting::class)->execute($meeting);

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::Unresolved, $outcome);
        $this->assertTrue($meeting->sync_available_at->isFuture());
        $this->assertNull($meeting->sync_claim_token);
    }

    #[Test]
    public function a_stale_claim_cannot_finalize_the_meeting(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'operation-123',
            'sync_claim_token' => '00000000-0000-4000-8000-000000000002',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $outcome = app(FinalizeZoomMeetingRecovery::class)->execute(
            $meeting,
            '00000000-0000-4000-8000-000000000003',
            $this->zoomMeeting(123, 'operation-123'),
        );

        $meeting->refresh();

        $this->assertSame(MeetingRecoveryOutcome::Skipped, $outcome);
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->sync_status);
        $this->assertNull($meeting->meeting_id);
    }

    private function zoomMeeting(int $id, string $operationId): ZoomMeeting
    {
        return new ZoomMeeting(
            meeting_id: $id,
            topic: 'Recovered meeting',
            agenda: '',
            created_at: '2026-09-18 10:00:00',
            duration: 30,
            start_time: '2026-09-19 10:00:00',
            start_url: "https://zoom.us/s/{$id}",
            join_url: "https://zoom.us/j/{$id}",
            status: 'waiting',
            timezone: 'UTC',
            password: '',
            join_before_host: false,
            tracking_fields: ['Daywright Operation ID' => $operationId],
        );
    }
}
