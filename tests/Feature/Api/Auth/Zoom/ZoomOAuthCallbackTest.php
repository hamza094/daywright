<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Zoom\ZoomOAuthTestHelper;
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

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertJsonPath('message', 'Zoom account connected successfully');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));

        $this->user->refresh();
        $tokens = app(\App\Repository\OAuthConnectionRepository::class)->getTokens($this->user, 'zoom');
        $this->assertNotNull($tokens);
        $this->assertEquals('access-token-here', $tokens->accessToken);
        $this->assertEquals('refresh-token-here', $tokens->refreshToken);
        $this->assertTrue(now()->addWeek()->equalTo($tokens->expiresAt));

    }

    #[Test]
    public function error_is_returned_if_the_authorization_fails(): void
    {
        $this->fakeZoom()->shouldFailWithException(
            new ZoomUserErrorException,
        );

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertBadRequest()
            ->assertJsonPath('message', 'Zoom request failed.')
            ->assertJsonPath('code', 'zoom_error');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function service_unavailable_is_returned_if_zoom_authorization_has_an_upstream_failure(): void
    {
        $this->fakeZoom()->shouldFailWithException(
            (new ZoomExternalFailureException(code: 429))->withContext([
                'retry_after_seconds' => 30,
            ]),
        );

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $response = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response->assertStatus(503)
            ->assertJsonPath('message', 'Zoom service is temporarily unavailable.')
            ->assertJsonPath('code', 'zoom_unavailable')
            ->assertJsonPath('meta.retry_after_seconds', 30);

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function error_is_returned_if_authorization_state_is_missing_or_expired(): void
    {
        $this->fakeZoom();

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function error_is_returned_if_the_user_id_does_not_match(): void
    {
        $this->fakeZoom();

        $otherUser = User::factory()->create();

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $otherUser, 'dummy-code-verifier');

        $this->actingAs($this->user)
            ->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function error_is_returned_if_the_code_is_missing_from_the_request(): void
    {
        $this->fakeZoom();

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?state=dummy-state')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.');

        $this->assertTrue(Cache::has('oauth:zoom:authorization:dummy-state'));

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user);
    }

    #[Test]
    public function error_is_returned_if_the_callback_is_replayed(): void
    {
        $this->freezeSecond();

        $this->fakeZoom();

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertOk()
            ->assertJsonPath('message', 'Zoom account connected successfully');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));
    }

    #[Test]
    public function error_is_returned_if_the_user_denies_the_zoom_connection(): void
    {
        $this->fakeZoom();

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?error=access_denied&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom account connection denied.');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function other_oauth_error_returns_generic_message(): void
    {
        $this->fakeZoom();

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $this->getJson(route('api.v1.oauth.zoom.callback').'?error=invalid_request&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization failed.');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function authorization_record_expires_after_ttl(): void
    {
        $this->fakeZoom();

        Cache::put('oauth:zoom:authorization:dummy-state', [
            'user_id' => $this->user->getKey(),
            'code_verifier' => 'dummy-code-verifier',
        ], now()->subMinutes(11));

        $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        ZoomOAuthTestHelper::assertNoTokensSaved($this->user->fresh());
    }

    #[Test]
    public function concurrent_callbacks_cannot_both_consume_same_state(): void
    {
        $this->freezeSecond();

        $this->fakeZoom();

        ZoomOAuthTestHelper::createAuthorizationState('dummy-state', $this->user, 'dummy-code-verifier');

        $response1 = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');
        $response2 = $this->getJson(route('api.v1.oauth.zoom.callback').'?code=dummy-code&state=dummy-state');

        $response1->assertOk()->assertJsonPath('message', 'Zoom account connected successfully');
        $response2->assertBadRequest()->assertJsonPath('message', 'Zoom authorization session is invalid or expired.');

        $this->assertFalse(Cache::has('oauth:zoom:authorization:dummy-state'));
    }
}
