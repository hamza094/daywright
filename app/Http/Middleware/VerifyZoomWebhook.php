<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

final class VerifyZoomWebhook
{
    private const string ENDPOINT_VALIDATION_EVENT = 'endpoint.url_validation';

    private const string REQUEST_ID_HEADER = 'x-zm-request-id';

    private const string SIGNATURE_HEADER = 'x-zm-signature';

    private const string TIMESTAMP_HEADER = 'x-zm-request-timestamp';

    private const int TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requiredHeader(
            $request,
            self::REQUEST_ID_HEADER,
        );

        $signature = $this->requiredHeader(
            $request,
            self::SIGNATURE_HEADER,
        );

        $timestamp = $this->requiredHeader(
            $request,
            self::TIMESTAMP_HEADER,
        );

        if (
            ! $this->hasValidTimestamp($timestamp)
            || ! $this->hasValidSignature($request, $timestamp, $signature)
        ) {
            abort(
                Response::HTTP_FORBIDDEN,
                'The webhook signature was invalid.',
            );
        }

        $request->headers->set(
            $this->idempotencyHeader(),
            $requestId,
        );

        if ($request->input('event') === self::ENDPOINT_VALIDATION_EVENT) {
            return response()->json(
                $this->endpointValidationPayload($request),
            );
        }

        return $next($request);
    }

    private function requiredHeader(Request $request, string $name): string
    {
        $value = $request->header($name);

        if (! is_string($value) || trim($value) === '') {
            abort(
                Response::HTTP_BAD_REQUEST,
                "Missing required Zoom webhook header: {$name}.",
            );
        }

        return trim($value);
    }

    private function hasValidTimestamp(string $timestamp): bool
    {
        if (! ctype_digit($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp)
            <= self::TIMESTAMP_TOLERANCE_SECONDS;
    }

    private function hasValidSignature(
        Request $request,
        string $timestamp,
        string $providedSignature,
    ): bool {
        $message = "v0:{$timestamp}:{$request->getContent()}";

        $generatedSignature = 'v0='.hash_hmac(
            'sha256',
            $message,
            $this->webhookSecret(),
        );

        return hash_equals($generatedSignature, $providedSignature);
    }

    /**
     * @return array{plainToken: string, encryptedToken: string}
     */
    private function endpointValidationPayload(Request $request): array
    {
        $plainToken = trim((string) Arr::get(
            $request->input('payload', []),
            'plainToken',
            '',
        ));

        if ($plainToken === '') {
            abort(
                Response::HTTP_BAD_REQUEST,
                'Missing required Zoom webhook payload field: payload.plainToken.',
            );
        }

        return [
            'plainToken' => $plainToken,
            'encryptedToken' => hash_hmac(
                'sha256',
                $plainToken,
                $this->webhookSecret(),
            ),
        ];
    }

    private function webhookSecret(): string
    {
        $secret = config('services.zoom.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            abort(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Zoom webhook secret is not configured.',
            );
        }

        return $secret;
    }

    private function idempotencyHeader(): string
    {
        $header = config('idempotency.header');

        if (! is_string($header) || trim($header) === '') {
            abort(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Idempotency header is not configured.',
            );
        }

        return $header;
    }
}
