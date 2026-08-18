<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use App\DataTransferObjects\Task\TaskCreateData;
use App\Models\Project;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

#[SchemaName('TaskStoreRequestData')]
class TaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled at the route level via `can:access,project`
        // Returning true here ensures validation runs when the middleware passes.
        return true;
    }

    public function toDto(): TaskCreateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TaskCreateData::fromArray($validated);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            /**
             * Task title. Titles must be unique within the selected project.
             *
             * @var string
             *
             * @example Draft QA checklist
             */
            'title' => [
                'required',
                'max:55',
                'min:3',
                Rule::unique('tasks')->where(fn ($query) => $query->where('project_id', $project->id)),
            ],
        ];

    }

    /**
     * Get the error messages for the defined validation rules.
     */
    #[Override]
    public function messages(): array
    {
        return [
            'title.required' => 'Task title required.',
            'title.max' => 'Your task title is too long.',
            'title.unique' => 'Task with same title already exists.',
        ];
    }
}
