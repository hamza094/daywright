<?php

declare(strict_types=1);

namespace App\Documentation;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Str;

final class PublicApiQueries
{
    private const array UNSUPPORTED_PUBLIC_API_QUERY_PARAMETERS = ['include', 'fields', 'append'];

    private const string CURSOR_FOR_PAGINATION = 'Cursor for pagination';

    private const string NUMBER_OF_ITEMS_PER_PAGE = 'Number of items per page';

    private const string PAGE_NUMBER_FOR_PAGINATION = 'Page number for pagination';

    public function apply(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            foreach ($path->operations as $operation) {
                $this->pruneUnsupportedOperationQueryParameters($path->path, $operation);
            }
        }
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
                $this->makeQueryParameter('year', new IntegerType, 'Year for chart data', 2025),
                $this->makeQueryParameter('month', new IntegerType, 'Month for chart data (1-12)', 7, null, 1, 12),
            ],
            'v1/dashboard/activities', 'dashboard/activities' => [
                $this->makeQueryParameter('start_date', new StringType, 'Start date in ISO 8601 format', '2025-01-01', null, null, null, 'date', true),
                $this->makeQueryParameter('end_date', new StringType, 'End date in ISO 8601 format', '2025-12-31', null, null, null, 'date', true),
            ],
            'v1/projects', 'projects' => [
                $this->makeQueryParameter('page', new IntegerType, self::PAGE_NUMBER_FOR_PAGINATION, 1, 1, 1),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 6, 6, 1, 100),
            ],
            'v1/projects/{project}/activities', 'projects/{project}/activities' => [
                $this->makeQueryParameter('page', new IntegerType, self::PAGE_NUMBER_FOR_PAGINATION, 1, 1, 1),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 10, 10, 1, 100),
            ],
            'v1/projects/{project}/conversations', 'projects/{project}/conversations' => [
                $this->makeQueryParameter('cursor', new StringType, self::CURSOR_FOR_PAGINATION, 'eyJpZCI6MX0'),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 10, 10, 1, 100),
            ],
            'v1/users/me/invitations', 'users/me/invitations' => [
                $this->makeQueryParameter('page', new IntegerType, self::PAGE_NUMBER_FOR_PAGINATION, 1, 1, 1),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 10, 10, 1, 100),
            ],
            'v1/dashboard/tasks', 'dashboard/tasks' => [
                $this->makeQueryParameter('cursor', new StringType, self::CURSOR_FOR_PAGINATION, 'eyJpZCI6MX0'),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 15, 15, 1, 100),
            ],
            'v1/notifications', 'notifications' => [
                $this->makeQueryParameter('cursor', new StringType, self::CURSOR_FOR_PAGINATION, 'eyJpZCI6MX0'),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 25, 25, 1, 100),
            ],
            'v1/projects/{project}/tasks', 'projects/{project}/tasks' => [
                $this->makeQueryParameter('page', new IntegerType, self::PAGE_NUMBER_FOR_PAGINATION, 1, 1, 1),
                $this->makeQueryParameter('per_page', new IntegerType, self::NUMBER_OF_ITEMS_PER_PAGE, 20, 20, 1, 100),
            ],
            default => [],
        };
    }

    private function makeQueryParameter(
        string $name,
        IntegerType|StringType $type,
        ?string $description = null,
        mixed $example = null,
        mixed $default = null,
        ?int $min = null,
        ?int $max = null,
        ?string $format = null,
        bool $required = false,
    ): Parameter {
        // Apply constraints directly to the type object
        if ($min !== null && $type instanceof IntegerType) {
            $type->min = $min;
        }

        if ($max !== null && $type instanceof IntegerType) {
            $type->max = $max;
        }

        if ($format !== null && $type instanceof StringType) {
            $type->format = $format;
        }

        // Set default on the type before creating schema
        if ($default !== null) {
            $type->default($default);
        }

        $parameter = Parameter::make($name, 'query')
            ->setSchema(Schema::fromType($type));

        if ($required) {
            $parameter->required = true;
        }

        // Set description and example on the parameter itself
        if ($description !== null) {
            $parameter->description = $description;
        }

        if ($example !== null) {
            $parameter->example = $example;
        }

        return $parameter;
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
}
