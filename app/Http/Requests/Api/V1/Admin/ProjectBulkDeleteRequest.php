<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ProjectBulkDeleteRequest extends FormRequest
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
            'project_ids' => ['required', 'array', 'min:1', 'max:200'],
            'project_ids.*' => ['required', 'integer', 'distinct', 'exists:projects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_ids.required' => 'Please provide at least one project id.',
            'project_ids.array' => 'Project ids must be provided as an array.',
            'project_ids.min' => 'Please provide at least one project id.',
            'project_ids.max' => 'You can delete up to 200 projects per request.',
            'project_ids.*.integer' => 'Each project id must be an integer.',
            'project_ids.*.distinct' => 'Duplicate project ids are not allowed.',
            'project_ids.*.exists' => 'One or more selected projects do not exist.',
        ];
    }
}
