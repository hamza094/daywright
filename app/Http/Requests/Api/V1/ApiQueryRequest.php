<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

abstract class ApiQueryRequest extends FormRequest
{
    /**
     * @return array<int, AllowedFilter|string>
     */
    public static function allowedFilters(): array
    {
        return [];
    }

    /**
     * @return array<int, AllowedSort|string>
     */
    public static function allowedSorts(): array
    {
        return [];
    }

    /**
     * @return array<int, AllowedSort|string>
     */
    public static function defaultSorts(): array
    {
        return [];
    }

    /**
     * @return array<int, AllowedInclude|string>
     */
    public static function allowedIncludes(): array
    {
        return [];
    }

    public function pageNumber(): int
    {
        return (int) $this->validated('page', 1);
    }

    /**
     * @return array<int, string>
     */
    protected function supportedTopLevelQueryParameters(): array
    {
        return [];
    }

    protected function enforcesSupportedTopLevelQueryParameters(): bool
    {
        return $this->supportedTopLevelQueryParameters() !== [];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->enforcesSupportedTopLevelQueryParameters()) {
                return;
            }

            foreach ($this->unsupportedTopLevelQueryParameters() as $queryParameter) {
                if ($validator->errors()->has($queryParameter)) {
                    continue;
                }

                $validator->errors()->add(
                    $queryParameter,
                    $this->unsupportedTopLevelQueryParameterMessage($queryParameter),
                );
            }
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function unsupportedQueryParameterRules(): array
    {
        return [
            'include' => ['prohibited'],
            'fields' => ['prohibited'],
            'append' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function unsupportedQueryParameterMessages(): array
    {
        return [
            'include.prohibited' => 'The include parameter is not supported for this endpoint.',
            'fields.prohibited' => 'The fields parameter is not supported for this endpoint.',
            'append.prohibited' => 'The append parameter is not supported for this endpoint.',
        ];
    }

    /**
     * @param  array<int, string>  $filterKeys
     * @return array<string, array<int, string>>
     */
    protected function topLevelFilterAliasRules(array $filterKeys): array
    {
        $rules = [];

        foreach ($filterKeys as $filterKey) {
            $rules[$filterKey] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @param  array<int, string>  $filterKeys
     * @return array<string, string>
     */
    protected function topLevelFilterAliasMessages(array $filterKeys): array
    {
        $messages = [];

        foreach ($filterKeys as $filterKey) {
            $messages["{$filterKey}.prohibited"] = "Use filter[{$filterKey}] instead of the top-level {$filterKey} parameter.";
        }

        return $messages;
    }

    /**
     * @return array<int, string>
     */
    protected function unsupportedTopLevelQueryParameters(): array
    {
        $supportedParameters = $this->supportedTopLevelQueryParameters();

        return array_values(array_diff(array_keys($this->query()), $supportedParameters));
    }

    protected function unsupportedTopLevelQueryParameterMessage(string $queryParameter): string
    {
        $unsupportedParameterMessages = $this->unsupportedQueryParameterMessages();

        return $unsupportedParameterMessages["{$queryParameter}.prohibited"]
            ?? "The {$queryParameter} query parameter is not supported for this endpoint.";
    }

    /**
     * @return array<int, string>
     */
    protected function pageRule(string $presence = 'sometimes'): array
    {
        return [$presence, 'integer', 'min:1'];
    }

    /**
     * @return array<int, string>
     */
    protected function perPageRule(string $presence = 'sometimes', int $min = 1, int $max = 100): array
    {
        return [$presence, 'integer', "min:{$min}", "max:{$max}"];
    }

    protected function perPageValue(int $default): int
    {
        return (int) $this->validated('per_page', $default);
    }

    protected function sortValue(string $default): string
    {
        return (string) $this->validated('sort', $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedFilters(?string $singleFilterKey = null): array
    {
        $filterInput = $this->input('filter', []);

        if (is_array($filterInput)) {
            return $filterInput;
        }

        if ($singleFilterKey !== null && is_string($filterInput) && $filterInput !== '') {
            return [$singleFilterKey => $filterInput];
        }

        return [];
    }

    /**
     * Ensure boolean-like nested filter keys are normalized to proper booleans
     * when possible.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $booleanKeys
     * @return array<string, mixed>
     */
    protected function normalizeBooleanFilters(array $filters, array $booleanKeys): array
    {
        foreach ($booleanKeys as $booleanKey) {
            if (! array_key_exists($booleanKey, $filters)) {
                continue;
            }

            $filters[$booleanKey] = $this->normalizeBooleanValue($filters[$booleanKey]);
        }

        return $filters;
    }

    /**
     * Lowercase the value for the given filter key if present and a string.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function lowercaseFilterValue(array $filters, string $key): array
    {
        $filterValue = $filters[$key] ?? null;

        if (is_string($filterValue)) {
            $filters[$key] = mb_strtolower($filterValue);
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function mergeFilters(array $filters): void
    {
        if ($this->has('filter') && ! is_array($this->input('filter'))) {
            return;
        }

        $this->merge(['filter' => $filters]);
    }

    protected function normalizeBooleanValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalizedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalizedValue ?? $value;
    }
}
