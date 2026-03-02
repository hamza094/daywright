<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\TwoFactor\TwoFactorStateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Override;
use Tests\TestCase;

class TwoFactorStateManagerTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorStateManager $manager;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TwoFactorStateManager::class);
    }

    /** @test */
    public function it_creates_state_with_configured_cache_and_session_keys(): void
    {
        config()->set('two-factor.login_state.cache_prefix', 'test-2fa:');
        config()->set('two-factor.login_state.session_key', 'test_2fa_session');

        /** @var User $user */
        $user = User::factory()->create();

        $token = $this->manager->createState($user, $user->email);

        $cacheKey = 'test-2fa:'.$token;
        $this->assertNotNull(Cache::get($cacheKey));
        $this->assertSame($user->getKey(), Cache::get($cacheKey)['user_id']);
        $this->assertSame($user->email, Cache::get($cacheKey)['email']);

        $this->assertNotNull(session('test_2fa_session'));
    }

    /** @test */
    public function it_forgets_state_from_session_and_cache(): void
    {
        config()->set('two-factor.login_state.cache_prefix', 'forget-2fa:');
        config()->set('two-factor.login_state.session_key', 'forget_2fa_session');

        /** @var User $user */
        $user = User::factory()->create();

        $token = $this->manager->createState($user, $user->email);

        $this->manager->forgetStateFromSession();

        $this->assertNull(Cache::get('forget-2fa:'.$token));
        $this->assertNull(session('forget_2fa_session'));
    }
}
