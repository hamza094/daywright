<?php

declare(strict_types=1);

namespace App\Exceptions\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use function Safe\preg_match;

final class ApiErrorFormatter
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function response(
        string $message,
        int $status,
        string $code,
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => $errors !== [] ? $errors : (object) [],
            'meta' => $meta !== [] ? $meta : (object) [],
        ], $status);
    }

    public static function publicMessage(?string $message, string $default): string
    {
        $message = trim((string) $message);

        if ($message === '' || $message === 'Not Found') {
            return $default;
        }

        // Defense-in-depth: reject messages containing common internal leak patterns
        if (self::looksLikeInternalMessage($message)) {
            return $default;
        }

        return $message;
    }

    public static function defaultMessageForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'The request could not be processed.',
            Response::HTTP_UNAUTHORIZED => 'Authentication is required.',
            Response::HTTP_FORBIDDEN => 'You are not authorized to perform this action.',
            Response::HTTP_NOT_FOUND => 'Resource not found.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed.',
            Response::HTTP_CONFLICT => 'The request conflicts with the current resource state.',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'Validation failed.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too many requests. Please try again later.',
            Response::HTTP_SERVICE_UNAVAILABLE => 'The service is temporarily unavailable.',
            default => 'An unexpected server error occurred.',
        };
    }

    public static function defaultCodeForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_error',
            Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
            Response::HTTP_SERVICE_UNAVAILABLE => 'service_unavailable',
            default => 'internal_server_error',
        };
    }

    private static function looksLikeInternalMessage(string $message): bool
    {
        $patterns = [
            '/\bSQLSTATE\b/i',
            '/\bSELECT\b.*\bFROM\b/i',
            '/\bINSERT\b.*\bINTO\b/i',
            '/\bstack\s*trace\b/i',
            '/\b[A-Z]:\\\\/',                // Windows paths
            '/\/(?:var|home|app|vendor)\//', // Unix paths
            '/\.php:\d+/',                   // PHP file references
            '/cURL error \d+/i',            // HTTP client internals
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }
}
