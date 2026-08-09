<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Override;

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
     * @var array<int, string>
     */
    private const array API_VERSIONS = ['v1'];

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    #[Override]
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
        foreach (self::API_VERSIONS as $version) {
            $this->mapVersionedApiRoutes($version);
            $this->mapVersionedWebRoutes($version);
        }

        $this->mapWebRoutes();
    }

    protected function configureRateLimiting(): void
    {
        // ──────────────────────────────────────────────────────────
        // LAYER 0: Global Safety Net (Runs in Kernel)
        // We keep this at 300/min by IP. Since it runs before auth, it always keys by IP.
        // If we kept this at 60/min, it would block authenticated users before they could reach their 200/min user ceiling.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(300)->by('api-safetynet|'.$request->ip());
        });

        // ──────────────────────────────────────────────────────────
        // LAYER 1: Strict User Ceiling (Runs after auth)
        RateLimiter::for('user-ceiling', function (Request $request) {
            $user = $request->user();

            // If unauthenticated, skip (handled by the api safety net)
            return $user
                ? Limit::perMinute(200)->by('user-ceiling|'.$user->id)
                : Limit::none();
        });

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

        RateLimiter::for('admin-api', function (Request $request) {
            $key = optional($request->user())->id
                ?: $request->ip();

            return Limit::perMinute(60)->by(sprintf('admin-api|%s', $key));
        });

        RateLimiter::for('admin-mutations', function (Request $request) {
            $key = optional($request->user())->id
                ?: $request->ip();

            return Limit::perMinute(20)->by(sprintf('admin-mutations|%s', $key));
        });

        // ──────────────────────────────────────────────────────────
        // LAYER 3: Sensitive Endpoint / Mutation Limits
        // ──────────────────────────────────────────────────────────

        // API Token CRUD — very tight (token creation is high-value)
        RateLimiter::for('sensitive-token-mgmt', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(5)->by('sensitive|token-mgmt|'.$key);
        });

        // Destructive user operations (delete, force-delete)
        RateLimiter::for('sensitive-destructive', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(5)->by('sensitive|destructive|'.$key);
        });

        // File upload (avatar) — expensive I/O
        RateLimiter::for('sensitive-upload', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(10)->by('sensitive|upload|'.$key);
        });

        // Subscription mutations — hits external Paddle API
        RateLimiter::for('sensitive-billing', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(5)->by('sensitive|billing|'.$key);
        });

        // Database backup — extremely expensive
        RateLimiter::for('sensitive-backup', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perHour(3)->by('sensitive|backup|'.$key);
        });

        // Webhook ingress — global per-source cap
        RateLimiter::for('webhook-ingress', function (Request $request) {
            return Limit::perMinute(120)->by('webhook|'.$request->ip());
        });

    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the versioned API routes for the application.
     */
    protected function mapVersionedApiRoutes(string $version): void
    {
        $authRoutes = base_path("routes/auth/{$version}.php");
        $apiRoutes = base_path("routes/api/{$version}.php");
        $adminRoutes = base_path("routes/api/admin/{$version}.php");

        if (! file_exists($authRoutes) && ! file_exists($apiRoutes) && ! file_exists($adminRoutes)) {
            return;
        }

        Route::prefix("api/{$version}")
            ->middleware(['api'])
            ->namespace($this->namespace)
            ->group(function () use ($adminRoutes, $apiRoutes, $authRoutes, $version): void {
                $this->groupIfRouteFileExists($authRoutes, "api.{$version}.");
                $this->groupIfRouteFileExists($apiRoutes, "api.{$version}.");
                $this->groupIfRouteFileExists($adminRoutes, "api.{$version}.admin.");

                Route::fallback(fn () => abort(404));
            });
    }

    /**
     * Define the versioned web-backed API routes for the application.
     */
    protected function mapVersionedWebRoutes(string $version): void
    {
        $webRoutes = base_path("routes/web/{$version}.php");

        if (! file_exists($webRoutes)) {
            return;
        }

        Route::prefix("api/{$version}")
            ->middleware('web')
            ->namespace($this->namespace)
            ->group(function () use ($version, $webRoutes): void {
                $this->groupIfRouteFileExists($webRoutes, "api.{$version}.");
            });
    }

    private function groupIfRouteFileExists(string $filePath, string $namePrefix): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        Route::name($namePrefix)->group($filePath);
    }
}
