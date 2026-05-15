<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Meetings;

use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class MeetingCreateTest extends TestCase
{
    use InteractsWithZoom,ProjectSetup,RefreshDatabase;

    /** @test */
    public function meeting_can_be_created_successfully(): void
    {
        $zoomFake = $this->fakeZoom();

        $postBody = [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];

        $response = $this->withoutExceptionHandling()->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $meetingResponse = $response->json('data');

        $zoomFake->assertMeetingCreated(
            topic: $postBody['topic'],
            agenda: $postBody['agenda'],
            duration: $postBody['duration'],
        );

        $this->assertDatabaseHas('meetings', ['topic' => $meetingResponse['topic']]);
    }

    /** @test */
    public function user_get_exception_if_error_occurs(): void
    {
        $postBody = [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];

        $zoomFake = $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException('Test error message')
        );

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $zoomFake->assertNoMeetingsCreated();

        $response->assertBadRequest();
    }

    /** @test */
    public function it_validates_meeting_creation_request(): void
    {
        $postBody = [
            'topic' => '',
            'agenda' => '',
            'duration' => 'not-an-integer',
            'password' => '',
            'join_before_host' => 'not-a-boolean',
            'start_time' => Carbon::now()->subWeek()->toIso8601String(),
            'timezone' => 'invalid/timezone',
        ];

        $response = $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), $postBody);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'topic',
            'agenda',
            'duration',
            'password',
            'join_before_host',
            'start_time',
            'timezone',
        ]);
    }

    /** @test */
    public function start_time_must_be_iso_8601_with_timezone_offset(): void
    {
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson(route('api.v1.meetings.store', ['project' => $this->project->slug]), [
            'topic' => 'test-repo',
            'agenda' => 'test-description',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => '2024-05-18T18:00:07',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }
}
