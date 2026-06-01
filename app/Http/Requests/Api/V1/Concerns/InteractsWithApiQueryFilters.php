<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

trait InteractsWithApiQueryFilters
{
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
