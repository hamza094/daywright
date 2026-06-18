<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;
use Tests\Traits\CreatesZoomUsers;

class ZoomMeetingUpdateTest extends TestCase
{
    use CreatesZoomUsers;
    use RefreshDatabase;

    /** @test */
    public function meeting_can_be_updated_in_zoom(): void
    {
        Saloon::fake([
            '/meetings/1234' => MockResponse::make(status: 204),
        ]);

        $user = $this->createZoomUser(now()->addWeek());

        $meetingData = $this->meetingData();

        app(ZoomService::class)->updateMeeting($meetingData, $user);

        Saloon::assertNotSent(GetRefreshTokenRequest::class);

        Saloon::assertSent(static fn (UpdateMeeting $request): bool => $request->resolveEndpoint() === '/meetings/1234'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === [
            'topic' => 'this is fun',
            'agenda' => 'the agenda of this meeting should discussed soon',
        ]);
    }

    private function meetingData(): array
    {
        return [
            'meeting_id' => 1234,
            'topic' => 'this is fun',
            'agenda' => 'the agenda of this meeting should discussed soon',
        ];
    }
}
