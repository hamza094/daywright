<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class ZoomOAuthCallbackTest extends TestCase
{
    use InteractsWithZoom;
    use LazilyRefreshDatabase;
    use ProjectSetup;

    #[Test]
    public function user_can_complete_connection_to_zoom(): void
    {
        $this->freezeSecond();

        $this->fakeZoom();

        Cache::put('oauth:zoom:dummy-state', 'dummy-code-verifier', now()->addMinutes(10));

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertJsonPath('message', 'Zoom account connected successfully');

        $this->assertFalse(Cache::has('oauth:zoom:dummy-state'));

        $this->user->refresh();

        $this->assertEquals('access-token-here', $this->user->zoom_access_token);

        $this->assertEquals('refresh-token-here', $this->user->zoom_refresh_token);

        $this->assertTrue(now()->addWeek()->equalTo($this->user->zoom_expires_at));

    }

    #[Test]
    public function error_is_returned_if_the_authorization_fails(): void
    {
        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException,
        );

        Cache::put('oauth:zoom:dummy-state', 'dummy-code-verifier', now()->addMinutes(10));

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertBadRequest()
            ->assertJsonPath('message', 'Zoom request failed.')
            ->assertJsonPath('code', 'zoom_error');

        $this->assertFalse(Cache::has('oauth:zoom:dummy-state'));

        $this->assertUserWasNotUpdated($this->user->fresh());
    }

    #[Test]
    public function service_unavailable_is_returned_if_zoom_authorization_has_an_upstream_failure(): void
    {
        $this->fakeZoom()->shouldFailWithException(
            new ZoomExternalFailureException,
        );

        Cache::put('oauth:zoom:dummy-state', 'dummy-code-verifier', now()->addMinutes(10));

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Zoom service is temporarily unavailable.')
            ->assertJsonPath('code', 'zoom_unavailable');

        $this->assertFalse(Cache::has('oauth:zoom:dummy-state'));

        $this->assertUserWasNotUpdated($this->user->fresh());
    }

    #[Test]
    public function error_is_returned_if_the_cached_verifier_is_missing_or_expired(): void
    {
        $this->fakeZoom();

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        $this->assertUserWasNotUpdated($this->user->fresh());
    }

    #[Test]
    public function error_is_returned_if_the_code_is_missing_from_the_request(): void
    {
        $this->fakeZoom();

        Cache::put('oauth:zoom:dummy-state', 'dummy-code-verifier', now()->addMinutes(10));

        $this->getJson(route('api.v1.oauth.zoom.callback').'?state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Missing required fields');

        $this->assertTrue(Cache::has('oauth:zoom:dummy-state'));

        $this->assertUserWasNotUpdated($this->user);
    }

    #[Test]
    public function error_is_returned_if_the_callback_is_replayed(): void
    {
        $this->freezeSecond();

        $this->fakeZoom();

        Cache::put('oauth:zoom:dummy-state', 'dummy-code-verifier', now()->addMinutes(10));

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertOk()
            ->assertJsonPath('message', 'Zoom account connected successfully');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        $this->assertFalse(Cache::has('oauth:zoom:dummy-state'));
    }

    #[Test]
    public function error_is_returned_if_the_user_denies_the_zoom_connection(): void
    {
        $this->fakeZoom();

        $this->getJson(route('api.v1.oauth.zoom.callback').'?error=access_denied')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom account connection denied');

        $this->assertUserWasNotUpdated($this->user->fresh());
    }

    private function assertUserWasNotUpdated(User $user): void
    {
        $this->assertNull($user->zoom_access_token);
        $this->assertNull($user->zoom_refresh_token);
        $this->assertNull($user->zoom_expires_at);
    }
}
