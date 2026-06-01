<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Validation\Validator;

trait InteractsWithUnsupportedApiQueryParameters
{
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
}
