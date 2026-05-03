<?php

declare(strict_types=1);

namespace App\Providers;

use App\Interfaces\Paddle;
use App\Interfaces\PaddleApi;
use App\Interfaces\SendSmsInterface;
use App\Interfaces\Zoom;
use App\Models\User;
use App\Services\Api\V1\Admin\Integration\PaddleService;
use App\Services\Api\V1\Paddle\SubscriptionService;
use App\Services\Api\V1\SendSmsService;
use App\Services\Api\V1\Zoom\ZoomService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Opcodes\LogViewer\Facades\LogViewer;
use Override;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(
            SendSmsInterface::class,
            SendSmsService::class
        );

        $this->app->bind(Paddle::class, SubscriptionService::class);

        $this->app->bind(PaddleApi::class, PaddleService::class);

        $this->app->bind(Zoom::class, ZoomService::class);

        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Feature::define('project-export', fn (User $user): bool => $user->isAdmin());
        Feature::define('project-messaging', fn (User $user): bool => $user->isAdmin());

        EnsureFeaturesAreActive::whenInactive(fn (Request $request, array $features): SymfonyResponse => response()->json([
            'message' => 'Feature not available.',
        ], SymfonyResponse::HTTP_FORBIDDEN));

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        Scramble::routes(function (Route $route): bool {
            $excludedPrefixes = [
                'api/v1/admin',
                'api/v1/webhooks',
                'api/v1/oauth/zoom',
                'api/v1/users/me/zoom-token',
                'api/v1/users/me/zoom-jwt-token',
                'api/v1/users/search',
                'api/v1/projects/{project}/export',
                'api/v1/projects/{project}/message',
                'api/v1/projects/{project}/messages',
            ];

            return Str::startsWith($route->uri, 'api/v1') &&
               ! Str::startsWith($route->uri, $excludedPrefixes);

        });

        Model::preventLazyLoading(! app()->isProduction());
        // Model::shouldBeStrict(! app()->isProduction());

        /* LogViewer::auth(function ($request) {
        return $request->user()
            && in_array($request->user()->email, [
                'ressie03@example.net',
            ]);
        });*/
    }
}
