<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function Safe\json_encode;

class VerifyZoomWebhook
{
    private const string REQUEST_ID_HEADER = 'x-zm-request-id';

    private const string SIGNATURE_HEADER = 'x-zm-signature';

    private const string TIMESTAMP_HEADER = 'x-zm-request-timestamp';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        $zoomRequestId = $this->getZoomRequestId($request);

        $providedSignature = $request->header(self::SIGNATURE_HEADER);

        $zoomTimestamp = $request->header(self::TIMESTAMP_HEADER);

        if (! $this->isRequestValid($providedSignature, $zoomTimestamp, $request)) {

            abort(Response::HTTP_FORBIDDEN, 'The webhook signature was invalid.');
        }

        $request->headers->set((string) config('idempotency.header'), $zoomRequestId);

        return $next($request);
    }

    public function isRequestValid(?string $providedSignature, ?string $timestamp, Request $request): bool
    {
        if ($providedSignature === null || $providedSignature === '') {
            return false;
        }

        return ! $this->isTimestampInvalid($timestamp)
        &&
        $this->isSignatureValid($providedSignature, $timestamp, $request);
    }

    private function getZoomRequestId(Request $request): string
    {
        $zoomRequestId = $request->header(self::REQUEST_ID_HEADER);

        if (! is_string($zoomRequestId) || trim($zoomRequestId) === '') {
            abort(Response::HTTP_BAD_REQUEST, 'Missing required Zoom webhook header: x-zm-request-id.');
        }

        return $zoomRequestId;
    }

    private function isTimestampInvalid(?string $timestamp): bool
    {
        if (! is_numeric($timestamp)) {
            return true;
        }

        return abs(time() - (int) $timestamp) > 300;
    }

    private function isSignatureValid(string $providedSignature, ?string $timestamp, Request $request): bool
    {
        if ($timestamp === null || $timestamp === '') {
            return false;
        }

        $generatedSignature = $this->generateSignature($timestamp, $request);

        return hash_equals($providedSignature, $generatedSignature);
    }

    private function generateSignature(string $timestamp, Request $request): string
    {
        $message = 'v0:'.$timestamp.':'.json_encode($request->all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }
}
