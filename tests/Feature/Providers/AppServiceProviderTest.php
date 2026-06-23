<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_boots_in_non_production_without_public_key(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        Config::set('cashier.public_key', null);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_boots_in_production_with_public_key_configured(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('cashier.public_key', 'paddle_pub_test_key');

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_fails_to_boot_in_production_without_public_key(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('cashier.public_key', null);

        $provider = new AppServiceProvider($this->app);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('PADDLE_PUBLIC_KEY must be configured in production environment.');

        $provider->boot();
    }
}
