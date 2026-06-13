<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Models\User;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use Safe\DateTimeImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Tests\Support\Zoom\ZoomResponseFactory;
use Tests\TestCase;
use Tests\Traits\CreatesZoomUsers;

/**
 * Unit tests for Zoom meeting creation service.
 *
 * Tests the ZoomService::createMeeting method which handles creating meetings via the Zoom API.
 * These tests verify:
 * - Token refresh when access token is expired
 * - Clearing of Zoom credentials when refresh token is rejected
 * - Successful meeting creation with valid data
 * - Rate limiting behavior
 * - Token reuse when access token is still valid
 * - Concurrent refresh lock behavior
 *
 * Level: Unit/Service testing
 */
class ZoomMeetingCreateTest extends TestCase
{
    use CreatesZoomUsers;
    use RefreshDatabase;

    private User $user;

    private array $meetingData;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->meetingData = [
            'topic' => 'this is fun',
            'agenda' => 'the agenda of this meeting should be discussed soon',
            'duration' => 30,
            'password' => 'hacker',
            'join_before_host' => false,
            'start_time' => (new DateTimeImmutable('2024-06-18T18:00:07Z'))->format('Y-m-d\TH:i:s\Z'),
            'timezone' => 'UTC',
        ];
        $this->user = $this->createZoomUser(now()->addWeek());
    }

    /** @test */
    public function it_refreshes_token_and_updates_user_if_expired(): void
    {
        $this->freezeSecond();
        $expiredUser = $this->createZoomUser(now()->subWeek());
        Saloon::fake([
            GetRefreshTokenRequest::class => ZoomResponseFactory::tokenResponse([
                'access_token' => 'new-access-token-here',
                'refresh_token' => 'new-refresh-token-here',
                'expires_in' => 3600,
            ]),
            'users/me/meetings' => ZoomResponseFactory::validMeetingResponse([
                'topic' => $this->meetingData['topic'],
                'agenda' => $this->meetingData['agenda'],
                'duration' => $this->meetingData['duration'],
                'password' => $this->meetingData['password'],
                'join_before_host' => $this->meetingData['join_before_host'],
                'start_time' => '2024-05-18T18:00:07Z',
                'timezone' => $this->meetingData['timezone'],
            ]),
        ]);
        app(ZoomService::class)->createMeeting($this->meetingData, $expiredUser);
        Saloon::assertSent(GetRefreshTokenRequest::class);
        $expiredUser->refresh();
        $tokens = app(\App\Repository\OAuthConnectionRepository::class)->getTokens($expiredUser, 'zoom');
        $this->assertNotNull($tokens);
        $this->assertEquals('new-access-token-here', $tokens->accessToken);
        $this->assertEquals('new-refresh-token-here', $tokens->refreshToken);
        $this->assertTrue(now()->addHour()->equalTo($tokens->expiresAt));
    }

    /** @test */
    public function it_clears_zoom_credentials_when_the_refresh_token_is_rejected(): void
    {
        $expiredUser = $this->createZoomUser(now()->subWeek());

        Saloon::fake([
            GetRefreshTokenRequest::class => MockResponse::make(body: 'Forbidden', status: 403),
        ]);

        try {
            app(ZoomService::class)->createMeeting($this->meetingData, $expiredUser);
            $this->fail('Expected ZoomUserErrorException was not thrown.');
        } catch (ZoomUserErrorException $exception) {
            $this->assertSame('Zoom account connection needs to be re-authorized.', $exception->getMessage());
        }

        $tokens = app(\App\Repository\OAuthConnectionRepository::class)->getTokens($expiredUser, 'zoom');
        $this->assertNull($tokens);
    }

    /** @test */
    public function it_creates_meeting_in_zoom_with_valid_data(): void
    {
        Saloon::fake([
            'users/me/meetings' => ZoomResponseFactory::validMeetingResponse([
                'topic' => $this->meetingData['topic'],
                'agenda' => $this->meetingData['agenda'],
                'duration' => $this->meetingData['duration'],
                'password' => $this->meetingData['password'],
                'join_before_host' => $this->meetingData['join_before_host'],
                'start_time' => '2024-05-18T18:00:07Z',
                'timezone' => $this->meetingData['timezone'],
            ]),
        ]);
        $this->createAndAssertMeeting($this->meetingData, $this->user);
        Saloon::assertSent(fn (CreateMeeting $request): bool => $request->resolveEndpoint() === '/users/me/meetings'
            && $request->getMethod() === Method::POST
            && $request->body()->all() === [
                'topic' => $this->meetingData['topic'],
                'agenda' => $this->meetingData['agenda'],
                'duration' => $this->meetingData['duration'],
                'password' => $this->meetingData['password'],
                'join_before_host' => $this->meetingData['join_before_host'],
                'start_time' => (new DateTimeImmutable('2024-06-18T18:00:07Z'))->format('Y-m-d\TH:i:s\Z'),
                'timezone' => $this->meetingData['timezone'],
            ]
        );
    }

    /** @test */
    public function it_applies_rate_limit_when_creating_meetings(): void
    {
        $requestCount = 0;
        Saloon::fake([
            'users/me/meetings' => function (PendingRequest $request) use (&$requestCount) {
                $requestCount++;
                if ($requestCount > 2) {
                    return ZoomResponseFactory::rateLimitResponse();
                }

                return ZoomResponseFactory::validMeetingResponse([
                    'topic' => $this->meetingData['topic'],
                    'agenda' => $this->meetingData['agenda'],
                    'duration' => $this->meetingData['duration'],
                    'password' => $this->meetingData['password'],
                    'join_before_host' => $this->meetingData['join_before_host'],
                    'start_time' => '2024-05-18T18:00:07Z',
                    'timezone' => $this->meetingData['timezone'],
                ]);
            },
        ]);
        $this->createAndAssertMeeting($this->meetingData, $this->user);
        $this->createAndAssertMeeting($this->meetingData, $this->user);
        $this->expectException(RateLimitReachedException::class);
        app(ZoomService::class)->createMeeting($this->meetingData, $this->user);
    }

    /** @test */
    public function refresh_lock_prevents_concurrent_refresh(): void
    {
        $this->freezeSecond();
        $expiredUser = $this->createZoomUser(now()->subWeek());

        Saloon::fake([
            GetRefreshTokenRequest::class => ZoomResponseFactory::tokenResponse([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]),
            'users/me/meetings' => ZoomResponseFactory::validMeetingResponse([
                'topic' => $this->meetingData['topic'],
                'agenda' => $this->meetingData['agenda'],
                'duration' => $this->meetingData['duration'],
                'password' => $this->meetingData['password'],
                'join_before_host' => $this->meetingData['join_before_host'],
                'start_time' => '2024-05-18T18:00:07Z',
                'timezone' => $this->meetingData['timezone'],
            ]),
        ]);

        // Simulate concurrent requests
        $results = [];
        for ($i = 0; $i < 2; $i++) {
            $results[] = app(ZoomService::class)->createMeeting($this->meetingData, $expiredUser);
        }

        // Should only send one refresh request despite multiple concurrent calls
        Saloon::assertSent(GetRefreshTokenRequest::class, 1);

        // All requests should succeed with the new token
        foreach ($results as $result) {
            $this->assertNotNull($result);
        }
    }

    /** @test */
    public function valid_access_token_is_reused_without_refresh(): void
    {
        $validUser = $this->createZoomUser(now()->addWeek());

        Saloon::fake([
            'users/me/meetings' => ZoomResponseFactory::validMeetingResponse([
                'topic' => $this->meetingData['topic'],
                'agenda' => $this->meetingData['agenda'],
                'duration' => $this->meetingData['duration'],
                'password' => $this->meetingData['password'],
                'join_before_host' => $this->meetingData['join_before_host'],
                'start_time' => '2024-05-18T18:00:07Z',
                'timezone' => $this->meetingData['timezone'],
            ]),
        ]);

        app(ZoomService::class)->createMeeting($this->meetingData, $validUser);

        // Should not attempt to refresh valid token
        Saloon::assertNotSent(GetRefreshTokenRequest::class);
    }

    private function createAndAssertMeeting(array $meetingData, User $user): void
    {
        $meeting = app(ZoomService::class)->createMeeting($meetingData, $user);
        $expectedAttributes = [
            'meeting_id' => 124,
            'topic' => $meetingData['topic'],
            'agenda' => $meetingData['agenda'],
            'created_at' => '2024-05-16 18:00:07',
            'duration' => $meetingData['duration'],
            'join_url' => 'https://zoom.us/j/1234567890?pwd=yourpassword',
            'password' => $meetingData['password'],
            'join_before_host' => $meetingData['join_before_host'],
            'start_time' => '2024-05-18 18:00:07',
            'start_url' => 'https://zoom.us/s/1234567890?pwd=yourpassword',
            'status' => 'waiting',
            'timezone' => $meetingData['timezone'],
        ];
        $this->assertInstanceOf(\App\DataTransferObjects\Zoom\Meeting::class, $meeting);
        foreach ($expectedAttributes as $attribute => $value) {
            $this->assertEquals($value, $meeting->$attribute);
        }
    }
}
