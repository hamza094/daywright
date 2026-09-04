<?php

declare(strict_types=1);

namespace App\Documentation\Transformers;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Adds middleware-aware error responses to public API operations.
 *
 * Handles:
 * - 403 from token abilities and policies
 * - 429 from throttling
 * - Idempotency headers and responses
 */
final class PublicApiMiddlewareResponses extends OperationExtension
{
    /**
     * Structured middleware data.
     *
     * @return array{baseName: string, parameters: array<int, string>, original: string}
     */
    public static function parseMiddleware(string $middleware): array
    {
        $original = $middleware;

        // Remove namespace if present
        $baseName = Str::afterLast($middleware, '\\');

        // Extract parameters (everything after colon)
        $parameters = [];
        if (str_contains($baseName, ':')) {
            [$baseName, $paramString] = explode(':', $baseName, 2);
            $parameters = explode(',', $paramString);
            $parameters = array_map('trim', $parameters);
        }

        return [
            'baseName' => $baseName,
            'parameters' => $parameters,
            'original' => $original,
        ];
    }

    /**
     * Static version for testing.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    public static function hasTokenAbilityOrPolicyMiddlewareStatic(array $parsedMiddleware): bool
    {
        foreach ($parsedMiddleware as $parsed) {
            $baseName = $parsed['baseName'];

            // Token ability middleware (alias or class)
            if ($baseName === 'tokenAbility' || $baseName === 'CheckTokenAbilities') {
                return true;
            }

            // Policy middleware (alias or class)
            if ($baseName === 'can' || $baseName === 'Authorize') {
                return true;
            }
        }

        return false;
    }

    /**
     * Static version for testing.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    public static function hasThrottleMiddlewareStatic(array $parsedMiddleware): bool
    {
        foreach ($parsedMiddleware as $parsed) {
            if ($parsed['baseName'] === 'throttle') {
                return true;
            }
        }

        return false;
    }

    /**
     * Static version for testing.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    public static function hasIdempotencyMiddlewareStatic(array $parsedMiddleware): bool
    {
        foreach ($parsedMiddleware as $parsed) {
            $baseName = $parsed['baseName'];

            // Check for Idempotent class or alias
            if (str_contains($baseName, 'Idempotent')) {
                return true;
            }

            // Check for the common alias
            if ($baseName === 'idempotent') {
                return true;
            }
        }

        return false;
    }

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middleware = $routeInfo->route->gatherMiddleware();
        $parsedMiddleware = array_map([self::class, 'parseMiddleware'], $middleware);

        // Add 403 for token abilities and policies
        if ($this->hasTokenAbilityOrPolicyMiddleware($parsedMiddleware)) {
            $this->ensureResponse($operation, 403, 'Forbidden - insufficient permissions');
        }

        // Add 429 only for throttled routes (not global)
        if ($this->hasThrottleMiddleware($parsedMiddleware)) {
            $this->ensureRateLimitResponse($operation);
        }

        // Note: Archived resource 409 responses are now handled by ArchivedResourceErrorResponse attribute
        // on individual controller methods instead of middleware inspection

        // Add idempotency error responses
        if ($this->hasIdempotencyMiddleware($parsedMiddleware)) {
            $this->addIdempotencyErrorResponses($operation);
        }
    }

    /**
     * Check if route has token ability or policy middleware.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    private function hasTokenAbilityOrPolicyMiddleware(array $parsedMiddleware): bool
    {
        return self::hasTokenAbilityOrPolicyMiddlewareStatic($parsedMiddleware);
    }

    /**
     * Check if route has throttle middleware.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    private function hasThrottleMiddleware(array $parsedMiddleware): bool
    {
        return self::hasThrottleMiddlewareStatic($parsedMiddleware);
    }

    /**
     * Check if route has idempotency middleware.
     * Recognizes: Idempotent::class, Idempotent::using(...), idempotent alias, and custom header names.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    private function hasIdempotencyMiddleware(array $parsedMiddleware): bool
    {
        return self::hasIdempotencyMiddlewareStatic($parsedMiddleware);
    }

    /**
     * Ensure a response exists for the given status code.
     */
    private function ensureResponse(Operation $operation, int $status, string $description): void
    {
        if ($this->operationHasResponseCode($operation, $status)) {
            return;
        }

        $operation->responses[] = Response::make($status)->setDescription($description);
    }

    /**
     * Ensure a rate limit response with Retry-After header exists.
     */
    private function ensureRateLimitResponse(Operation $operation): void
    {
        if ($this->operationHasResponseCode($operation, 429)) {
            return;
        }

        $response = Response::make(429)
            ->setDescription('Too many requests');

        $response->addHeader('Retry-After', (new Header('Retry-After'))
            ->setDescription('Number of seconds to wait before making a new request.')
            ->setSchema(Schema::fromType(new IntegerType)));

        $operation->responses[] = $response;
    }

    /**
     * Add idempotency request header and error responses.
     * Note: Due to Scramble API limitations, headers are added via descriptions in this version.
     * The request header should be documented via controller attributes or request schemas.
     */
    private function addIdempotencyErrorResponses(Operation $operation): void
    {
        // Add 400 for missing Idempotency-Key with canonical envelope
        if (! $this->operationHasResponseCode($operation, 400)) {
            $response = Response::make(400)
                ->setDescription('Bad request - Idempotency-Key header is required')
                ->setContent('application/json', Schema::fromType(new StringType));

            $operation->responses[] = $response;
        }

        // Add 409 for idempotency in progress with Retry-After header
        if (! $this->operationHasResponseCode($operation, 409)) {
            $response = Response::make(409)
                ->setDescription('Conflict - Request with this Idempotency-Key is already being processed');

            $response->addHeader('Retry-After', (new Header('Retry-After'))
                ->setDescription('Number of seconds to wait before retrying the request.')
                ->setSchema(Schema::fromType(new IntegerType)));

            $operation->responses[] = $response;
        }

        // Add 422 for reused Idempotency-Key with different data
        if (! $this->operationHasResponseCode($operation, 422)) {
            $response = Response::make(422)
                ->setDescription('Validation error - Idempotency-Key was reused with different request data')
                ->setContent('application/json', Schema::fromType(new StringType));

            $operation->responses[] = $response;
        }

        // Add Idempotency-Replayed header to success responses
        $this->addIdempotencyReplayedHeader($operation);
    }

    /**
     * Add Idempotency-Replayed header to success responses.
     */
    private function addIdempotencyReplayedHeader(Operation $operation): void
    {
        // Find success responses (2xx status codes)
        foreach ($operation->responses as $response) {
            if ($response instanceof Reference) {
                continue; // Skip references, we can't modify shared responses
            }

            $statusCode = is_numeric($response->code) ? (int) $response->code : 0;

            // Add to 2xx responses (200, 201, 202, etc.)
            if ($statusCode >= 200 && $statusCode < 300) {
                $response->addHeader('Idempotency-Replayed', (new Header('Idempotency-Replayed'))
                    ->setDescription('Indicates whether this response is a replay of a previous request with the same Idempotency-Key. Set to "true" when replaying.')
                    ->setSchema(Schema::fromType(new StringType)));
            }
        }
    }

    /**
     * Check if operation already has a response with the given status code.
     * Only checks inline responses to avoid conflicts with shared references.
     */
    private function operationHasResponseCode(Operation $operation, int $status): bool
    {
        return collect($operation->responses ?? [])->contains(static function ($response) use ($status): bool {
            // Only check inline responses, skip references
            if ($response instanceof Reference) {
                return false;
            }

            return is_numeric($response->code) && (int) $response->code === $status;
        });
    }
}
