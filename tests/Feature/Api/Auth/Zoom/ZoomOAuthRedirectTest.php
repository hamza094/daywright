<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth\Zoom;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class ZoomOAuthRedirectTest extends TestCase
{
    use InteractsWithZoom;
    use LazilyRefreshDatabase;
    use ProjectSetup;

    #[Test]
    public function user_is_redirected_to_zoom(): void
    {
        $this->fakeZoom()->buildAuthorizationUrlUsing(
            authorizationUrl: 'https://dummy-redirect-url.com',
            state: 'dummy-state',
            codeVerifier: 'dummy-code-verifier',
        );

        $this->get(route('api.v1.oauth.zoom.redirect'))
            ->assertOk()
            ->assertJsonPath('data.redirect_url', 'https://dummy-redirect-url.com');

        $this->assertTrue(Cache::has('oauth:zoom:authorization:dummy-state'));
        $authorization = Cache::get('oauth:zoom:authorization:dummy-state');
        $this->assertSame($this->user->getKey(), $authorization['user_id']);
        $this->assertSame('dummy-code-verifier', $authorization['code_verifier']);
    }

    #[Test]
    public function authorization_url_contains_state_and_s256_challenge(): void
    {
        $this->fakeZoom()->buildAuthorizationUrlUsing(
            authorizationUrl: 'https://zoom.us/oauth/authorize?state=test-state&code_challenge=test-challenge&code_challenge_method=S256',
            state: 'test-state',
            codeVerifier: 'test-verifier',
        );

        $response = $this->get(route('api.v1.oauth.zoom.redirect'));

        $redirectUrl = $response->json('data.redirect_url');

        $this->assertStringContainsString('state=', $redirectUrl);
        $this->assertStringContainsString('code_challenge=', $redirectUrl);
        $this->assertStringContainsString('code_challenge_method=S256', $redirectUrl);
    }
}
