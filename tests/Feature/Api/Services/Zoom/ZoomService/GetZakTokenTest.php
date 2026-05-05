<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Services\Zoom\ZoomService;

use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Models\User;
use App\Services\Zoom\ZoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class GetZakTokenTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    /** @test */
    public function auth_user_can_get_his_zak_token(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => MockResponse::make([
                'token' => 'zak token',
            ]),
        ]);

        $user = $this->userCreate(now()->addWeek());

        (new ZoomService)->getZakToken($user);

        Saloon::assertSent(static fn (GetZakToken $request): bool => $request->resolveEndpoint() === 'users/me/token?type=zak' && $request->getMethod() === Method::GET);
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
