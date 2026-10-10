<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Limit;
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

    /** @test */
    public function rate_limit_exception_is_thrown_with_previous_exception_and_retry_after(): void
    {
        $user = $this->createZoomUser(now()->addWeek());
        $meetingData = $this->meetingData();

        $limit = Limit::allow(1)->everySeconds(60);
        $limit->exceeded();
        $previousException = new RateLimitReachedException($limit);

        Saloon::fake([
            UpdateMeeting::class => fn () => throw $previousException,
        ]);

        try {
            app(ZoomService::class)->updateMeeting($meetingData, $user);
            $this->fail('Expected ZoomRateLimitException to be thrown');
        } catch (ZoomRateLimitException $exception) {
            $this->assertInstanceOf(ZoomRateLimitException::class, $exception);
            $this->assertSame('This app is limiting Zoom requests. Please retry later. Try again in 60 seconds.', $exception->publicMessage());
            $this->assertSame($previousException, $exception->getPrevious());
            $this->assertNotNull($exception->retryAfterSeconds());
        }
    }

    /** @test */
    public function provider_http_429_with_valid_retry_after_is_normalized_to_rate_limit_exception(): void
    {
        $user = $this->createZoomUser(now()->addWeek());
        $meetingData = $this->meetingData();

        Saloon::fake([
            UpdateMeeting::class => MockResponse::make(
                status: 429,
                headers: ['Retry-After' => '120'],
            ),
        ]);

        try {
            app(ZoomService::class)->updateMeeting($meetingData, $user);
            $this->fail('Expected ZoomRateLimitException to be thrown');
        } catch (ZoomRateLimitException $exception) {
            $this->assertSame(120, $exception->retryAfterSeconds());
            $this->assertSame('Zoom is limiting requests to its API. Please retry later. Try again in 120 seconds.', $exception->publicMessage());
        }
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
