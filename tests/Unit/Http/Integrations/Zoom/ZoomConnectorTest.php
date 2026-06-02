<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Integrations\Zoom;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\ZoomConnector;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class ZoomConnectorTest extends TestCase
{
    #[Test]
    public function connector_maps_forbidden_responses_to_unauthorized_exceptions(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => MockResponse::make(body: 'Forbidden', status: 403),
        ]);

        $this->expectException(UnauthorizedException::class);

        $this->authenticatedConnector()->send(new GetZakToken)->throw();
    }

    #[Test]
    public function connector_maps_not_found_responses_to_not_found_exceptions(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => MockResponse::make(body: 'Not found', status: 404),
        ]);

        $this->expectException(NotFoundException::class);

        $this->authenticatedConnector()->send(new GetZakToken)->throw();
    }

    #[Test]
    public function connector_maps_rate_limited_and_server_failures_to_external_failures(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => MockResponse::make(body: 'Rate limited', status: 429),
        ]);

        try {
            $this->authenticatedConnector()->send(new GetZakToken)->throw();
            $this->fail('Expected ZoomExternalFailureException was not thrown.');
        } catch (ZoomExternalFailureException $exception) {
            $this->assertSame(429, $exception->getCode());
        }

        Saloon::fake([
            'users/me/token?type=zak' => MockResponse::make(body: 'Server failure', status: 500),
        ]);

        try {
            $this->authenticatedConnector()->send(new GetZakToken)->throw();
            $this->fail('Expected ZoomExternalFailureException was not thrown.');
        } catch (ZoomExternalFailureException $exception) {
            $this->assertSame(500, $exception->getCode());
        }
    }

    #[Test]
    public function connector_maps_client_errors_to_zoom_user_errors(): void
    {
        Saloon::fake([
            'users/me/token?type=zak' => MockResponse::make(body: 'Bad request', status: 400),
        ]);

        $this->expectException(ZoomUserErrorException::class);

        $this->authenticatedConnector()->send(new GetZakToken)->throw();
    }

    private function authenticatedConnector(): ZoomConnector
    {
        return (new ZoomConnector)->authenticate(
            new AccessTokenAuthenticator('token', 'refresh-token', new DateTimeImmutable('+1 hour')),
        );
    }
}
