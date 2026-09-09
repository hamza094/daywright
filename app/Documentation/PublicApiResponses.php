<?php

declare(strict_types=1);

namespace App\Documentation;

use App\Documentation\Attributes\ApiError;
use App\Exceptions\Support\ErrorCode;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use ReflectionAttribute;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class PublicApiResponses
{
    private const string VALIDATION_FAILED_MESSAGE = 'Validation failed.';

    public function __construct(
        private PublicApiRouteCatalog $routeCatalog,
    ) {}

    public function apply(OpenApi $openApi): void
    {
        $this->applySharedErrorResponses($openApi);
        $this->applyExplicitPublicBusinessErrorResponses($openApi);
    }

    private function applySharedErrorResponses(OpenApi $openApi): void
    {
        $this->registerSharedPublicApiErrorResponses($openApi->components);
        $this->normalizeSharedErrorResponses($openApi->components);

        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                $this->replaceOperationErrorResponsesWithSharedReferences($operation, $openApi->components);
                // 500 is global fallback for all operations
                $this->ensureSharedPublicApiErrorResponse($operation, $openApi->components, 500);
                // 429 is now only added by PublicApiMiddlewareResponses for throttled routes
                // Removed global 429 injection to match actual middleware behavior
            }
        }
    }

    private function applyExplicitPublicBusinessErrorResponses(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $method => $operation) {
                $route = $this->routeCatalog->findForOperation($path->path, $method);

                if (! $route instanceof Route) {
                    continue;
                }

                $reflectionAction = (new RouteInfo($route, mb_strtoupper($method)))->reflectionAction();

                if (! $reflectionAction) {
                    continue;
                }

                foreach ($reflectionAction->getAttributes(ApiError::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $error = $attribute->newInstance();
                    $definition = ErrorCode::get($error->code);

                    if ($definition === null) {
                        continue;
                    }

                    $this->applyBusinessErrorResponse($openApi, $operation, $definition, $error->code);
                }
            }
        }
    }

    /**
     * @param  array{status: int, message: string, description: string, meta_schema: array<string, string>, example: array<string, mixed>}  $definition
     */
    private function applyBusinessErrorResponse(OpenApi $openApi, Operation $operation, array $definition, string $code): void
    {
        $description = sprintf(
            '%s Machine-readable code: %s.',
            $definition['description'],
            $code,
        );

        $index = $this->findResponseIndex($operation, $definition['status']);

        if ($index !== null) {
            $this->updateExistingBusinessErrorResponse($operation, $index, $definition, $code, $description);

            return;
        }

        $this->appendBusinessErrorResponse($openApi, $operation, $definition, $code, $description);
    }

    private function findResponseIndex(Operation $operation, int $status): ?int
    {
        foreach ($operation->responses as $index => $candidate) {
            $response = $candidate instanceof Reference ? $candidate->resolve() : $candidate;

            if (is_numeric($response->code) && (int) $response->code === $status) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array{status: int, message: string, description: string, meta_schema: array<string, string>, example: array<string, mixed>}  $definition
     */
    private function updateExistingBusinessErrorResponse(
        Operation $operation,
        int $index,
        array $definition,
        string $code,
        string $description,
    ): void {
        $candidate = $operation->responses[$index];
        $response = clone $candidate instanceof Reference ? $candidate->resolve() : $candidate;

        if (! str_contains((string) $response->description, $code)) {
            $response->setDescription(rtrim((string) $response->description, '.').'. '.$description);
        }

        $examples = $response->getExtensionProperty('error-examples');
        $examples = is_array($examples) ? $examples : [];
        $examples[$code] = $definition['example'];
        $response->setExtensionProperty('error-examples', $examples);

        if (! $response->hasExtensionProperty('error-example')) {
            $response->setExtensionProperty('error-example', $definition['example']);
        }

        $operation->responses[$index] = $response;
        $this->removeDuplicateResponses($operation, $index, $definition['status']);
    }

    /**
     * @param  array{status: int, message: string, description: string, meta_schema: array<string, string>, example: array<string, mixed>}  $definition
     */
    private function appendBusinessErrorResponse(
        OpenApi $openApi,
        Operation $operation,
        array $definition,
        string $code,
        string $description,
    ): void {
        $response = Response::make($definition['status'])
            ->setDescription($description);
        $this->normalizeErrorResponseToCanonicalEnvelope($response, $definition['status'], $openApi->components);
        $response->setExtensionProperty('error-examples', [$code => $definition['example']]);
        $response->setExtensionProperty('error-example', $definition['example']);
        $operation->responses[] = $response;
    }

    private function removeDuplicateResponses(Operation $operation, int $keepIndex, int $status): void
    {
        $operation->responses = array_values(array_filter(
            $operation->responses,
            static function ($candidate, int $candidateIndex) use ($keepIndex, $status): bool {
                if ($candidateIndex === $keepIndex) {
                    return true;
                }

                $response = $candidate instanceof Reference ? $candidate->resolve() : $candidate;

                return ! is_numeric($response->code) || (int) $response->code !== $status;
            },
            ARRAY_FILTER_USE_BOTH,
        ));
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
        $definitions = [];

        foreach (ErrorCode::all() as $code => $definition) {
            // Skip non-public service-specific errors
            if ($code === ErrorCode::DASHBOARD_SERVICE_ERROR) {
                continue;
            }

            $responseName = $this->publicApiErrorResponseName($definition['status']);
            if ($responseName === null) {
                continue;
            }

            $schemaName = match ($code) {
                ErrorCode::BAD_REQUEST => 'PublicBadRequestErrorEnvelope',
                ErrorCode::UNAUTHENTICATED => 'PublicUnauthenticatedErrorEnvelope',
                ErrorCode::FORBIDDEN => 'PublicForbiddenErrorEnvelope',
                ErrorCode::NOT_FOUND => 'PublicNotFoundErrorEnvelope',
                ErrorCode::METHOD_NOT_ALLOWED => 'PublicMethodNotAllowedErrorEnvelope',
                ErrorCode::CONFLICT => 'PublicConflictErrorEnvelope',
                ErrorCode::VALIDATION_ERROR => 'PublicApiValidationErrorEnvelope',
                ErrorCode::RATE_LIMITED => 'PublicRateLimitErrorEnvelope',
                ErrorCode::TOKEN_RATE_LIMITED => 'PublicRateLimitErrorEnvelope',
                ErrorCode::INTERNAL_SERVER_ERROR => 'PublicInternalServerErrorEnvelope',
                ErrorCode::SERVICE_UNAVAILABLE => 'PublicServiceUnavailableErrorEnvelope',
                // Business error codes - use their status-based envelope
                ErrorCode::PROJECT_ARCHIVED => 'PublicConflictErrorEnvelope',
                ErrorCode::TASK_ARCHIVED => 'PublicConflictErrorEnvelope',
                ErrorCode::PLAN_LIMIT_EXCEEDED => 'PublicForbiddenErrorEnvelope',
                ErrorCode::SUBSCRIPTION_REQUIRED => 'PublicForbiddenErrorEnvelope',
                ErrorCode::TASK_NOT_TRASHED => 'PublicForbiddenErrorEnvelope',
                ErrorCode::INVALID_STATE_TRANSITION => 'PublicApiValidationErrorEnvelope',
                // Infrastructure errors
                ErrorCode::STORAGE_ERROR => 'PublicInternalServerErrorEnvelope',
                ErrorCode::DATABASE_ERROR => 'PublicInternalServerErrorEnvelope',
                default => null,
            };

            if ($schemaName === null) {
                continue;
            }

            $definitions[] = [
                'response' => $responseName,
                'schema' => $schemaName,
                'status' => $definition['status'],
                'description' => $definition['description'],
                'message' => $definition['message'],
                'code' => $code,
                'meta' => $definition['example']['meta'] ?? [],
            ];
        }

        return $definitions;
    }

    private function registerSharedPublicApiErrorResponse(Components $components, string $name, int $status, string $description, string $schemaName): void
    {
        if (array_key_exists($name, $components->responses)) {
            return;
        }

        $response = Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', new Reference('schemas', $schemaName, $components));

        // Add Retry-After header to 429 rate limit response
        if ($status === SymfonyResponse::HTTP_TOO_MANY_REQUESTS) {
            $response->addHeader('Retry-After', (new \Dedoc\Scramble\Support\Generator\Header('Retry-After'))
                ->setDescription('Number of seconds to wait before making a new request.')
                ->setSchema(Schema::fromType(new \Dedoc\Scramble\Support\Generator\Types\IntegerType)));
        }

        $components->responses[$name] = $response;
    }

    private function normalizeSharedErrorResponses(Components $components): void
    {
        // Normalize Laravel's default exception responses to use canonical envelopes
        $defaultResponses = [
            'AuthenticationException' => 401,
            'ModelNotFoundException' => 404,
            'AuthorizationException' => 403,
            'ValidationException' => 422,
        ];

        foreach ($defaultResponses as $responseName => $status) {
            if (array_key_exists($responseName, $components->responses)) {
                $response = $components->responses[$responseName];
                $this->normalizeErrorResponseToCanonicalEnvelope($response, $status, $components);
            }
        }
    }

    private function replaceOperationErrorResponsesWithSharedReferences(Operation $operation, Components $components): void
    {
        $operation->responses = array_values(array_map(
            fn (Reference|Response $response): Reference|Response => $this->mergeCanonicalSchemaIntoErrorResponse($response, $components),
            $operation->responses,
        ));
    }

    private function mergeCanonicalSchemaIntoErrorResponse(Reference|Response $response, Components $components): Reference|Response
    {
        $resolvedResponse = $response instanceof Reference ? $response->resolve() : $response;
        $responseCode = is_numeric($resolvedResponse->code) ? (int) $resolvedResponse->code : null;

        // Only process error responses (4xx and 5xx)
        if ($responseCode === null || ($responseCode < 400 || $responseCode >= 600)) {
            return $response;
        }

        // Normalize the response to use canonical envelope
        $this->normalizeErrorResponseToCanonicalEnvelope($resolvedResponse, $responseCode, $components);

        return $resolvedResponse;
    }

    private function normalizeErrorResponseToCanonicalEnvelope(Response $response, int $status, Components $components): void
    {
        // Determine the canonical schema name
        $schemaName = match ($status) {
            422 => 'PublicApiValidationErrorEnvelope',
            429 => 'PublicRateLimitErrorEnvelope',
            default => 'PublicApiErrorEnvelope',
        };

        if (! $components->hasSchema($schemaName)) {
            return;
        }

        // Get the canonical schema reference
        $canonicalSchema = new Reference('schemas', $schemaName, $components);

        // Replace the JSON schema with canonical envelope (even if one exists)
        $response->setContent('application/json', $canonicalSchema);
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
            ->example((object) []);

        $meta = (new ObjectType)
            ->setDescription('Structured error context when available.')
            ->example($metaExample);

        $completeExample = [
            'message' => $messageExample,
            'code' => $codeExample,
            'errors' => [],
            'meta' => $metaExample,
        ];

        return Schema::fromType(
            (new ObjectType)
                ->addProperty('message', (new StringType)->setDescription('Safe human-readable error message.')->example($messageExample))
                ->addProperty('code', (new StringType)->setDescription('Stable machine-readable error code.')->example($codeExample))
                ->addProperty('errors', $validationErrors)
                ->addProperty('meta', $meta)
                ->setRequired(['message', 'code', 'errors', 'meta'])
                ->example($completeExample)
        );
    }

    private function makePublicApiValidationErrorEnvelopeSchema(): Schema
    {
        $validationErrors = (new ObjectType)
            ->setDescription('Field-level validation details keyed by input name.')
            ->additionalProperties((new ArrayType)->setItems(new StringType))
            ->example([
                'email' => ['The email field is required.'],
            ]);

        $meta = (new ObjectType)
            ->setDescription('Structured error context when available.')
            ->example([]);

        return Schema::fromType(
            (new ObjectType)
                ->addProperty('message', (new StringType)->setDescription('Safe human-readable error message.')->example(self::VALIDATION_FAILED_MESSAGE))
                ->addProperty('code', (new StringType)->setDescription('Stable machine-readable error code.')->example('validation_error'))
                ->addProperty('errors', $validationErrors)
                ->addProperty('meta', $meta)
                ->setRequired(['message', 'code', 'errors', 'meta'])
                ->example([
                    'message' => self::VALIDATION_FAILED_MESSAGE,
                    'code' => 'validation_error',
                    'errors' => (object) [
                        'email' => ['The email field is required.'],
                    ],
                    'meta' => (object) [],
                ])
        );
    }
}
