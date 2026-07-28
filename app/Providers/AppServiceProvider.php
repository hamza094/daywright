<?php

declare(strict_types=1);

namespace App\Providers;

use App\Interfaces\Paddle;
use App\Interfaces\PaddleApi;
use App\Interfaces\SendSmsInterface;
use App\Interfaces\Zoom;
use App\Models\User;
use App\Services\Admin\Integration\PaddleService;
use App\Services\Config\ConfigValidator;
use App\Services\Paddle\SubscriptionService;
use App\Services\VonageSmsService;
use App\Services\Zoom\ZoomService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use Opcodes\LogViewer\Facades\LogViewer;
use Override;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    public function boot(ConfigValidator $configValidator): void
    {
        $configValidator->validatePaddleConfig();

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

        Queue::failing(function (JobFailed $event): void {
            $payload = $event->job->payload();

            Log::channel('queue_critical')->error('Critical queue job failed permanently', [
                'job' => $event->job->resolveName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'uuid' => $event->job->uuid(),
                'attempts' => $event->job->attempts(),
                'exception' => $event->exception,
                'tags' => $payload['tags'] ?? [],
            ]);
        });

        RateLimiter::for('zoom-api', fn () => Limit::perMinute(60));

        // 1. Slow DB Queries (> 500ms)
        DB::listen(function (QueryExecuted $query): void {
            if ($query->time > 500) {
                Log::warning('Slow DB Query detected', [
                    'sql' => $query->sql,
                    'bindings' => '******** (redacted for security)',
                    'time_ms' => $query->time,
                ]);
            }
        });

        // 2. Outbound HTTP Failures
        Event::listen(function (ConnectionFailed $event): void {
            Log::error('Outbound HTTP request failed', [
                'url' => $event->request->url(),
                'method' => $event->request->method(),
                'exception' => $event->exception,
            ]);
        });

        // 3. Auth Context (User ID)
        Event::listen(function (\Illuminate\Auth\Events\Authenticated $event): void {
            Context::add('user_id', $event->user->getAuthIdentifier());
        });

        // 4. Background Queue Context
        Event::listen(function (JobProcessing $event): void {
            Context::add('job_id', $event->job->getJobId());
            Context::add('job_name', $event->job->resolveName());
        });
    }

    // Scramble/OpenAPI related methods extracted to ScrambleServiceProvider
}
