<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\Exceptions\Integrations\Zoom\ZoomException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Safe\preg_match;

final class ZoomLogContext
{
    private const string REDACTED = '[REDACTED]';

    /**
     * @var list<string>
     */
    private const array SENSITIVE_KEY_PATTERNS = [
        '/join[_-]?url/i',
        '/start[_-]?url/i',
        '/(^|_)zoom(_|_).*token/i',
        '/(^|_)oauth/i',
        '/(^|_)zak/i',
        '/(^|_)token$/i',
        '/email/i',
        '/password/i',
        '/secret/i',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function forRequest(Request $request, ZoomException $exception): array
    {
        $operation = $request->route()?->getName() ?? sprintf('%s %s', $request->method(), $request->path());
        $userIdentifier = self::getUserIdentifier($request);

        $context = [
            'provider' => 'zoom',
            'operation' => $operation,
            ...$userIdentifier,
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
        $userIdentifier = [];

        if ($userId !== null) {
            if (is_string($userId) && self::looksLikeUuid($userId)) {
                $userIdentifier['user_uuid'] = $userId;
            } else {
                $userIdentifier['user_id'] = $userId;
            }
        }

        $ctx = [
            'provider' => 'zoom',
            'operation' => $operation,
            'meeting_id' => $meetingId,
            'request_id' => $requestId,
            ...$userIdentifier,
            ...$context,
        ];

        return self::filter(self::sanitizeContext($ctx));
    }

    /**
     * @return array<string, mixed>
     */
    private static function getUserIdentifier(Request $request): array
    {
        $user = $request->user();

        if ($user !== null) {
            if ($user->uuid !== null && $user->uuid !== '') {
                return ['user_uuid' => $user->uuid];
            }

            return ['user_id' => $user->getAuthIdentifier()];
        }

        return ['user_id' => Auth::id()];
    }

    private static function requestId(Request $request): ?string
    {
        $requestId = $request->header('x-zm-request-id') ?? $request->header('x-request-id');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    private static function providerStatusCode(ZoomException $exception): ?int
    {
        return $exception->getCode() > 0
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
        $out = [];

        foreach ($context as $key => $value) {
            if (self::isSensitiveKey($key)) {
                $out[$key] = self::REDACTED;

                continue;
            }

            $out[$key] = self::sanitizeValue($value);
        }

        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (preg_match($pattern, $key) !== 0) {
                return true;
            }
        }

        return false;
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::sanitizeArray($value);
        }

        if (is_string($value)) {
            return self::sanitizeString($value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private static function sanitizeArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = self::isSensitiveKey((string) $key)
                ? self::REDACTED
                : self::sanitizeValue($value);
        }

        return $result;
    }

    private static function sanitizeString(string $value): string
    {
        if (preg_match('/https?:\\/\\/[^\\s]*zoom\\.us\\/j\\//i', $value) !== 0) {
            return self::REDACTED;
        }

        if (preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}/', $value) !== 0) {
            return self::REDACTED;
        }

        return $value;
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
