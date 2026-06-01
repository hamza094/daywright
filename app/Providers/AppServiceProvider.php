<?php

declare(strict_types=1);

namespace App\Providers;

use App\Interfaces\Paddle;
use App\Interfaces\PaddleApi;
use App\Interfaces\SendSmsInterface;
use App\Interfaces\Zoom;
use App\Models\User;
use App\Services\Admin\Integration\PaddleService;
use App\Services\Paddle\SubscriptionService;
use App\Services\VonageSmsService;
use App\Services\Zoom\ZoomService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Opcodes\LogViewer\Facades\LogViewer;
use Override;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AppServiceProvider extends ServiceProvider
{
    private const array UNSUPPORTED_PUBLIC_API_QUERY_PARAMETERS = ['include', 'fields', 'append'];

    private const string VALIDATION_FAILED_MESSAGE = 'Validation failed.';

    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->bind(
            SendSmsInterface::class,
            VonageSmsService::class
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

        EnsureFeaturesAreActive::whenInactive(function (): SymfonyResponse {
            throw new HttpException(SymfonyResponse::HTTP_FORBIDDEN, 'Feature not available.');
        });
        // Scramble/OpenAPI generation logic moved to ScrambleServiceProvider

        Model::preventLazyLoading(! app()->isProduction());
        // Model::shouldBeStrict(! app()->isProduction());

        /* LogViewer::auth(function ($request) {
        return $request->user()
            && in_array($request->user()->email, [
                'ressie03@example.net',
            ]);
        });*/
    }

    // Scramble/OpenAPI related methods extracted to ScrambleServiceProvider
}
