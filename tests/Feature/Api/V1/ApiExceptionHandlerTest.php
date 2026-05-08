<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Exceptions\Integrations\Zoom\NotFoundException as ZoomNotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException as ZoomUnauthorizedException;
use App\Exceptions\Paddle\SubscriptionException;
use Illuminate\Support\Facades\Route;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
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

            Route::get('/zoom/not-found', function (): never {
                throw new ZoomNotFoundException('Zoom meeting not found.');
            });

            Route::get('/zoom/unauthorized', function (): never {
                throw new ZoomUnauthorizedException('Zoom access denied.');
            });

            Route::get('/paddle/subscription', function (): never {
                throw new SubscriptionException('Billing state conflict.');
            });
        });
    }

    #[Test]
    public function unmatched_api_routes_use_the_standard_not_found_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/missing-route')
            ->assertNotFound()
            ->assertJsonPath('message', 'Sorry Record not found.');
    }

    #[Test]
    public function method_not_allowed_responses_use_the_standard_api_payload(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/method-not-allowed')
            ->assertStatus(405)
            ->assertJsonPath('message', 'The HTTP method used for the request is not allowed.');
    }

    #[Test]
    public function zoom_not_found_exceptions_keep_their_not_found_status(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/zoom/not-found')
            ->assertNotFound()
            ->assertJsonPath('message', 'Zoom meeting not found.');
    }

    #[Test]
    public function zoom_unauthorized_exceptions_keep_their_forbidden_status(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/zoom/unauthorized')
            ->assertForbidden()
            ->assertJsonPath('message', 'Zoom access denied.');
    }

    #[Test]
    public function paddle_subscription_exceptions_are_rendered_through_the_api_handler(): void
    {
        $this->getJson('/api/v1/_exception-handler-test/paddle/subscription')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Billing state conflict.');
    }
}
