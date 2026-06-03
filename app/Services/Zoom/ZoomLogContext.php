<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ZoomLogContext
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequest(Request $request, ZoomException $exception): array
    {
        $operation = $request->route()?->getName() ?? sprintf('%s %s', $request->method(), $request->path());

        $userUuid = null;
        $user = $request->user();

        if ($user !== null) {
            if (isset($user->uuid) && is_string($user->uuid) && $user->uuid !== '') {
                $userUuid = $user->uuid;
            } else {
                $id = $user->getAuthIdentifier();

                if (is_int($id) || ctype_digit((string) $id)) {
                    $userUuid = User::where('id', (int) $id)->value('uuid') ?: null;
                }
            }
        }

        if ($userUuid === null) {
            $authId = Auth::id();

            if (is_int($authId) || ctype_digit((string) $authId)) {
                $userUuid = User::where('id', (int) $authId)->value('uuid') ?: null;
            }
        }

        $context = [
            'provider' => 'zoom',
            'operation' => $operation,
            'user_uuid' => $userUuid,
            'request_id' => self::requestId($request),
            'path' => $request->path(),
            'method' => $request->method(),
            'provider_status_code' => self::providerStatusCode($exception),
            ...$exception->context(),
        ];

        return self::filter(self::sanitizeContext($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function forWebhook(
        string $operation,
        int|string $meetingId,
        ?string $requestId,
        int|string|null $userId = null,
        array $context = [],
    ): array {
        $userUuid = null;

        if ($userId !== null) {
            if (is_string($userId) && self::looksLikeUuid($userId)) {
                $userUuid = $userId;
            } elseif (is_int($userId) || ctype_digit((string) $userId)) {
                $userUuid = User::where('id', (int) $userId)->value('uuid') ?: null;
            }
        }

        $ctx = [
            'provider' => 'zoom',
            'operation' => $operation,
            'meeting_id' => $meetingId,
            'request_id' => $requestId,
            'user_uuid' => $userUuid,
            ...$context,
        ];

        return self::filter(self::sanitizeContext($ctx));
    }

    private static function requestId(Request $request): ?string
    {
        $requestId = $request->header('x-zm-request-id') ?? $request->header('x-request-id');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    private static function providerStatusCode(ZoomException $exception): ?int
    {
        return is_int($exception->getCode()) && $exception->getCode() > 0
            ? $exception->getCode()
            : null;
    }

    /**
     * Sanitize context to remove or redact sensitive keys and values.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function sanitizeContext(array $context): array
    {
        $sensitiveKeyPatterns = [
            '/join[_-]?url/i',
            '/start[_-]?url/i',
            '/(^|_)zoom(_|_).*token/i',
            '/(^|_)oauth/i',
            '/(^|_)token$/i',
            '/email/i',
            '/password/i',
            '/secret/i',
        ];

        $sanitizeValue = function ($value) use (&$sanitizeValue) {
            if (is_array($value)) {
                $res = [];

                foreach ($value as $k => $v) {
                    $res[$k] = $sanitizeValue($v);
                }

                return $res;
            }

            if (is_string($value)) {
                if (preg_match('/https?:\\/\\/[^\\s]*zoom\\.us\\/j\\//i', $value)) {
                    return '[REDACTED]';
                }

                if (preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}/', $value)) {
                    return '[REDACTED]';
                }
            }

            return $value;
        };

        $out = [];

        foreach ($context as $key => $value) {
            $isSensitiveKey = false;

            foreach ($sensitiveKeyPatterns as $pat) {
                if (preg_match($pat, (string) $key)) {
                    $isSensitiveKey = true;
                    break;
                }
            }

            if ($isSensitiveKey) {
                $out[$key] = '[REDACTED]';

                continue;
            }

            $out[$key] = $sanitizeValue($value);
        }

        return $out;
    }

    private static function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function filter(array $context): array
    {
        return array_filter(
            $context,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }
}
