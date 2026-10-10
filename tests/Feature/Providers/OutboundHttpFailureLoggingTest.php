<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OutboundHttpFailureLoggingTest extends TestCase
{
    #[Test]
    public function outbound_http_failure_logs_url_without_query_secrets(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Outbound HTTP request failed',
                Mockery::on(
                    fn (array $context): bool => $context['url'] === 'https://api.example.com/oauth/token'
                        && $context['method'] === 'POST'
                        && $context['exception_class'] === ConnectionException::class
                        && $context['exception_code'] === 0
                        && ! array_key_exists('exception', $context)
                        && ! str_contains(json_encode($context), 'oauth-secret-code')
                ),
            );

        $psrRequest = new PsrRequest(
            'POST',
            'https://api.example.com/oauth/token?code=oauth-secret-code',
        );

        $request = new ClientRequest($psrRequest);

        event(new ConnectionFailed(
            $request,
            new ConnectionException('Connection failed.'),
        ));
    }
}
