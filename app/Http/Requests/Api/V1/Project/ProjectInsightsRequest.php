<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectInsightsQueryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ProjectInsightsRequest extends FormRequest
{
    private const array ALLOWED_SECTIONS = [
        'health',
        'task-health',
        'collaboration',
        'risk',
        'stage',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): ProjectInsightsQueryData
    {
        return ProjectInsightsQueryData::fromArray($this->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Array of insight sections to include. Omitting this parameter returns all sections.
             * Allowed values: health, task-health, collaboration, risk, stage.
             *
             * @var array<int, 'health'|'task-health'|'collaboration'|'risk'|'stage'>
             *
             * @example ["health","risk"]
             */
            'sections' => ['sometimes', 'array'],
            'sections.*' => ['string', Rule::in(self::ALLOWED_SECTIONS)],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getSections(): array
    {
        $sections = $this->validated('sections', []);

        if (! is_array($sections) || $sections === []) {
            return self::ALLOWED_SECTIONS;
        }

        return $sections;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $sections = $this->input('sections', []);

        if (! is_array($sections)) {
            return;
        }

        $normalized = [];

        foreach ($sections as $s) {
            $s = trim((string) $s);

            if ($s === '') {
                continue;
            }

            if (! in_array($s, $normalized, true)) {
                $normalized[] = $s;
            }
        }

        $this->merge(['sections' => $normalized]);
    }
}
