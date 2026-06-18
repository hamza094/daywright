<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Project;

use App\Models\Meeting;
use App\Services\Project\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class MeetingServiceTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

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
