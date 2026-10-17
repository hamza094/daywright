<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectUpdateData;
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
             * Current project version for optimistic concurrency control.
             * Required to prevent silent overwrites from concurrent edits.
             *
             * @example 5
             */
            'version' => [
                'required',
                'integer',
                'min:1',
            ],
            /**
             * Updated project name.
             *
             * @example Website Redesign v2
             */
            'name' => [
                'sometimes', 'required', 'max:150', 'string', 'min:4',
            ],
            /**
             * Updated project description.
             *
             * @example Complete redesign of the company website with new branding and improved UX.
             */
            'about' => [
                'sometimes', 'required', 'string', 'min:15',
            ],
            /**
             * Updated project notes.
             *
             * @example Focus on mobile-first design approach
             */
            'notes' => [
                'sometimes', 'present', 'string', 'max:250',
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
