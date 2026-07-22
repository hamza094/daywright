<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Admin\TaskBulkDeleteData;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class TaskBulkDeleteRequest extends FormRequest
{
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
            'task_ids' => ['required', 'array', 'min:1', 'max:200'],
            'task_ids.*' => ['required', 'integer', 'distinct', 'exists:tasks,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'task_ids.required' => 'Please provide at least one task id.',
            'task_ids.array' => 'Task ids must be provided as an array.',
            'task_ids.min' => 'Please provide at least one task id.',
            'task_ids.max' => 'You can delete up to 200 tasks per request.',
            'task_ids.*.integer' => 'Each task id must be an integer.',
            'task_ids.*.distinct' => 'Duplicate task ids are not allowed.',
            'task_ids.*.exists' => 'One or more selected tasks do not exist.',
        ];
    }

    public function toDto(): TaskBulkDeleteData
    {
        return TaskBulkDeleteData::fromValidated($this->validated());
    }
}
