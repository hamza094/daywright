<?php

declare(strict_types=1);

namespace App\Exceptions\Support;

use Illuminate\Http\JsonResponse;

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
        // Use the registry as the source of truth for default messages
        $defaultCode = ErrorCode::defaultCodeForStatus($status);

        return ErrorCode::message($defaultCode);
    }

    public static function defaultCodeForStatus(int $status): string
    {
        return ErrorCode::defaultCodeForStatus($status);
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
