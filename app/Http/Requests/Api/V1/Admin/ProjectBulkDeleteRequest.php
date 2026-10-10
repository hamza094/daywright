<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Admin\BulkDeleteData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('ProjectBulkDeleteRequestData')]
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
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:projects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'ids.required' => 'Please provide at least one project id.',
            'ids.array' => 'Project ids must be provided as an array.',
            'ids.min' => 'Please provide at least one project id.',
            'ids.max' => 'You can delete up to 200 projects per request.',
            'ids.*.integer' => 'Each project id must be an integer.',
            'ids.*.distinct' => 'Duplicate project ids are not allowed.',
            'ids.*.exists' => 'One or more selected projects do not exist.',
        ];
    }

    public function toDto(): BulkDeleteData
    {
        return BulkDeleteData::fromIds($this->validated()['ids']);
    }
}
