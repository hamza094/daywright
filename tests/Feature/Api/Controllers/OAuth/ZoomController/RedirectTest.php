<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Controllers\OAuth\ZoomController;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class RedirectTest extends TestCase
{
    use InteractsWithZoom;
    use LazilyRefreshDatabase;
    use ProjectSetup;

    /** @test */
    public function user_is_redirected_to_zoom(): void
    {
        $this->fakeZoom()->buildAuthorizationUrlUsing(
            authorizationUrl: 'https://dummy-redirect-url.com',
            state: 'dummy-state',
            codeVerifier: 'dummy-code-verifier',
        );

        $this->get(route('oauth.zoom.redirect'));

        // Retrieve the cached values from the cache
        $oauthZoomState = session()->get('oauth_zoom_state');

        $oauthZoomCodeVerifier = session()->get('oauth_zoom_code_verifier');

        $this->assertEquals('dummy-state', $oauthZoomState);
        $this->assertEquals('dummy-code-verifier', $oauthZoomCodeVerifier);
    }
}
