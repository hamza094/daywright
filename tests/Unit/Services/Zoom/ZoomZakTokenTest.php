<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Http\Integrations\Zoom\Requests\GetRefreshTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Models\User;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Zoom\ZoomResponseFactory;
use Tests\TestCase;
use Tests\Traits\CreatesZoomUsers;

class ZoomZakTokenTest extends TestCase
{
    use CreatesZoomUsers;
    use RefreshDatabase;

    /** @test */
    public function auth_user_can_get_his_zak_token(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => ZoomResponseFactory::zakTokenResponse(),
        ]);

        $user = $this->createZoomUser(now()->addWeek());

        app(ZoomService::class)->getZakToken($user);

        Saloon::assertSent(static fn (GetZakToken $request): bool => $request->resolveEndpoint() === 'users/me/token?type=zak' && $request->getMethod() === Method::GET);
    }

    /** @test */
    public function it_reloads_latest_tokens_before_refreshing_an_expired_user(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => ZoomResponseFactory::zakTokenResponse(),
        ]);

        $staleUser = $this->createZoomUser(now()->subMinute());

        $freshUser = User::query()->findOrFail($staleUser->id);
        app(\App\Repository\OAuthConnectionRepository::class)->saveTokens(
            $freshUser,
            'zoom',
            new \App\DataTransferObjects\OAuth\OAuthTokens(
                accessToken: 'fresh-access-token-here',
                refreshToken: 'fresh-refresh-token-here',
                expiresAt: now()->addHour()->toDateTimeImmutable(),
            )
        );

        app(ZoomService::class)->getZakToken($staleUser);

        Saloon::assertNotSent(GetRefreshTokenRequest::class);
        Saloon::assertSent(static fn (GetZakToken $request): bool => $request->resolveEndpoint() === 'users/me/token?type=zak' && $request->getMethod() === Method::GET);
    }
}
