<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Override;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ScrambleServiceProvider extends ServiceProvider
{
    private const array UNSUPPORTED_PUBLIC_API_QUERY_PARAMETERS = ['include', 'fields', 'append'];

    private const string VALIDATION_FAILED_MESSAGE = 'Validation failed.';

    #[Override]
    public function register(): void
    {
        // No bindings here; this provider configures Scramble/OpenAPI behavior.
    }

    public function boot(): void
    {
        Scramble::configure()
            ->withRuleTransformers([
                \App\Documentation\RuleTransformers\TaskSystemStatusRuleTransformer::class,
            ]);

        Scramble::resolveTagsUsing(fn (RouteInfo $routeInfo): array => [$this->resolvePublicApiTag($routeInfo)]);

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));

            $this->applyPublicApiTagMetadata($openApi);
            $this->applySharedPublicApiErrorResponses($openApi);
            $this->pruneUnsupportedQueryParametersFromDocs($openApi);

            $applicationUrl = rtrim(url('/'), '/');

            foreach ($openApi->servers as $server) {
                if (! str_starts_with($server->url, $applicationUrl)) {
                    continue;
                }

                $relativeUrl = Str::after($server->url, $applicationUrl);
                $server->url = $relativeUrl === '' ? '/' : $relativeUrl;
            }
        });

        Scramble::routes(function (Route $route): bool {
            if ($route->isFallback) {
                return false;
            }

            $uri = $route->uri();
            $middleware = $route->gatherMiddleware();

            // 1. Must be API v1
            if (! Str::startsWith($uri, 'api/v1')) {
                return false;
            }

            // 2. Automatically hides Admin, Token Mgmt, Zoom OAuth, 2FA Mgmt, and Password Update
            if (in_array('session.auth', $middleware, true) || in_array('firstParty.auth', $middleware, true)) {
                return false;
            }

            // 3. Exclude Webhooks, Browser Guest Session/OAuth, and Unreleased Features
            $excludedPrefixes = [
                'api/v1/webhooks',
                'api/v1/session',
                'api/v1/auth',
                'api/v1/twofactor',
                'api/v1/register',
                'api/v1/login',
                'api/v1/forgot-password',
                'api/v1/reset-password',
                'api/v1/email',
                'api/v1/logout',
                'api/v1/projects/{project}/export',
                'api/v1/projects/{project}/messages',
                'api/v1/projects/{project}/meetings',
            ];

            if (Str::startsWith($uri, $excludedPrefixes)) {
                return false;
            }

            // 4. Exclude singleton HTML form helper routes (/create, /edit)
            return ! Str::endsWith($uri, ['/create', '/edit']);
        });
    }

    private function resolvePublicApiTag(RouteInfo $routeInfo): string
    {
        $uri = $routeInfo->route->uri;

        return match (true) {
            Str::startsWith($uri, [
                'api/v1/register',
                'api/v1/login',
                'api/v1/logout',
                'api/v1/forgot-password',
                'api/v1/reset-password',
                'api/v1/email/',
                'api/v1/session/',
                'api/v1/auth/',
                'api/v1/twofactor/',
            ]) => 'Authentication',
            Str::startsWith($uri, 'api/v1/api-tokens') => 'API Tokens',
            Str::startsWith($uri, 'api/v1/users/me/subscription') => 'Subscription',
            Str::startsWith($uri, 'api/v1/dashboard/') => 'Dashboard',
            Str::startsWith($uri, 'api/v1/notifications') => 'Notifications',
            $uri === 'api/v1/stages' => 'Stages',
            Str::contains($uri, '/conversations') => 'Conversations',
            Str::contains($uri, '/tasks') || $uri === 'api/v1/task-statuses' => 'Tasks',
            Str::contains($uri, '/invitations') || Str::contains($uri, '/members/') || $uri === 'api/v1/users/me/invitations' => 'Invitations',
            Str::startsWith($uri, 'api/v1/users') => 'Users',
            Str::startsWith($uri, 'api/v1/projects') => 'Projects',
            default => 'Public API',
        };
    }

    private function publicApiErrorResponseName(int $status): ?string
    {
        return match ($status) {
            400 => 'PublicBadRequestError',
            401 => 'PublicUnauthenticatedError',
            403 => 'PublicForbiddenError',
            404 => 'PublicNotFoundError',
            405 => 'PublicMethodNotAllowedError',
            409 => 'PublicConflictError',
            422 => 'PublicValidationError',
            429 => 'PublicRateLimitError',
            500 => 'PublicInternalServerError',
            503 => 'PublicServiceUnavailableError',
            default => null,
        };
    }

    /**
     * @return array<string, array{description: string, weight: int}>
     */
    private function publicApiTagDefinitions(): array
    {
        return [
            'Authentication' => [
                'description' => 'Token, session, OAuth, password reset, email verification, and two-factor authentication endpoints.',
                'weight' => 10,
            ],
            'Users' => [
                'description' => 'Current-user, profile, avatar, and public user account management endpoints.',
                'weight' => 20,
            ],
            'Invitations' => [
                'description' => 'Personal and project invitation management endpoints.',
                'weight' => 30,
            ],
            'API Tokens' => [
                'description' => 'Personal access token management endpoints for bearer-token clients.',
                'weight' => 40,
            ],
            'Subscription' => [
                'description' => 'Subscription checkout, plan swap, cancellation, and subscription status endpoints.',
                'weight' => 50,
            ],
            'Dashboard' => [
                'description' => 'Released dashboard read models for charts, insights, tasks, activities, and projects.',
                'weight' => 60,
            ],
            'Notifications' => [
                'description' => 'Notification listing, bulk-read, status update, and deletion endpoints.',
                'weight' => 70,
            ],
            'Projects' => [
                'description' => 'Released public project CRUD, insights, limits, and activity endpoints.',
                'weight' => 80,
            ],
            'Stages' => [
                'description' => 'Shared project stage listing endpoints.',
                'weight' => 90,
            ],
            'Tasks' => [
                'description' => 'Released task CRUD, assignment, archive, restore, and task status endpoints.',
                'weight' => 100,
            ],
            'Conversations' => [
                'description' => 'Released project conversation list, create, attachment upload, and delete endpoints.',
                'weight' => 110,
            ],
        ];
    }

    private function applyPublicApiTagMetadata(OpenApi $openApi): void
    {
        $usedTags = collect($openApi->paths)
            ->flatMap(static fn ($path): array => array_values($path->operations))
            ->flatMap(static fn (Operation $operation): array => $operation->tags)
            ->unique()
            ->values();

        $openApi->tags = collect($this->publicApiTagDefinitions())
            ->filter(static fn (array $metadata, string $tag): bool => $usedTags->contains($tag))
            ->map(static function (array $metadata, string $tag): Tag {
                $tagDefinition = new Tag($tag, $metadata['description']);
                $tagDefinition->setAttribute('weight', $metadata['weight']);

                return $tagDefinition;
            })
            ->sortBy(static fn (Tag $tag): int => (int) $tag->getAttribute('weight', PHP_INT_MAX))
            ->values()
            ->all();
    }

    private function applySharedPublicApiErrorResponses(OpenApi $openApi): void
    {
        $this->registerSharedPublicApiErrorResponses($openApi->components);

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                $this->replaceOperationErrorResponsesWithSharedReferences($operation, $openApi->components);
                $this->ensureSharedPublicApiErrorResponse($operation, $openApi->components, 500);
            }
        }
    }

    private function pruneUnsupportedQueryParametersFromDocs(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                $this->pruneUnsupportedOperationQueryParameters($path->path, $operation);
            }
        }
    }

    private function replaceOperationErrorResponsesWithSharedReferences(Operation $operation, Components $components): void
    {
        $operation->responses = array_values(array_map(
            fn (Reference|Response $response): Reference|Response => $this->sharedPublicApiErrorResponseReference($response, $components) ?? $response,
            $operation->responses,
        ));
    }

    private function sharedPublicApiErrorResponseReference(Reference|Response $response, Components $components): ?Reference
    {
        $resolvedResponse = $response instanceof Reference ? $response->resolve() : $response;
        $responseCode = is_numeric($resolvedResponse->code) ? (int) $resolvedResponse->code : null;
        $responseName = $responseCode ? $this->publicApiErrorResponseName($responseCode) : null;

        if ($responseName === null) {
            return null;
        }

        return new Reference('responses', $responseName, $components);
    }

    private function pruneUnsupportedOperationQueryParameters(string $path, Operation $operation): void
    {
        $documentedFilterAliases = $this->documentedFilterAliases($operation);

        $operation->parameters = array_values(array_filter(
            $operation->parameters,
            fn (Parameter|Reference $parameter): bool => $this->shouldKeepPublicApiQueryParameter($parameter, $documentedFilterAliases),
        ));

        $this->appendMissingRequiredPublicApiQueryParameters($path, $operation);
    }

    /**
     * @param  array<int, string>  $documentedFilterAliases
     */
    private function shouldKeepPublicApiQueryParameter(Parameter|Reference $parameter, array $documentedFilterAliases): bool
    {
        $resolvedParameter = $parameter instanceof Reference ? $parameter->resolve() : $parameter;

        if (! $resolvedParameter instanceof Parameter || $resolvedParameter->in !== 'query') {
            return true;
        }

        if (in_array($resolvedParameter->name, self::UNSUPPORTED_PUBLIC_API_QUERY_PARAMETERS, true)) {
            return false;
        }

        return ! in_array($resolvedParameter->name, $documentedFilterAliases, true);
    }

    private function appendMissingRequiredPublicApiQueryParameters(string $path, Operation $operation): void
    {
        $documentedQueryParameters = $this->documentedQueryParameterNames($operation);

        foreach ($this->requiredPublicApiQueryParameters($path, $operation->method) as $requiredParameter) {
            if (in_array($requiredParameter->name, $documentedQueryParameters, true)) {
                continue;
            }

            $operation->parameters[] = $requiredParameter;
            $documentedQueryParameters[] = $requiredParameter->name;
        }
    }

    /**
     * @return array<int, string>
     */
    private function documentedQueryParameterNames(Operation $operation): array
    {
        return collect($operation->parameters)
            ->map(static fn ($parameter) => $parameter instanceof Reference ? $parameter->resolve() : $parameter)
            ->filter(static fn ($parameter): bool => $parameter instanceof Parameter && $parameter->in === 'query')
            ->map(static fn (Parameter $parameter): string => $parameter->name)
            ->values()
            ->all();
    }

    /**
     * @return array<int, Parameter>
     */
    private function requiredPublicApiQueryParameters(string $path, string $method): array
    {
        if ($method !== 'get') {
            return [];
        }

        $normalizedPath = trim($path, '/');
        $normalizedPath = Str::replaceStart('api/', '', $normalizedPath);

        return match ($normalizedPath) {
            'v1/dashboard/chart-data', 'dashboard/chart-data' => [
                $this->makeQueryParameter('year', new IntegerType),
                $this->makeQueryParameter('month', new IntegerType),
            ],
            'v1/dashboard/activities', 'dashboard/activities' => [
                $this->makeQueryParameter('start_date', new StringType),
                $this->makeQueryParameter('end_date', new StringType),
            ],
            'v1/projects', 'projects' => [
                $this->makeQueryParameter('page', new IntegerType),
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            'v1/projects/{project}/activities', 'projects/{project}/activities' => [
                $this->makeQueryParameter('page', new IntegerType),
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            'v1/projects/{project}/conversations', 'projects/{project}/conversations' => [
                $this->makeQueryParameter('cursor', new StringType),
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            'v1/users/me/invitations', 'users/me/invitations' => [
                $this->makeQueryParameter('page', new IntegerType),
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            'v1/dashboard/tasks', 'dashboard/tasks' => [
                $this->makeQueryParameter('cursor', new StringType),
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            'v1/notifications', 'notifications' => [
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            'v1/projects/{project}/tasks', 'projects/{project}/tasks' => [
                $this->makeQueryParameter('page', new IntegerType),
                $this->makeQueryParameter('per_page', new IntegerType),
            ],
            default => [],
        };
    }

    private function makeQueryParameter(string $name, IntegerType|StringType $type): Parameter
    {
        return Parameter::make($name, 'query')
            ->setSchema(Schema::fromType($type));
    }

    /**
     * @return array<int, string>
     */
    private function documentedFilterAliases(Operation $operation): array
    {
        return collect($operation->parameters)
            ->map(static fn ($parameter) => $parameter instanceof Reference ? $parameter->resolve() : $parameter)
            ->filter(static fn ($parameter): bool => $parameter instanceof Parameter && $parameter->in === 'query')
            ->map(static function (Parameter $parameter): ?string {
                $name = $parameter->name;

                $inner = Str::between($name, 'filter[', ']');

                /** @var string|null $inner */
                if ($inner === null || $inner === '') {
                    return null;
                }

                return $inner;
            })
            ->filter(static fn (?string $parameterName): bool => is_string($parameterName))
            ->values()
            ->all();
    }

    private function registerSharedPublicApiErrorResponses(Components $components): void
    {
        if (! $components->hasSchema('PublicApiErrorEnvelope')) {
            $components->addSchema('PublicApiErrorEnvelope', $this->makePublicApiErrorEnvelopeSchema());
        }

        if (! $components->hasSchema('PublicApiValidationErrorEnvelope')) {
            $components->addSchema('PublicApiValidationErrorEnvelope', $this->makePublicApiValidationErrorEnvelopeSchema());
        }

        foreach ($this->publicApiErrorResponseDefinitions() as $definition) {
            if (! $components->hasSchema($definition['schema'])) {
                $components->addSchema(
                    $definition['schema'],
                    $definition['status'] === SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY
                        ? $this->makePublicApiValidationErrorEnvelopeSchema()
                        : $this->makePublicApiErrorEnvelopeSchema(messageExample: $definition['message'], codeExample: $definition['code'], metaExample: $definition['meta'])
                );
            }

            $this->registerSharedPublicApiErrorResponse($components, $definition['response'], $definition['status'], $definition['description'], $definition['schema']);
        }
    }

    /**
     * @return array<int, array{response: string, schema: string, status: int, description: string, message: string, code: string, meta: array<string, mixed>}>
     */
    private function publicApiErrorResponseDefinitions(): array
    {
        return [
            [
                'response' => 'PublicBadRequestError',
                'schema' => 'PublicBadRequestErrorEnvelope',
                'status' => SymfonyResponse::HTTP_BAD_REQUEST,
                'description' => 'Bad request',
                'message' => 'The request could not be processed.',
                'code' => 'bad_request',
                'meta' => [],
            ],
            [
                'response' => 'PublicUnauthenticatedError',
                'schema' => 'PublicUnauthenticatedErrorEnvelope',
                'status' => SymfonyResponse::HTTP_UNAUTHORIZED,
                'description' => 'Unauthenticated',
                'message' => 'Authentication is required.',
                'code' => 'unauthenticated',
                'meta' => [],
            ],
            [
                'response' => 'PublicForbiddenError',
                'schema' => 'PublicForbiddenErrorEnvelope',
                'status' => SymfonyResponse::HTTP_FORBIDDEN,
                'description' => 'Forbidden',
                'message' => 'You are not authorized to perform this action.',
                'code' => 'forbidden',
                'meta' => [],
            ],
            [
                'response' => 'PublicNotFoundError',
                'schema' => 'PublicNotFoundErrorEnvelope',
                'status' => SymfonyResponse::HTTP_NOT_FOUND,
                'description' => 'Not found',
                'message' => 'Resource not found.',
                'code' => 'not_found',
                'meta' => [],
            ],
            [
                'response' => 'PublicMethodNotAllowedError',
                'schema' => 'PublicMethodNotAllowedErrorEnvelope',
                'status' => SymfonyResponse::HTTP_METHOD_NOT_ALLOWED,
                'description' => 'Method not allowed',
                'message' => 'Method not allowed.',
                'code' => 'method_not_allowed',
                'meta' => [],
            ],
            [
                'response' => 'PublicConflictError',
                'schema' => 'PublicConflictErrorEnvelope',
                'status' => SymfonyResponse::HTTP_CONFLICT,
                'description' => 'Conflict',
                'message' => 'The request conflicts with the current resource state.',
                'code' => 'conflict',
                'meta' => [],
            ],
            [
                'response' => 'PublicValidationError',
                'schema' => 'PublicApiValidationErrorEnvelope',
                'status' => SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY,
                'description' => 'Validation error',
                'message' => self::VALIDATION_FAILED_MESSAGE,
                'code' => 'validation_error',
                'meta' => [],
            ],
            [
                'response' => 'PublicRateLimitError',
                'schema' => 'PublicRateLimitErrorEnvelope',
                'status' => SymfonyResponse::HTTP_TOO_MANY_REQUESTS,
                'description' => 'Too many requests',
                'message' => 'Too many requests. Please try again later.',
                'code' => 'rate_limited',
                'meta' => ['retry_after_seconds' => 42],
            ],
            [
                'response' => 'PublicInternalServerError',
                'schema' => 'PublicInternalServerErrorEnvelope',
                'status' => SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR,
                'description' => 'Internal server error',
                'message' => 'An unexpected server error occurred.',
                'code' => 'internal_server_error',
                'meta' => [],
            ],
            [
                'response' => 'PublicServiceUnavailableError',
                'schema' => 'PublicServiceUnavailableErrorEnvelope',
                'status' => SymfonyResponse::HTTP_SERVICE_UNAVAILABLE,
                'description' => 'Service unavailable',
                'message' => 'The service is temporarily unavailable.',
                'code' => 'service_unavailable',
                'meta' => [],
            ],
        ];
    }

    private function registerSharedPublicApiErrorResponse(Components $components, string $name, int $status, string $description, string $schemaName): void
    {
        if (array_key_exists($name, $components->responses)) {
            return;
        }

        $components->responses[$name] = Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', new Reference('schemas', $schemaName, $components));
    }

    private function ensureSharedPublicApiErrorResponse(Operation $operation, Components $components, int $status): void
    {
        if ($this->operationHasResponseCode($operation, $status)) {
            return;
        }

        $responseName = $this->publicApiErrorResponseName($status);

        if ($responseName === null) {
            return;
        }

        $operation->responses ??= [];
        $operation->responses[] = new Reference('responses', $responseName, $components);
    }

    private function operationHasResponseCode(Operation $operation, int $status): bool
    {
        return collect($operation->responses ?? [])->contains(static function ($response) use ($status): bool {
            $resolvedResponse = $response instanceof Reference ? $response->resolve() : $response;

            return is_numeric($resolvedResponse->code) && (int) $resolvedResponse->code === $status;
        });
    }

    /**
     * @param  array<string,mixed>  $metaExample
     */
    private function makePublicApiErrorEnvelopeSchema(
        string $messageExample = 'Resource not found.',
        string $codeExample = 'not_found',
        array $metaExample = [],
    ): Schema {
        $validationErrors = (new ObjectType)
            ->setDescription('Field-level validation details when available.')
            ->additionalProperties((new ArrayType)->setItems(new StringType))
            ->example([]);

        $meta = (new ObjectType)
            ->setDescription('Structured error context when available.')
            ->example($metaExample);

        return Schema::fromType(
            (new ObjectType)
                ->addProperty('message', (new StringType)->setDescription('Safe human-readable error message.')->example($messageExample))
                ->addProperty('code', (new StringType)->setDescription('Stable machine-readable error code.')->example($codeExample))
                ->addProperty('errors', $validationErrors)
                ->addProperty('meta', $meta)
                ->setRequired(['message', 'code', 'errors', 'meta'])
                ->example([
                    'message' => $messageExample,
                    'code' => $codeExample,
                    'errors' => [],
                    'meta' => $metaExample,
                ])
        );
    }

    private function makePublicApiValidationErrorEnvelopeSchema(): Schema
    {
        $validationErrors = (new ObjectType)
            ->setDescription('Field-level validation details keyed by input name.')
            ->additionalProperties((new ArrayType)->setItems(new StringType))
            ->example([
                'email' => ['The provided credentials are incorrect.'],
            ]);

        $meta = (new ObjectType)
            ->setDescription('Structured error context when available.');

        return Schema::fromType(
            (new ObjectType)
                ->addProperty('message', (new StringType)->setDescription('Safe human-readable error message.')->example(self::VALIDATION_FAILED_MESSAGE))
                ->addProperty('code', (new StringType)->setDescription('Stable machine-readable error code.')->example('validation_error'))
                ->addProperty('errors', $validationErrors)
                ->addProperty('meta', $meta->example([]))
                ->setRequired(['message', 'code', 'errors', 'meta'])
                ->example([
                    'message' => self::VALIDATION_FAILED_MESSAGE,
                    'code' => 'validation_error',
                    'errors' => [
                        'email' => ['The provided credentials are incorrect.'],
                    ],
                    'meta' => [],
                ])
        );
    }
}
