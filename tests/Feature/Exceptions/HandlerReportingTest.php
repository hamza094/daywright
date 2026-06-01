<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Exceptions\ArchivedResourceException;
use App\Exceptions\Handler;
use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Exceptions\Paddle\PaddleRequestException;
use App\Exceptions\Paddle\SubscriptionException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Exceptions\Subscription\SubscriptionRequiredException;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class HandlerReportingTest extends TestCase
{
    #[Test]
    public function expected_client_and_business_exceptions_are_not_reported(): void
    {
        $handler = $this->app->make(Handler::class);

        $exceptions = [
            new ValidationException(Validator::make([], ['email' => ['required']])),
            new AuthenticationException,
            new AuthorizationException,
            new ModelNotFoundException,
            new NotFoundHttpException,
            new MethodNotAllowedHttpException(['POST']),
            ArchivedResourceException::project(),
            new SubscriptionRequiredException,
            new PlanLimitExceededException(
                'Plan limit exceeded.',
                'projects',
                'Projects',
                PlanLimitExceededException::REASON_LIMIT_REACHED,
                5,
                5,
                PlanLimitExceededException::SCOPE_ACCOUNT,
                1,
            ),
            new SubscriptionException('You are already on this plan.'),
            new ZoomUserErrorException('User is not connected to Zoom.'),
        ];

        foreach ($exceptions as $exception) {
            $this->assertFalse(
                $handler->shouldReport($exception),
                sprintf('Expected %s to be excluded from reporting.', $exception::class),
            );
        }
    }

    #[Test]
    public function infrastructure_and_external_service_exceptions_remain_reportable(): void
    {
        $handler = $this->app->make(Handler::class);

        $exceptions = [
            new ExternalServiceUnavailableException('Dependency unavailable.'),
            new ZoomExternalFailureException('Zoom request failed.'),
            new PaddleRequestException('Paddle request failed.'),
            new S3Exception('S3 request failed.', new Command('PutObject')),
            new RuntimeException('Database connection lost.'),
        ];

        foreach ($exceptions as $exception) {
            $this->assertTrue(
                $handler->shouldReport($exception),
                sprintf('Expected %s to remain reportable.', $exception::class),
            );
        }
    }

    #[Test]
    public function metrics_only_business_exceptions_are_logged_to_the_exception_metrics_channel(): void
    {
        $handler = $this->app->make(Handler::class);

        $request = Request::create('/api/v1/subscription/swap', 'PATCH');
        $this->app->instance('request', $request);

        Log::shouldReceive('channel')
            ->once()
            ->with('exception_metrics')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'api_exception_metric',
                Mockery::on(fn (array $context): bool => $context === [
                    'exception' => SubscriptionRequiredException::class,
                    'code' => 'subscription_required',
                    'status' => 403,
                    'message' => 'Access denied. An active subscription is required to perform this action.',
                    'path' => 'api/v1/subscription/swap',
                    'method' => 'PATCH',
                    'meta' => [
                        'upgrade_required' => true,
                    ],
                ])
            );

        $handler->report(new SubscriptionRequiredException);
    }

    #[Test]
    public function routine_framework_client_exceptions_are_not_logged_to_the_exception_metrics_channel(): void
    {
        $handler = $this->app->make(Handler::class);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('info')->never();

        $handler->report(new NotFoundHttpException);
    }
}
