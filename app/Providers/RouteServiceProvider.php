<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    // protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapAuthRoutes();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('oauth2-socialite', function (Request $request) {
            $provider = mb_strtolower((string) $request->route('provider')) ?: 'generic';

            return Limit::perMinute(8)->by(sprintf('oauth|%s|%s', $request->ip(), $provider));
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by(sprintf('login|%s|%s', $request->ip(), $email));
        });

        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by(sprintf('register|%s', $request->ip())));

        RateLimiter::for('password-email', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));

            return Limit::perMinute(4)->by(sprintf('pwd-email|%s|%s', $request->ip(), $email));
        });

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by(sprintf('pwd-reset|%s', $request->ip())));

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)->by(optional($request->user())->id ?: $request->ip()));

        RateLimiter::for('two-factor', function (Request $request) {
            $key = optional($request->user())->id
                ?: $request->ip();

            return Limit::perMinute(5)->by(sprintf('2fa|%s', $key));
        });

        RateLimiter::for('invite-actions', fn (Request $request) => Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip()));

    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api/v1')
            ->middleware(['api'])
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "auth" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapAuthRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/auth.php'));
    }
}
