<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;
use Tests\Traits\CreatesZoomUsers;

class ZoomMeetingDeleteTest extends TestCase
{
    use CreatesZoomUsers;
    use RefreshDatabase;

    private ZoomService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ZoomService::class);
    }

    /** @test */
    public function meeting_can_be_deleted_in_zoom(): void
    {
        $meetingId = 12378;

        Saloon::fake([
            '/meetings/'.$meetingId => MockResponse::make(body: 'Meeting deleted.', status: 204),
        ]);

        $user = $this->createZoomUser(now()->addWeek());

        $this->service->deleteMeeting($meetingId, $user);

        Saloon::assertNotSent(GetRefreshTokenRequest::class);

        Saloon::assertSent(static fn (DeleteMeeting $request): bool => $request->resolveEndpoint() === '/meetings/'.$meetingId
     && $request->getMethod() === Method::DELETE);
    }
}
