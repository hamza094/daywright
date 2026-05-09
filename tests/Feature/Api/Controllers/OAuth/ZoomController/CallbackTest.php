<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\OAuth\ZoomController;

use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class CallbackTest extends TestCase
{
    use InteractsWithZoom;
    use LazilyRefreshDatabase;
    use ProjectSetup;

    /** @test */
    public function user_can_complete_connection_to_zoom(): void
    {
        $this->freezeSecond();

        $this->fakeZoom();

        session()->put('oauth_zoom_state', 'dummy-state');

        session()->put('oauth_zoom_code_verifier', 'dummy-code-verifier');

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertJsonPath('message', 'Zoom account connected successfully');

        $this->user->refresh();

        $this->assertEquals('access-token-here', $this->user->zoom_access_token);

        $this->assertEquals('refresh-token-here', $this->user->zoom_refresh_token);

        $this->assertTrue(now()->addWeek()->equalTo($this->user->zoom_expires_at));

    }

    /** @test */
    public function error_is_returned_if_the_authorization_fails(): void
    {
        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException,
        );

        session()->put('oauth_zoom_state', 'dummy-state');

        session()->put('oauth_zoom_code_verifier', 'dummy-code-verifier');

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertBadRequest()
            ->assertJsonPath('message', 'Zoom request failed.')
            ->assertJsonPath('code', 'zoom_error');

        $this->assertUserWasNotUpdated($this->user->fresh());
    }

    /** @test */
    public function service_unavailable_is_returned_if_zoom_authorization_has_an_upstream_failure(): void
    {
        $this->fakeZoom()->shouldFailWithException(
            new ZoomExternalFailureException,
        );

        session()->put('oauth_zoom_state', 'dummy-state');

        session()->put('oauth_zoom_code_verifier', 'dummy-code-verifier');

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Zoom service is temporarily unavailable.')
            ->assertJsonPath('code', 'zoom_unavailable');

        $this->assertUserWasNotUpdated($this->user->fresh());
    }

    /** @test */
    public function error_is_returned_if_the_code_is_missing_from_the_request(): void
    {
        $this->fakeZoom();

        session()->put('oauth_zoom_state', 'dummy-state');

        session()->put('oauth_zoom_code_verifier', 'dummy-code-verifier');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Missing required fields');

        $this->assertUserWasNotUpdated($this->user);
    }

    private function assertUserWasNotUpdated(User $user): void
    {
        $this->assertNull($user->zoom_access_token);
        $this->assertNull($user->zoom_refresh_token);
        $this->assertNull($user->zoom_expires_at);
    }
}
