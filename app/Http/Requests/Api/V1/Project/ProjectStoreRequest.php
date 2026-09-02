<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectCreateData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;

#[SchemaName('ProjectStoreRequestData')]
class ProjectStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): ProjectCreateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProjectCreateData::fromArray($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Project name. Must be between 4 and 150 characters.
             *
             * @example Website Redesign
             */
            'name' => 'required|string|max:150|min:4',
            /**
             * Project description. Must be at least 15 characters.
             *
             * @example Complete redesign of the company website with new branding and improved UX.
             */
            'about' => 'required|string|min:15',
            /**
             * Initial stage ID for the project. Terminal stages (6 and 7) are intentionally excluded during creation.
             *
             * @example 1
             */
            'stage_id' => 'required|integer|between:1,5',
            /**
             * Optional project notes. Maximum 250 characters.
             *
             * @example Focus on mobile-first design approach
             */
            'notes' => 'sometimes|nullable|string|max:250',
            /**
             * Optional array of initial tasks to create with the project. Maximum 3 tasks.
             *
             * @example [{"title":"Prepare launch checklist"}]
             */
            'tasks' => 'sometimes|array|max:3',
            /**
             * Individual task title. Must be unique within the array, between 5 and 55 characters.
             *
             * @example Prepare launch checklist
             */
            'tasks.*.title' => 'required|string|distinct|min:5|max:55',
        ];
    }
}
