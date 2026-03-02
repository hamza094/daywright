<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Override;

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

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            /**
             * Tasks title
             *
             * - Project task must be unique
             *
             * @example "this is a new project task"
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
     *
     * @return array
     */
    #[Override]
    public function messages()
    {
        return [
            'title.required' => 'Task title required.',
            'title.max' => 'Your task title is too long.',
            'title.unique' => 'Task with same title already exists.',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {

        /** @var Project|null $project */
        $project = $this->route('project');

        throw_if($project->tasksReachedItsLimit(),
            ValidationException::withMessages(
                ['tasks' => 'Project tasks reached their limit'])
        );
    }
}
