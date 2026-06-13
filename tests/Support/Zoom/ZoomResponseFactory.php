<?php

declare(strict_types=1);

namespace Tests\Support\Zoom;

use Saloon\Http\Faking\MockResponse;

class ZoomResponseFactory
{
    public static function validMeetingResponse(array $overrides = []): MockResponse
    {
        $defaultResponse = [
            'id' => 124,
            'topic' => 'Test Meeting',
            'agenda' => 'Test Agenda',
            'created_at' => '2024-05-16T18:00:07Z',
            'duration' => 30,
            'join_url' => 'https://zoom.us/j/1234567890?pwd=yourpassword',
            'password' => 'testpassword',
            'join_before_host' => false,
            'start_time' => '2024-05-18T18:00:07Z',
            'start_url' => 'https://zoom.us/s/1234567890?pwd=yourpassword',
            'status' => 'waiting',
            'timezone' => 'UTC',
        ];

        return MockResponse::make(array_merge($defaultResponse, $overrides));
    }

    public static function tokenResponse(array $overrides = []): MockResponse
    {
        $defaultResponse = [
            'access_token' => 'access-token-here',
            'refresh_token' => 'refresh-token-here',
            'expires_in' => 3600,
        ];

        return MockResponse::make(array_merge($defaultResponse, $overrides));
    }

    public static function invalidGrantResponse(): MockResponse
    {
        return MockResponse::make(
            body: ['error' => 'invalid_grant', 'error_description' => 'Invalid authorization code'],
            status: 400
        );
    }

    public static function meetingNotFoundResponse(): MockResponse
    {
        return MockResponse::make(
            body: ['code' => 1001, 'message' => 'Meeting not found'],
            status: 404
        );
    }

    public static function rateLimitResponse(int $retryAfter = 60): MockResponse
    {
        return MockResponse::make(
            body: ['code' => 4294, 'message' => 'Rate limit exceeded'],
            status: 429,
            headers: ['Retry-After' => (string) $retryAfter]
        );
    }

    public static function zakTokenResponse(string $token = 'zak token'): MockResponse
    {
        return MockResponse::make([
            'token' => $token,
        ]);
    }
}
