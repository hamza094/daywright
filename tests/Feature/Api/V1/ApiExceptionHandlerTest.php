<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Exceptions\ArchivedResourceException;
use App\Exceptions\Handler;
use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use App\Exceptions\Integrations\Zoom\NotFoundException as ZoomNotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException as ZoomUnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Exceptions\Paddle\PaddleRequestException;
use App\Exceptions\Paddle\SubscriptionException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Exceptions\Subscription\SubscriptionRequiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Override;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ApiExceptionHandlerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/_exception-handler-test')->group(function (): void {
            Route::get('/method-not-allowed', function (): never {
                throw new MethodNotAllowedHttpException(['POST']);
            });

            Route::get('/archived/project', function (): never {
                throw ArchivedResourceException::project();
            });

            Route::get('/archived/task', function (): never {
                throw ArchivedResourceException::task();
            });

            Route::get('/subscription/required', function (): never {
                throw new SubscriptionRequiredException;
            });

            Route::get('/subscription/plan-limit', function (): never {
                throw new PlanLimitExceededException(
                    'Plan limit exceeded.',
                    'projects',
                    'Projects',
                    PlanLimitExceededException::REASON_LIMIT_REACHED,
                    5,
                    5,
                    PlanLimitExceededException::SCOPE_ACCOUNT,
                    1,
                );
            });

            Route::get('/zoom/not-found', function (): never {
                throw new ZoomNotFoundException('Zoom meeting not found.');
            });

            Route::get('/zoom/unauthorized', function (): never {
                throw new ZoomUnauthorizedException('Zoom access denied.');
            });

            Route::get('/zoom/user-error', function (): never {
                throw new ZoomUserErrorException('Malformed Zoom request.');
            });

            Route::get('/zoom/external-failure', function (): never {
                throw new ZoomExternalFailureException('Zoom upstream failed.');
            });

            Route::get('/paddle/subscription', function (): never {
                throw new SubscriptionException('Billing state conflict.');
            });

            Route::get('/paddle/request', function (): never {
                throw new PaddleRequestException('Payment provider request failed.');
            });

            Route::get('/external-service', function (): never {
                throw new ExternalServiceUnavailableException('Dependency unavailable.', Response::HTTP_SERVICE_UNAVAILABLE);
            });

            Route::get('/server-error', function (): never {
                throw new RuntimeException('Database connection lost');
            });

            Route::get('/http/server-error', function (): never {
                throw new HttpException(500, 'Connection timed out');
            });
        });
    }

    #[Test]
    public function archived_project_exceptions_use_the_standard_api_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/archived/project')
            ->assertConflict()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Project is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'project_archived')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function archived_task_exceptions_use_the_standard_api_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/archived/task')
            ->assertConflict()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Task is archived. Restore it before performing this action.')
            ->assertJsonPath('code', 'task_archived')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function subscription_required_exceptions_use_the_standard_api_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/subscription/required')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Access denied. An active subscription is required to perform this action.')
            ->assertJsonPath('code', 'subscription_required')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.upgrade_required', true);
    }

    #[Test]
    public function plan_limit_exceptions_use_the_standard_api_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/subscription/plan-limit')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Plan limit exceeded.')
            ->assertJsonPath('code', 'plan_limit_exceeded')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.reason', PlanLimitExceededException::REASON_LIMIT_REACHED)
            ->assertJsonPath('meta.limit_type', 'projects')
            ->assertJsonPath('meta.limit_label', 'Projects')
            ->assertJsonPath('meta.current_usage', 5)
            ->assertJsonPath('meta.max_allowed', 5)
            ->assertJsonPath('meta.limit_scope', PlanLimitExceededException::SCOPE_ACCOUNT)
            ->assertJsonPath('meta.can_upgrade', true)
            ->assertJsonPath('meta.upgrade_required', true);
    }

    #[Test]
    public function unmatched_api_routes_use_the_standard_not_found_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/missing-route')
            ->assertNotFound()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function method_not_allowed_responses_use_the_standard_api_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/method-not-allowed')
            ->assertStatus(405)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Method not allowed.')
            ->assertJsonPath('code', 'method_not_allowed')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function zoom_not_found_exceptions_keep_their_not_found_status(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/zoom/not-found')
            ->assertNotFound()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Zoom resource not found.')
            ->assertJsonPath('code', 'zoom_not_found')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.provider', 'zoom');
    }

    #[Test]
    public function zoom_unauthorized_exceptions_keep_their_forbidden_status(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/zoom/unauthorized')
            ->assertForbidden()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Zoom access is forbidden.')
            ->assertJsonPath('code', 'zoom_forbidden')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.provider', 'zoom');
    }

    #[Test]
    public function zoom_user_errors_are_rendered_as_bad_requests(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/zoom/user-error')
            ->assertBadRequest()
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Zoom request failed.')
            ->assertJsonPath('code', 'zoom_error')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.provider', 'zoom');
    }

    #[Test]
    public function zoom_external_failures_are_rendered_as_service_unavailable(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/zoom/external-failure')
            ->assertStatus(503)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Zoom service is temporarily unavailable.')
            ->assertJsonPath('code', 'zoom_unavailable')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.provider', 'zoom');
    }

    #[Test]
    public function paddle_subscription_exceptions_are_rendered_through_the_api_handler(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/paddle/subscription')
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Billing state conflict.')
            ->assertJsonPath('code', 'subscription_conflict')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function paddle_request_exceptions_are_rendered_as_service_unavailable(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/paddle/request')
            ->assertStatus(503)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Payment provider request failed.')
            ->assertJsonPath('code', 'payment_error')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta.provider', 'paddle');
    }

    #[Test]
    public function external_service_exceptions_use_their_configured_status_and_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/external-service')
            ->assertStatus(503)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'Dependency unavailable.')
            ->assertJsonPath('code', 'service_unavailable')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function unexpected_errors_use_the_generic_internal_server_error_payload(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->getJson('/api/v1/_exception-handler-test/server-error')
            ->assertStatus(500)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'An unexpected server error occurred.')
            ->assertJsonPath('code', 'internal_server_error')
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);

        app()->detectEnvironment(fn (): string => 'testing');
    }

    #[Test]
    public function http_server_errors_do_not_leak_their_original_exception_message(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/http/server-error')
            ->assertStatus(500)
            ->assertJsonStructure(['message', 'code', 'errors', 'meta'])
            ->assertJsonPath('message', 'An unexpected server error occurred.')
            ->assertJsonPath('code', 'internal_server_error')
            ->assertJsonMissing(['message' => 'Connection timed out'])
            ->assertJsonPath('errors', [])
            ->assertJsonPath('meta', []);
    }

    #[Test]
    public function non_api_routes_skip_the_api_render_callbacks(): void
    {
        $handler = $this->app->make(Handler::class);
        $request = Request::create('/web-route', 'GET');
        $response = $handler->render($request, new NotFoundHttpException('Web route missing.'));

        $this->assertSame(404, $response->getStatusCode());

        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }
}
