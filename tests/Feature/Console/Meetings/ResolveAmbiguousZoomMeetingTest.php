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

final class ResolveAmbiguousZoomMeetingTest extends TestCase
{
    use InteractsWithZoom;
    use RefreshDatabase;

    #[Test]
    public function it_requires_a_reference_before_manual_resolution(): void
    {
        $meeting = $this->createUnknownMeeting();

        $this->artisan('meetings:resolve-ambiguous', [
            'meeting_id' => $meeting->id,
            '--mark-failed' => true,
            '--force' => true,
        ])->assertExitCode(1);

        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->fresh()->sync_status);
    }

    #[Test]
    public function it_marks_only_a_create_unknown_meeting_as_failed(): void
    {
        $meeting = $this->createUnknownMeeting();

        $this->artisan('meetings:resolve-ambiguous', [
            'meeting_id' => $meeting->id,
            '--mark-failed' => true,
            '--reference' => 'INC-123',
            '--force' => true,
        ])->assertExitCode(0);

        $meeting->refresh();

        $this->assertSame(MeetingSyncStatus::Failed, $meeting->sync_status);
        $this->assertSame('manually_confirmed_not_created', $meeting->sync_error);
    }

    #[Test]
    public function it_activates_only_a_zoom_meeting_with_the_exact_operation_id(): void
    {
        $meeting = $this->createUnknownMeeting();
        $this->fakeZoom()->findsMeeting(new ZoomMeeting(
            meeting_id: 123,
            topic: 'Recovered meeting',
            agenda: '',
            created_at: '2026-09-18 10:00:00',
            duration: 30,
            start_time: '2026-09-19 10:00:00',
            start_url: 'https://zoom.us/s/123',
            join_url: 'https://zoom.us/j/123',
            status: 'waiting',
            timezone: 'UTC',
            password: '',
            join_before_host: false,
            tracking_fields: ['Daywright Operation ID' => 'operation-123'],
        ));

        $this->artisan('meetings:resolve-ambiguous', [
            'meeting_id' => $meeting->id,
            '--zoom-meeting-id' => '123',
            '--reference' => 'INC-123',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(MeetingSyncStatus::Active, $meeting->fresh()->sync_status);
    }

    #[Test]
    public function it_rejects_manual_resolution_while_automatic_recovery_is_due(): void
    {
        $meeting = $this->createUnknownMeeting();
        $meeting->update(['sync_available_at' => now()]);

        $this->artisan('meetings:resolve-ambiguous', [
            'meeting_id' => $meeting->id,
            '--mark-failed' => true,
            '--reference' => 'INC-123',
            '--force' => true,
        ])->assertExitCode(1);

        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->fresh()->sync_status);
    }

    #[Test]
    public function it_leaves_the_meeting_unchanged_when_confirmation_is_declined(): void
    {
        $meeting = $this->createUnknownMeeting();

        $this->artisan('meetings:resolve-ambiguous', [
            'meeting_id' => $meeting->id,
            '--mark-failed' => true,
            '--reference' => 'INC-123',
        ])
            ->expectsConfirmation("Resolve meeting {$meeting->id} with reference INC-123?", 'no')
            ->assertSuccessful();

        $this->assertSame(MeetingSyncStatus::CreateUnknown, $meeting->fresh()->sync_status);
    }

    private function createUnknownMeeting(): \App\Models\Meeting
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        return MeetingTestHelper::createCreateUnknownMeeting($project, $user, [
            'sync_operation_id' => 'operation-123',
            'sync_available_at' => null,
        ]);
    }
}
