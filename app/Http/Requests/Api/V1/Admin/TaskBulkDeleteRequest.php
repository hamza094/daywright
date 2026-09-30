<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Admin\BulkDeleteData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('TaskBulkDeleteRequestData')]
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:tasks,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'ids.required' => 'Please provide at least one task id.',
            'ids.array' => 'Task ids must be provided as an array.',
            'ids.min' => 'Please provide at least one task id.',
            'ids.max' => 'You can delete up to 200 tasks per request.',
            'ids.*.integer' => 'Each task id must be an integer.',
            'ids.*.distinct' => 'Duplicate task ids are not allowed.',
            'ids.*.exists' => 'One or more selected tasks do not exist.',
        ];
    }

    public function toDto(): BulkDeleteData
    {
        return BulkDeleteData::fromIds($this->validated()['ids']);
    }
}
