<?php

declare(strict_types=1);

namespace App\Documentation\Transformers;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Routing\Router;
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

    /**
     * Static version for testing.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    public static function hasThrottleMiddlewareStatic(array $parsedMiddleware): bool
    {
        foreach ($parsedMiddleware as $parsed) {
            if (in_array($parsed['baseName'], [
                'throttle',
                'ThrottleRequests',
                'ThrottleRequestsWithRedis',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        // Resolve middleware through Laravel's router to handle middleware groups
        $resolvedMiddleware = $this->resolveRouteMiddleware($routeInfo->route);
        $parsedMiddleware = array_map([self::class, 'parseMiddleware'], $resolvedMiddleware);

        // Add 403 for token abilities and policies
        if ($this->hasTokenAbilityOrPolicyMiddleware($parsedMiddleware)) {
            $this->ensureResponse($operation, 403, 'Forbidden - insufficient permissions');
        }

        // Add 429 only for throttled routes (now using resolved middleware)
        if ($this->hasThrottleMiddleware($parsedMiddleware)) {
            $this->ensureRateLimitResponse($operation);
        }

        // The package only processes POST, PUT, and PATCH requests.
        if ($this->hasIdempotencyMiddleware($parsedMiddleware) && $this->isIdempotentOperation($operation)) {
            $this->addIdempotencyContract($operation, $parsedMiddleware);
        }

        // Add archived resource 409 responses based on route binding behavior
        $this->addArchivedResourceErrorResponse($operation, $routeInfo);

    }

    /**
     * Resolve route middleware through Laravel's router to handle middleware groups.
     * This ensures inherited middleware (like throttle:api from the API group) is properly detected.
     *
     * @return array<int, string>
     */
    private function resolveRouteMiddleware(IlluminateRoute $route): array
    {
        $middleware = app(Router::class)->gatherRouteMiddleware($route);

        return $middleware;
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
     * Check if route has throttle middleware.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    private function hasThrottleMiddleware(array $parsedMiddleware): bool
    {
        return self::hasThrottleMiddlewareStatic($parsedMiddleware);
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
     * The package bypasses idempotency for methods other than POST, PUT, and PATCH.
     */
    private function isIdempotentOperation(Operation $operation): bool
    {
        return in_array(mb_strtoupper($operation->method), ['POST', 'PUT', 'PATCH'], true);
    }

    /**
     * Add the request header, replay header, and package error responses.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     */
    private function addIdempotencyContract(Operation $operation, array $parsedMiddleware): void
    {
        $options = $this->idempotencyOptions($parsedMiddleware);

        if (! $this->operationHasParameter($operation, 'header', $options['header'])) {
            $operation->addParameters([
                Parameter::make($options['header'], 'header')
                    ->required($options['required'])
                    ->description(($options['required'] ? 'Required' : 'Optional').' unique key used to prevent duplicate requests.')
                    ->setSchema(Schema::fromType(new StringType))
                    ->example('req_abc123'),
            ]);
        }

        $this->addIdempotencyErrorResponses($operation, $options['required']);
    }

    /**
     * Read the effective options from Idempotent::using(...) or package config.
     *
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $parsedMiddleware
     * @return array{header: string, required: bool}
     */
    private function idempotencyOptions(array $parsedMiddleware): array
    {
        foreach ($parsedMiddleware as $parsed) {
            if (! str_contains($parsed['baseName'], 'Idempotent') && $parsed['baseName'] !== 'idempotent') {
                continue;
            }

            $parameters = $parsed['parameters'];
            $required = $parameters[1] ?? null;

            return [
                'header' => $parameters[3] ?? config()->string('idempotency.header'),
                'required' => $required === null
                    ? config()->boolean('idempotency.required')
                    : in_array(mb_strtolower($required), ['1', 'true', 'on', 'yes'], true),
            ];
        }

        return [
            'header' => config()->string('idempotency.header'),
            'required' => config()->boolean('idempotency.required'),
        ];
    }

    private function addIdempotencyErrorResponses(Operation $operation, bool $required): void
    {
        if ($required && ! $this->operationHasResponseCode($operation, 400)) {
            $response = Response::make(400)
                ->setDescription('Bad request (bad_request) - the configured idempotency header is required.')
                ->setContent('application/json', Schema::fromType(new StringType));

            $operation->responses[] = $response;
        }

        if (! $this->operationHasResponseCode($operation, 409)) {
            $response = Response::make(409)
                ->setDescription('Conflict (conflict) - a request with this idempotency key is already being processed.');

            $response->addHeader('Retry-After', (new Header('Retry-After'))
                ->setDescription('Number of seconds to wait before retrying the request.')
                ->setSchema(Schema::fromType(new IntegerType)));

            $operation->responses[] = $response;
        }

        if (! $this->operationHasResponseCode($operation, 422)) {
            $response = Response::make(422)
                ->setDescription('Validation error (validation_error) - the idempotency key was reused with different request data.')
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

    private function operationHasParameter(Operation $operation, string $in, string $name): bool
    {
        foreach ($operation->parameters as $parameter) {
            if ($parameter instanceof Parameter && $parameter->in === $in && $parameter->name === $name) {
                return true;
            }
        }

        return false;
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

    /**
     * Add archived resource 409 responses based on route binding behavior.
     * Inspects route parameters and whether trashed bindings are allowed.
     */
    private function addArchivedResourceErrorResponse(Operation $operation, RouteInfo $routeInfo): void
    {
        $route = $routeInfo->route;

        // Check if route allows trashed bindings using Laravel's API
        if ($route->allowsTrashedBindings()) {
            // If withTrashed() is present, archived resources are accepted, no 409 needed
            return;
        }

        $paramNames = $route->parameterNames();
        $hasProjectParam = in_array('project', $paramNames, true);
        $hasTaskParam = in_array('task', $paramNames, true);

        if (! $hasProjectParam && ! $hasTaskParam) {
            return;
        }

        // Build description based on which parameters are present
        $description = match (true) {
            $hasProjectParam && $hasTaskParam => 'Conflict - Project or task is archived (project_archived or task_archived)',
            $hasProjectParam => 'Conflict - Project is archived (project_archived)',
            default => 'Conflict - Task is archived (task_archived)',
        };

        foreach ($operation->responses as $index => $candidate) {
            $response = $candidate instanceof Reference ? clone $candidate->resolve() : $candidate;

            if (! is_numeric($response->code) || (int) $response->code !== 409) {
                continue;
            }

            if (! str_contains($response->description, $description)) {
                $response->setDescription(rtrim($response->description, '.').'. '.$description);
            }

            $operation->responses[$index] = $response;

            return;
        }

        $operation->responses[] = Response::make(409)->setDescription($description);
    }
}
