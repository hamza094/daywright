<?php

declare(strict_types=1);

namespace Tests\Feature\V1\Services\Zoom\ZoomService;

use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Models\User;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class MeetingUpdateTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function meeting_can_be_updated_in_zoom(): void
    {
        Saloon::fake([
            '/meetings/1234' => MockResponse::make(status: 204),
        ]);

        $user = $this->userCreate(now()->addWeek());

        $meetingData = $this->meetingData();

        (new ZoomService)->updateMeeting($meetingData, $user);

        Saloon::assertNotSent(GetRefreshTokenRequest::class);

        Saloon::assertSent(static fn (UpdateMeeting $request): bool => $request->resolveEndpoint() === '/meetings/1234'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === [
            'topic' => 'this is fun',
            'agenda' => 'the agenda of this meeting should discussed soon',
        ]);
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

    private function meetingData(): array
    {
        return [
            'meeting_id' => 1234,
            'topic' => 'this is fun',
            'agenda' => 'the agenda of this meeting should discussed soon',
        ];
    }
}
