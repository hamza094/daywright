<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Two-Factor Authentication Feature Tests
 *
 * Tests the complete 2FA flow including setup, confirmation, login, and management.
 */
class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const TWO_FA_SESSION = '2fa_login';

    protected User $user;

    protected string $testPassword = 'Testpassword@3';

    protected string $testEmail = '2fauser@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestUser();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_2fa_status_disabled_by_default(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('twofactor.fetch-user'));

        $response->assertOk()
            ->assertJson(['status' => 'disabled']);
    }

    /** @test */
    public function it_can_prepare_two_factor(): void
    {
        Sanctum::actingAs($this->user);

        // Assert no 2FA before setup
        $this->assertDatabaseMissing('two_factor_authentications', [
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => $this->user::class,
        ]);

        $response = $this->postTwoFactor('setup', [
            'password' => $this->testPassword,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'in_progress'])
            ->assertJsonStructure(['qr_code', 'uri', 'string', 'status']);

        // Assert 2FA was created in database
        $this->assertDatabaseHas('two_factor_authentications', [
            'authenticatable_id' => $this->user->id,
            'authenticatable_type' => $this->user::class,
        ]);
    }

    /** @test */
    public function it_can_confirm_two_factor(): void
    {
        $mockedUser = $this->createMockedUser([
            'confirmTwoFactorAuth' => ['123456', true],
            'hasTwoFactorEnabled' => false,
            'getRecoveryCodes' => collect(['abc123', 'xyz789']),
        ]);

        Sanctum::actingAs($mockedUser);

        $response = $this->postTwoFactor('confirm', [
            'code' => '123456',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'enabled',
                'message' => 'success',
                'recoveryCodes' => ['abc123', 'xyz789'],
            ]);
    }

    /** @test */
    public function it_can_show_and_regenerate_recovery_codes(): void
    {
        $mockedUser = $this->createMockedUser([
            'hasTwoFactorEnabled' => true,
            'generateRecoveryCodes' => collect(['abc123', 'xyz789']),
        ]);

        Sanctum::actingAs($mockedUser);

        $response = $this->getJson(route('twofactor.recovery-codes'));

        $response->assertOk()
            ->assertJson([
                'message' => 'success',
                'recoveryCodes' => ['abc123', 'xyz789'],
            ]);
    }

    /** @test */
    public function it_can_disable_two_factor(): void
    {
        Sanctum::actingAs($this->user);

        // First setup 2FA
        $this->postTwoFactor('setup', [
            'password' => $this->testPassword,
        ]);

        // Then disable it
        $response = $this->deleteJson(route('twofactor.disable'));

        $response->assertOk()
            ->assertJson(['status' => 'disabled']);
    }

    /** @test */
    public function it_shows_2fa_required_message_during_login_when_enabled(): void
    {
        $this->enableTwoFactorState();

        [$response] = $this->beginTwoFactorLogin();

        $response->assertJson([
            'message' => 'Two-factor authentication is enabled. Please provide the verification code.',
            'status' => '2fa_required',
        ]);

        $encryptedSession = session(self::TWO_FA_SESSION);
        $this->assertIsString($encryptedSession);

        $decryptedSession = decrypt($encryptedSession);
        $this->assertNotNull($decryptedSession['expires_at']);
    }

    /** @test */
    public function it_stores_two_factor_state_in_cache_when_login_requires_two_factor(): void
    {
        $this->enableTwoFactorState(true);

        [, $sessionKey, $cacheKey, $state] = $this->beginTwoFactorLogin();

        $encryptedSession = session($sessionKey);
        $this->assertNotNull($encryptedSession, '2FA session entry missing');

        $this->assertArrayHasKey('token', $state);

        $cached = Cache::get($cacheKey);

        $this->assertNotNull($cached, '2FA cache entry missing');
        $this->assertSame($this->user->id, $cached['user_id']);
        $this->assertSame($this->user->email, $cached['email']);
    }

    /** @test */
    public function it_clears_cached_two_factor_state_when_invalid_code_is_submitted(): void
    {
        $this->enableTwoFactorState(true);

        [, $sessionKey, $cacheKey] = $this->beginTwoFactorLogin();

        $sessionData = session()->all();

        $response = $this->withSession($sessionData)->postJson(route('twofactor.login-confirm'), [
            'code' => '123456',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertNull(Cache::get($cacheKey), '2FA cache entry should be cleared');
        $this->assertFalse(session()->has($sessionKey));
    }

    /** @test */
    public function it_logs_in_via_web_guard_and_returns_auth_payload_after_successful_two_factor(): void
    {
        $this->enableTwoFactorState(true);

        [, $sessionKey] = $this->beginTwoFactorLogin();

        $this->user = $this->user->fresh();
        $code = $this->user->makeTwoFactorCode();

        $response = $this->withSession(session()->all())->postJson(route('twofactor.login-confirm'), [
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['uuid', 'name', 'email'],
                'message',
            ]);

        $logged = User::where('email', $this->user->email)->first();

        $this->assertAuthenticatedAs($logged, 'web');
    }

    /** @test */
    public function it_fails_two_factor_login_with_missing_session(): void
    {
        $response = $this->postJson(route('twofactor.login-confirm'), [
            'code' => '123456',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertStringContainsString(
            'Your session verification window expired. Please log in again.',
            $response->json('errors.code.0')
        );
    }

    /** @test */
    public function it_fails_two_factor_login_with_expired_session(): void
    {
        // Setup encrypted session data with expired timestamp
        session()->put(self::TWO_FA_SESSION, encrypt([
            'expires_at' => now()->subMinutes(1), // Expired 1 minute ago
        ]));

        $response = $this->postJson(route('twofactor.login-confirm'), [
            'code' => '123456',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
        $this->assertStringContainsString(
            'Your session verification window expired. Please log in again.',
            $response->json('errors.code.0')
        );
    }

    /**
     * Create a test user with known credentials
     */
    private function createTestUser(): void
    {
        $this->user = User::factory()->create([
            'email' => $this->testEmail,
            'password' => Hash::make($this->testPassword),
        ]);
    }

    /**
     * Enable 2FA for the test user
     */
    private function enableTwoFactorForUser(): void
    {
        $twoFactor = $this->user->createTwoFactorAuth();

        // Ensure deterministic label for assertions if needed
        /** @var \Illuminate\Database\Eloquent\Model $twoFactor */
        $twoFactor->forceFill([
            'label' => "DayWright:{$this->user->email}",
        ])->save();

        $this->user->enableTwoFactorAuth();
    }

    /**
     * Create a mocked user with specific 2FA behavior
     */
    private function createMockedUser(array $expectations = []): User
    {
        $mockedUser = Mockery::mock(User::class)->makePartial();

        // Set default expectations
        $defaultExpectations = [
            'confirmTwoFactorAuth' => ['123456', true],
            'hasTwoFactorEnabled' => [false],
            'getRecoveryCodes' => [collect(['abc123', 'xyz789'])],
        ];

        // Merge with provided expectations
        $expectations = array_merge($defaultExpectations, $expectations);

        foreach ($expectations as $method => $params) {
            if (is_array($params)) {
                $mockedUser->shouldReceive($method)
                    ->with(...array_slice($params, 0, -1))
                    ->andReturn(end($params));
            } else {
                $mockedUser->shouldReceive($method)->andReturn($params);
            }
        }

        // Set up basic user properties
        $mockedUser->id = $this->user->id;
        $mockedUser->email = $this->user->email;
        $mockedUser->password = $this->user->password;

        return $mockedUser;
    }

    private function twoFactorSessionKey(): string
    {
        return (string) config('two-factor.login_state.session_key', self::TWO_FA_SESSION);
    }

    private function twoFactorCachePrefix(): string
    {
        return (string) config('two-factor.login_state.cache_prefix', '2fa_login:');
    }

    private function twoFactorRoute(string $name): string
    {
        return route("twofactor.$name");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function postTwoFactor(string $name, array $payload = []): TestResponse
    {
        return $this->postJson($this->twoFactorRoute($name), $payload);
    }

    private function enableTwoFactorState(bool $flushCache = false): void
    {
        if ($flushCache) {
            Cache::flush();
        }

        $this->enableTwoFactorForUser();
    }

    /**
     * Start the login flow for a 2FA-enabled user and return the response and state details.
     *
     * @param  array<string,mixed>  $overrides
     * @return array{0:TestResponse<\Symfony\Component\HttpFoundation\Response>,1:string,2:string,3:array<string,mixed>}
     */
    private function beginTwoFactorLogin(array $overrides = []): array
    {
        $payload = array_merge([
            'email' => $this->user->email,
            'password' => $this->testPassword,
        ], $overrides);

        $response = $this->postJson('/api/v1/login', $payload);

        $response->assertOk()
            ->assertJson(['status' => '2fa_required']);

        $sessionKey = $this->twoFactorSessionKey();
        $this->assertTrue(session()->has($sessionKey), '2FA session entry missing');

        $state = decrypt(session($sessionKey));
        $cacheKey = $this->twoFactorCachePrefix().$state['token'];

        return [$response, $sessionKey, $cacheKey, $state];
    }
}
