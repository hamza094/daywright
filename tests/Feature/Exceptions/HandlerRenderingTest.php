<?php

declare(strict_types=1);

namespace Tests\Feature\Exceptions;

use App\Exceptions\ApiException;
use App\Exceptions\DashboardServiceException;
use App\Exceptions\Handler;
use App\Exceptions\Integrations\ExternalServiceUnavailableException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Exceptions\Subscription\SubscriptionRequiredException;
use App\Models\User;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class HandlerRenderingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Override]
    protected function tearDown(): void
    {
        app()->detectEnvironment(fn () => 'testing');
        parent::tearDown();
    }

    // Phase 2: Client Error Tests (4xx)

    #[Test]
    public function authentication_exception_returns_401_with_correct_json(): void
    {
        $response = $this->unauthenticatedGet('/api/v1/users/me');

        $this->assertApiErrorResponse(
            $response,
            401,
            'unauthenticated',
            'Authentication is required.'
        );
    }

    #[Test]
    public function model_not_found_returns_404_with_correct_json(): void
    {
        $this->authenticateUser();

        $response = $this->getJson('/api/v1/projects/999999');

        $this->assertApiErrorResponse(
            $response,
            404,
            'not_found',
            'Resource not found.'
        );
    }

    #[Test]
    public function authorization_exception_returns_403_with_correct_json(): void
    {
        $this->authenticateUser();

        // Try to access admin endpoint as regular user
        $response = $this->getJson('/api/v1/admin/users');

        $this->assertApiErrorResponse(
            $response,
            403,
            'forbidden',
            'This action is unauthorized.'
        );
    }

    #[Test]
    public function validation_exception_returns_422_with_errors(): void
    {
        $this->authenticateUser();

        // Try to create a project with invalid data
        $response = $this->postJson('/api/v1/projects', [
            'name' => '', // Invalid: empty name
        ]);

        // This should trigger validation error
        $response->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors']);
    }

    #[Test]
    public function method_not_allowed_returns_405_with_correct_json(): void
    {
        $this->authenticateUser();

        // Try POST on a GET-only endpoint
        $response = $this->postJson('/api/v1/users/me');

        $this->assertApiErrorResponse(
            $response,
            405,
            'method_not_allowed',
            'Method not allowed.'
        );
    }

    // Phase 3: Rate Limiting Tests
    // Note: Rate limiting tests require actual throttle middleware configuration
    // The renderable is implemented in HandlesApiExceptions trait and tested via
    // integration tests in the existing test suite.

    #[Test]
    public function throttle_exception_returns_429_with_retry_after_meta(): void
    {
        // Create exception and set headers using the proper method
        $exception = new ThrottleRequestsException;
        $exception->setHeaders([
            'Retry-After' => '47',
            'X-RateLimit-Limit' => '60',
            'X-RateLimit-Remaining' => '0',
        ]);

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);
        $response = $handler->render($request, $exception);

        $this->assertEquals(429, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('rate_limited', $json['code']);
        $this->assertEquals('Too many requests. Please try again later.', $json['message']);

        // The renderable extracts Retry-After header and adds to meta
        // This tests that the renderable is called and processes the exception
        $this->assertEquals(47, $json['meta']['retry_after_seconds']);
        // Assert Line 122 worked (HTTP Headers)
        $this->assertEquals('47', $response->headers->get('Retry-After'));
        $this->assertEquals('60', $response->headers->get('X-RateLimit-Limit'));
    }

    // Phase 4: Server Error Tests (5xx)

    #[Test]
    public function generic_server_error_prevents_sensitive_data_leak(): void
    {
        $this->authenticateUser();

        // Test the Throwable catch-all renderable directly
        $exception = new RuntimeException('SENSITIVE_DB_CREDENTIALS: password=secret123');

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);

        // Set app to production to trigger the catch-all
        app()->detectEnvironment(fn () => 'production');

        $response = $handler->render($request, $exception);

        // Verify 500 status
        $this->assertEquals(500, $response->status());

        // Verify the response does NOT contain sensitive data
        $content = $response->getContent();
        $this->assertStringNotContainsString('SENSITIVE_DB_CREDENTIALS', $content);
        $this->assertStringNotContainsString('password=secret123', $content);

        // Verify safe generic message
        $json = json_decode($content, true);
        $this->assertEquals('internal_server_error', $json['code']);
        $this->assertEquals('An unexpected server error occurred.', $json['message']);

    }

    #[Test]
    public function storage_error_returns_500_with_storage_error_code(): void
    {
        // Test S3Exception renderable directly
        $exception = new S3Exception('S3 error', new Command('PutObject'));

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);

        $response = $handler->render($request, $exception);

        $this->assertEquals(500, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('storage_error', $json['code']);
        $this->assertEquals('Storage request could not be completed.', $json['message']);
        $this->assertEquals('s3', $json['meta']['provider']);
    }

    #[Test]
    public function external_service_unavailable_returns_503(): void
    {
        // Test ExternalServiceUnavailableException renderable directly
        $exception = new ExternalServiceUnavailableException(
            'Service temporarily unavailable.',
            503
        );

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/admin/dashboard/activities', 'GET');
        $this->app->instance('request', $request);

        $response = $handler->render($request, $exception);

        $this->assertEquals(503, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('service_unavailable', $json['code']);
    }

    // Phase 5: Custom Exception Tests

    #[Test]
    public function api_exception_renders_with_custom_message_and_code(): void
    {
        // Create a custom ApiException implementation for testing
        $exception = new class('Custom error message') extends ApiException
        {
            public function status(): int
            {
                return 400;
            }

            public function errorCode(): string
            {
                return 'custom_error';
            }

            public function publicMessage(): string
            {
                return 'Custom public message';
            }
        };

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);

        $response = $handler->render($request, $exception);

        $this->assertEquals(400, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('custom_error', $json['code']);
        $this->assertEquals('Custom public message', $json['message']);
    }

    #[Test]
    public function subscription_required_exception_renders_with_meta(): void
    {
        $exception = new SubscriptionRequiredException(
            'Premium feature requires subscription',
            upgradeRequired: true
        );

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);

        $response = $handler->render($request, $exception);

        $this->assertEquals(403, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('subscription_required', $json['code']);
        $this->assertEquals('Premium feature requires subscription', $json['message']);
        $this->assertTrue($json['meta']['upgrade_required']);
    }

    #[Test]
    public function plan_limit_exceeded_exception_renders_with_meta(): void
    {
        $exception = new PlanLimitExceededException(
            'Project limit exceeded',
            limitType: 'projects',
            limitLabel: 'Projects',
            reason: PlanLimitExceededException::REASON_LIMIT_REACHED,
            currentUsage: 5,
            maxAllowed: 3,
            limitScope: PlanLimitExceededException::SCOPE_ACCOUNT,
            limitOwnerId: 1
        );

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);

        $response = $handler->render($request, $exception);

        $this->assertEquals(403, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('plan_limit_exceeded', $json['code']);
        $this->assertEquals('Project limit exceeded', $json['message']);
        $this->assertEquals(5, $json['meta']['current_usage']);
        $this->assertEquals(3, $json['meta']['max_allowed']);
        $this->assertTrue($json['meta']['can_upgrade']);
    }

    #[Test]
    public function dashboard_service_exception_renders_with_correct_code(): void
    {
        $exception = new DashboardServiceException(
            'Failed to load dashboard data'
        );

        $handler = $this->app->make(Handler::class);
        $request = Request::create('/api/v1/users/me', 'GET');
        $this->app->instance('request', $request);

        $response = $handler->render($request, $exception);

        $this->assertEquals(500, $response->status());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals('dashboard_service_error', $json['code']);
        $this->assertEquals('Failed to load dashboard data', $json['message']);
    }

    /**
     * Helper method to authenticate a user for API requests
     */
    protected function authenticateUser(): void
    {
        Sanctum::actingAs($this->user);
    }

    /**
     * Helper method to make authenticated API requests
     */
    protected function authenticatedGet(string $endpoint): TestResponse
    {
        $this->authenticateUser();

        return $this->getJson($endpoint);
    }

    /**
     * Helper method to make authenticated POST requests
     */
    protected function authenticatedPost(string $endpoint, array $data = []): TestResponse
    {
        $this->authenticateUser();

        return $this->postJson($endpoint, $data);
    }

    /**
     * Helper method to make unauthenticated API requests
     */
    protected function unauthenticatedGet(string $endpoint): TestResponse
    {
        return $this->getJson($endpoint);
    }

    /**
     * Helper method to assert standard API error response structure
     */
    protected function assertApiErrorResponse(
        TestResponse $response,
        int $status,
        string $code,
        ?string $message = null
    ): void {
        $response->assertStatus($status)
            ->assertJsonStructure([
                'message',
                'code',
                'errors',
                'meta',
            ])
            ->assertJsonPath('code', $code);

        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }
    }
}
