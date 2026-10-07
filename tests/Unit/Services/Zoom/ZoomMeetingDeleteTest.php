<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomRateLimitException;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Limit;
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

    /** @test */
    public function rate_limit_exception_is_thrown_with_previous_exception_and_retry_after(): void
    {
        $meetingId = 12378;
        $user = $this->createZoomUser(now()->addWeek());

        $limit = Limit::allow(1)->everySeconds(60);
        $limit->exceeded();
        $previousException = new RateLimitReachedException($limit);

        Saloon::fake([
            DeleteMeeting::class => fn () => throw $previousException,
        ]);

        try {
            $this->service->deleteMeeting($meetingId, $user);
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
        $meetingId = 12378;
        $user = $this->createZoomUser(now()->addWeek());

        Saloon::fake([
            DeleteMeeting::class => MockResponse::make(
                status: 429,
                headers: ['Retry-After' => '120'],
            ),
        ]);

        try {
            $this->service->deleteMeeting($meetingId, $user);
            $this->fail('Expected ZoomRateLimitException to be thrown');
        } catch (ZoomRateLimitException $exception) {
            $this->assertSame(120, $exception->retryAfterSeconds());
            $this->assertSame('Zoom is limiting requests to its API. Please retry later. Try again in 120 seconds.', $exception->publicMessage());
        }
    }
}
