<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectUpdateData;
use Closure;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('ProjectUpdateRequestData')]
class ProjectUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): ProjectUpdateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProjectUpdateData::fromArray($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {

        return [
            /**
             * Updated project name. Must be different from the current name.
             *
             * @example Website Redesign v2
             */
            'name' => [
                'sometimes', 'required', 'max:150', 'string', 'min:4',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === $this->project->name) {
                        $fail("The {$attribute} must be different from the current name.");
                    }
                },
            ],
            /**
             * Updated project description. Must be different from the current about description.
             *
             * @example Complete redesign of the company website with new branding and improved UX.
             */
            'about' => [
                'sometimes', 'required', 'min:15',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === $this->project->about) {
                        $fail("The {$attribute} must be different from the current about description.");
                    }
                },
            ],
            /**
             * Updated project notes. Must be different from the current project notes.
             *
             * @example Focus on mobile-first design approach
             */
            'notes' => [
                'sometimes', 'present', 'max:250',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->has('notes') && $value === $this->project->notes) {
                        $fail("The {$attribute} must be different from the current project notes.");
                    }
                },
            ],
        ];
    }

    #[Override]
    public function messages(): array
    {
        return [
            'name.required' => 'Project name required.',
            'about.required' => 'Project about required.',
            'name.max' => 'Project name is too long.',
        ];
    }
}
