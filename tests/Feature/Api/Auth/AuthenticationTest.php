<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Override;
use Tests\TestCase;
use Torann\GeoIP\Facades\GeoIP;
use Torann\GeoIP\Location;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const string TEST_PASSWORD = 'Testpassword@3';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();

        // create a user
        User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make(self::TEST_PASSWORD),
        ]);

    }

    /** @test */
    public function register_new_user(): void
    {
        $this->postJson(route('auth.register'),
            ['name' => 'Elvis William',
                'email' => 'mihupocob@mailinator.com',
                'password' => 'Password4!',
                'password_confirmation' => 'Password4!',
            ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'mihupocob@mailinator.com']);
    }

    /** @test */
    public function register_new_user_starts_generic_trial(): void
    {
        $this->travelTo(Carbon::parse('2026-03-16 09:00:00'));

        try {
            $this->postJson(route('auth.register'), [
                'name' => 'Trial User',
                'email' => 'trial-user@example.com',
                'password' => 'Password4!',
                'password_confirmation' => 'Password4!',
            ])->assertCreated();

            $user = User::query()->where('email', 'trial-user@example.com')->firstOrFail();
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
    public function api_login_returns_user_and_access_token_after_successful_login(): void
    {
        $response = $this->postJson(route('auth.login'), [
            'email' => 'johndoe@example.org',
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'access_token', 'message', 'status'])
            ->assertJsonFragment(['status' => 'success']);
    }

    /** @test */
    public function api_login_stores_timezone_from_geoip_when_missing(): void
    {
        Http::fake([
            'https://ipecho.net/plain' => Http::response('8.8.8.8', 200),
        ]);

        GeoIP::shouldReceive('getLocation')
            ->once()
            ->with('8.8.8.8')
            ->andReturn(new Location(['timezone' => 'Europe/London']));

        $response = $this->postJson(route('auth.login'), [
            'email' => 'johndoe@example.org',
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'johndoe@example.org',
            'timezone' => 'Europe/London',
        ]);
    }

    /** @test */
    public function register_stores_timezone_from_geoip_when_missing(): void
    {
        Http::fake([
            'https://ipecho.net/plain' => Http::response('8.8.4.4', 200),
        ]);

        GeoIP::shouldReceive('getLocation')
            ->once()
            ->with('8.8.4.4')
            ->andReturn(new Location(['timezone' => 'Europe/Paris']));

        $this->postJson(route('auth.register'), [
            'name' => 'Elvis William',
            'email' => 'mihupocob@mailinator.com',
            'password' => 'Password4!',
            'password_confirmation' => 'Password4!',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'mihupocob@mailinator.com',
            'timezone' => 'Europe/Paris',
        ]);
    }

    /** @test */
    public function spa_session_login_returns_payload_without_access_token(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this->withoutExceptionHandling()->postJson('/api/v1/session/login', [
            'email' => 'johndoe@example.org',
            'password' => self::TEST_PASSWORD,
        ]);

        $user = User::where('email', 'johndoe@example.org')->first();
        $this->assertAuthenticatedAs($user, 'web');

        $response->assertOk()
            ->assertJsonStructure(['user', 'message', 'status'])
            ->assertJsonMissing(['access_token'])
            ->assertJsonFragment(['status' => 'success']);
    }

    /** @test */
    public function show_validation_email_error(): void
    {
        $response = $this->postJson(route('auth.login'), [
            'email' => 'test@test.com',
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function show_validation_password_errors(): void
    {
        $response = $this->postJson(route('auth.register'),
            ['name' => 'Elvis William',
                'email' => 'mihupocob@mailinator.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password'])
            ->assertJson([
                'errors' => [
                    'password' => [
                        'The password must include both uppercase and lowercase letters.',
                        'The password must include at least one special character (symbol).',
                        'The password must contain at least one number.',
                    ],
                ],
            ]);
    }

    /** @test */
    public function authenticated_user_can_logout(): void
    {
        Sanctum::actingAs(
            User::first(),
        );

        $response = $this->postJson(route('auth.logout'), []);
        $response->assertOk();
    }

    /** @test */
    public function registration_with_existing_email_not_allowed(): void
    {
        $this->postJson(route('auth.register'),
            ['name' => 'Elvis William',
                'email' => 'johndoe@example.org',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
