<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
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
            'users/me/token?type=zak' => MockResponse::make([
                'token' => 'zak token',
            ]),
        ]);

        $user = $this->createZoomUser(now()->addWeek());

        (new ZoomService)->getZakToken($user);

        Saloon::assertSent(static fn (GetZakToken $request): bool => $request->resolveEndpoint() === 'users/me/token?type=zak' && $request->getMethod() === Method::GET);
    }
}
