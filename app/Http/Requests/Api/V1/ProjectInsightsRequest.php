<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ProjectInsightsRequest extends FormRequest
{
    public const VALID_SECTIONS = [
        'health', 'task-health', 'collaboration', 'risk', 'stage',
    ];

    public const DEFAULT_SECTIONS = self::VALID_SECTIONS;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $in = Rule::in(self::VALID_SECTIONS);

        return [
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['string', $in],
        ];
    }

    /**
     * @return array<int,string>
     */
    public function getSections(): array
    {
        return $this->validated()['sections'] ?? self::DEFAULT_SECTIONS;
    }

    /**
     * Custom validation messages for the request.
     *
     * @return array<string,string>
     */
    #[Override]
    public function messages(): array
    {
        $allowed = implode(', ', self::VALID_SECTIONS);

        return [
            'sections.array' => 'The sections field must be an array. Use sections[]=health&sections[]=risk.',
            'sections.*.string' => 'Each section must be a string.',
            'sections.*.in' => "Invalid section selected. Allowed values: {$allowed}.",
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $rawSections = $this->input('sections');

        if ($rawSections === null) {
            $this->merge(['sections' => self::DEFAULT_SECTIONS]);

            return;
        }

        if (is_array($rawSections)) {
            $this->merge(['sections' => $this->normalizeSectionsArray($rawSections)]);
        }
    }

    /**
     * Normalize a raw sections array to a cleaned list of strings
     *
     * @param  array<int,mixed>  $sections
     * @return array<int,string>
     */
    private function normalizeSectionsArray(array $sections): array
    {
        return collect($sections)
            ->map(fn ($value): ?string => is_scalar($value) ? trim((string) $value) : null)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->unique(null, true)
            ->values()
            ->all();
    }
}
