<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Meetings;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;

final class RecoverAmbiguousZoomMeetingsTest extends TestCase
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
    public function it_recovers_only_due_meetings(): void
    {
        $dueMeeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => now()->subMinute(),
        ]);
        $futureMeeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'meeting_id' => 456,
            'sync_operation_id' => 'operation-456',
            'sync_available_at' => now()->addHour(),
        ]);
        $zoom = $this->fakeZoom()->findsMeeting($this->zoomMeeting(123));

        $this->artisan('meetings:recover-ambiguous')
            ->expectsOutput('Selected: 1')
            ->expectsOutput('Recovered: 1')
            ->assertSuccessful();

        $this->assertSame(MeetingSyncStatus::Active, $dueMeeting->fresh()->sync_status);
        $this->assertSame(MeetingSyncStatus::CreateUnknown, $futureMeeting->fresh()->sync_status);
        $zoom->assertNoMeetingsCreated();
    }

    #[Test]
    public function it_recovers_a_creating_meeting_after_its_lease_expires(): void
    {
        $meeting = MeetingTestHelper::createCreatingMeeting($this->project, $this->user, [
            'meeting_id' => 123,
            'sync_operation_id' => 'operation-123',
            'sync_lease_expires_at' => now()->subMinute(),
        ]);
        $this->fakeZoom()->findsMeeting($this->zoomMeeting(123));

        $this->artisan('meetings:recover-ambiguous')
            ->expectsOutput('Selected: 1')
            ->expectsOutput('Recovered: 1')
            ->assertSuccessful();

        $this->assertSame(MeetingSyncStatus::Active, $meeting->fresh()->sync_status);
    }

    private function zoomMeeting(int $meetingId): ZoomMeeting
    {
        return new ZoomMeeting(
            meeting_id: $meetingId,
            topic: 'Recovered meeting',
            agenda: '',
            created_at: '2026-09-18 10:00:00',
            duration: 30,
            start_time: '2026-09-19 10:00:00',
            start_url: "https://zoom.us/s/{$meetingId}",
            join_url: "https://zoom.us/j/{$meetingId}",
            status: 'waiting',
            timezone: 'UTC',
            password: '',
            join_before_host: false,
            tracking_fields: ['Daywright Operation ID' => 'operation-123'],
        );
    }
}
