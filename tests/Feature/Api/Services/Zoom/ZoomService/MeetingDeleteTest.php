<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Services\Zoom\ZoomService;

use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Models\User;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class MeetingDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function meeting_can_be_deleted_in_zoom(): void
    {
        $meetingId = 12378;

        Saloon::fake([
            '/meetings/'.$meetingId => MockResponse::make(body: 'Meeting deleted.', status: 204),
        ]);

        $user = $this->userCreate(now()->addWeek());

        (new ZoomService)->deleteMeeting($meetingId, $user);

        Saloon::assertNotSent(GetRefreshTokenRequest::class);

        Saloon::assertSent(static fn (DeleteMeeting $request): bool => $request->resolveEndpoint() === '/meetings/'.$meetingId
     && $request->getMethod() === Method::DELETE);
    }

    private function userCreate($expireAt)
    {
        return User::factory()
            ->create([
                'zoom_access_token' => 'access-token-here',
                'zoom_refresh_token' => 'refresh-token-here',
                'zoom_expires_at' => $expireAt,
            ]);
    }
}
