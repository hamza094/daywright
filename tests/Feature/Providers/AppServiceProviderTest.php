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
        Config::set('services.paddle.monthly', null);
        Config::set('services.paddle.yearly', null);
        Config::set('services.paddle.subscription_name', null);
        Config::set('services.paddle.vendor_id', null);
        Config::set('services.paddle.vendor_auth_code', null);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_boots_in_production_with_public_key_configured(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('cashier.public_key', 'paddle_pub_test_key');
        Config::set('services.paddle.monthly', '12345');
        Config::set('services.paddle.yearly', '67890');
        Config::set('services.paddle.subscription_name', 'DayWright');
        Config::set('services.paddle.vendor_id', 'vendor_123');
        Config::set('services.paddle.vendor_auth_code', 'auth_code');

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_fails_to_boot_in_production_without_public_key(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('cashier.public_key', null);
        Config::set('services.paddle.monthly', null);
        Config::set('services.paddle.yearly', null);
        Config::set('services.paddle.subscription_name', null);
        Config::set('services.paddle.vendor_id', null);
        Config::set('services.paddle.vendor_auth_code', null);

        $provider = new AppServiceProvider($this->app);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The following Paddle configuration values are missing in production environment: PADDLE_PUBLIC_KEY, Monthly_Plan (services.paddle.monthly), Yearly_Plan (services.paddle.yearly), PADDLE_SUBSCRIPTION_NAME (services.paddle.subscription_name), PADDLE_VENDOR_ID (services.paddle.vendor_id), PADDLE_VENDOR_AUTH_CODE (services.paddle.vendor_auth_code)');

        $provider->boot();
    }
}
