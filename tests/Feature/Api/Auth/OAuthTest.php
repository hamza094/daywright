<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Enums\OAuthProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery as m;
use RuntimeException;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_o_auth_redirect(): void
    {
        $provider = OAuthProvider::GitHub;

        $response = $this->getJson('/api/v1/auth/redirect/'.$provider->value);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['redirect_url']]);
    }

    /** @test */
    public function authenticated_user_cannot_request_o_auth_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');

        $this->getJson('/api/v1/auth/redirect/'.OAuthProvider::GitHub->value)
            ->assertBadRequest()
            ->assertJsonPath('message', 'User is already authenticated.');
    }

    /** @test */
    public function give_old_user_if_its_present(): void
    {
        $user = User::factory(['email' => 'test@example.com'])->create();

        $this->performOAuthCallback();

        $this->get(route('oauth.callback', ['provider' => 'github']));

        $this->assertDatabaseHas('users', [
            'name' => $user->name,
            'username' => $user->username,
            'avatar_path' => $user->avatar_path,
            'oauth_id' => '123',
            'oauth_provider' => OAuthProvider::GitHub,
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertEquals('access-token', $user->oauth_token);
        $this->assertEquals('refresh-token', $user->oauth_refresh_token);
    }

    /** @test */
    public function test_o_auth_callback(): void
    {
        $this->performOAuthCallback();

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['uuid', 'name', 'email'],
                ],
            ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertAuthenticatedAs($user, 'web');

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'jinx004',
            'avatar_path' => 'https://example.com/avatar.jpg',
            'oauth_id' => '123',
            'oauth_provider' => OAuthProvider::GitHub,
        ]);

        $user = User::where('email', 'test@example.com')->first();

        $this->assertEquals('access-token', $user->oauth_token);
        $this->assertEquals('refresh-token', $user->oauth_refresh_token);
    }

    /** @test */
    public function newly_created_o_auth_user_starts_generic_trial(): void
    {
        $this->travelTo(Carbon::parse('2026-03-16 11:00:00'));

        try {
            $this->performOAuthCallback();

            $this->get(route('oauth.callback', ['provider' => 'github']))->assertSuccessful();

            $user = User::query()->where('email', 'test@example.com')->firstOrFail();
            /** @var \Laravel\Paddle\Customer|null $customer */
            $customer = $user->customer()->first();

            $this->assertNotNull($customer);
            $this->assertTrue($user->fresh()->isOnTrial());
            $this->assertTrue(
                $customer->trial_ends_at->equalTo(now()->addDays((int) config('plan-limits.trial.duration_days')))
            );
        } finally {
            $this->travelBack();
        }
    }

    /** @test */
    public function existing_o_auth_user_does_not_receive_a_new_trial_customer(): void
    {
        $user = User::factory(['email' => 'test@example.com'])->create();

        $this->performOAuthCallback();

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertSuccessful();

        $this->assertDatabaseMissing('customers', [
            'billable_id' => (string) $user->getKey(),
            'billable_type' => $user->getMorphClass(),
        ]);
    }

    /** @test */
    public function callback_returns_standardized_message_when_processing_fails(): void
    {
        Socialite::shouldReceive('driver')
            ->once()
            ->with('github')
            ->andReturn(m::self());

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturn(m::self());

        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new RuntimeException('OAuth provider failed'));

        $this->getJson(route('oauth.callback', ['provider' => 'github']))
            ->assertStatus(500)
            ->assertJsonPath('message', 'Error processing user data.');
    }

    protected function mockSocialite($provider, $user = null)
    {
        $mock = Socialite::shouldReceive('stateless')
            ->andReturn(m::self())
            ->shouldReceive('driver')
            ->with($provider)
            ->andReturn(m::self());

        if ($user) {
            $mock->shouldReceive('user')
                ->andReturn((new SocialiteUser)->setRaw($user)->map($user));
        } else {
            $mock->shouldReceive('redirect')
                ->andReturn(redirect('https://url-to-provider'));
        }
    }

    /**
     * Perform the OAuth callback for testing.
     */
    private function performOAuthCallback(): void
    {
        $this->mockSocialite('github', [
            'id' => '123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'token' => 'access-token',
            'nickname' => 'jinx004',
            'avatar' => 'https://example.com/avatar.jpg',
            'refreshToken' => 'refresh-token',
        ]);
    }
}
