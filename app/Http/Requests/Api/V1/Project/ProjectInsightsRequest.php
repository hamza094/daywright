<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectInsightsRequest extends FormRequest
{
    private const ALLOWED_SECTIONS = [
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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

        if (! is_array($sections) || empty($sections)) {
            return self::ALLOWED_SECTIONS;
        }

        return $sections;
    }

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
