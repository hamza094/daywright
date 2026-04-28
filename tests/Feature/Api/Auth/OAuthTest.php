<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Enums\OAuthProvider;
use App\Models\User;
use App\Models\UserSocialAccount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery as m;
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
            ->assertJsonStructure(['redirect_url']);
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
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $socialAccount = $user?->socialAccounts()->where('provider', OAuthProvider::GitHub->value)->first();

        $this->assertNotNull($user);
        $this->assertNotNull($socialAccount);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub->value,
            'provider_user_id' => '123',
        ]);
        $this->assertEquals('access-token', $socialAccount->access_token);
        $this->assertEquals('refresh-token', $socialAccount->refresh_token);
    }

    /** @test */
    public function test_o_auth_callback(): void
    {
        $this->performOAuthCallback();

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertSuccessful()
            ->assertJsonStructure([
                'user' => ['uuid', 'name', 'email'],
                'message',
            ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertAuthenticatedAs($user, 'web');

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'username' => 'jinx004',
            'avatar_path' => 'https://example.com/avatar.jpg',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $socialAccount = $user?->socialAccounts()->where('provider', OAuthProvider::GitHub->value)->first();

        $this->assertNotNull($user);
        $this->assertNotNull($socialAccount);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub->value,
            'provider_user_id' => '123',
        ]);
        $this->assertEquals('access-token', $socialAccount->access_token);
        $this->assertEquals('refresh-token', $socialAccount->refresh_token);
    }

    /** @test */
    public function linked_social_account_is_resolved_before_email_lookup(): void
    {
        $user = User::factory(['email' => 'original@example.com'])->create();

        UserSocialAccount::factory()
            ->for($user)
            ->forProvider(OAuthProvider::GitHub, '123')
            ->create();

        $this->performOAuthCallback([
            'email' => 'changed@example.com',
        ]);

        $this->get(route('oauth.callback', ['provider' => 'github']))->assertSuccessful();

        $socialAccount = $user->fresh()?->socialAccounts()->where('provider', OAuthProvider::GitHub->value)->first();

        $this->assertAuthenticatedAs($user->fresh(), 'web');
        $this->assertSame(1, User::count());
        $this->assertNotNull($socialAccount);
        $this->assertDatabaseHas('user_social_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub->value,
            'provider_user_id' => '123',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'changed@example.com',
        ]);
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
    private function performOAuthCallback(array $overrides = []): void
    {
        $this->mockSocialite('github', array_merge([
            'id' => '123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'token' => 'access-token',
            'nickname' => 'jinx004',
            'avatar' => 'https://example.com/avatar.jpg',
            'refreshToken' => 'refresh-token',
        ], $overrides));
    }
}
