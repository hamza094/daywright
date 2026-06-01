<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Project;

use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Project\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class MeetingServiceTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function it_keeps_the_persisted_zoom_meeting_id_during_updates(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->for($this->user)
            ->create([
                'meeting_id' => 1234,
                'topic' => 'Original topic',
            ]);

        $zoom = Mockery::mock(Zoom::class);
        $zoom->shouldReceive('updateMeeting')
            ->once()
            ->with(
                Mockery::on(fn (array $payload): bool => $payload === [
                    'topic' => 'Updated topic',
                    'meeting_id' => 1234,
                ]),
                Mockery::on(fn (User $user): bool => $user->is($this->user)),
            )
            ->andReturnTrue();

        $service = app(MeetingService::class);

        $updatedMeeting = $service->updateProjectMeeting($meeting, $this->user, [
            'meeting_id' => 9999,
            'topic' => 'Updated topic',
        ], $zoom);

        $this->assertSame(1234, $updatedMeeting->meeting_id);
        $this->assertTrue($updatedMeeting->relationLoaded('user'));

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'meeting_id' => 1234,
            'topic' => 'Updated topic',
        ]);
    }

    #[Test]
    public function it_uses_the_meeting_resource_load_profile_for_meeting_lists(): void
    {
        Meeting::factory()
            ->for($this->project)
            ->for($this->user)
            ->create([
                'start_time' => now()->addDay(),
            ]);

        $meetings = app(MeetingService::class)->getMeetingsData($this->project, false, 10, 1);

        $meeting = $meetings->getCollection()->first();

        $this->assertInstanceOf(Meeting::class, $meeting);
        $this->assertTrue($meeting->relationLoaded('user'));
    }
}
