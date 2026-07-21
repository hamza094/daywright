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
            'name' => 'required|string|max:150|min:4',
            'about' => 'required|min:15',
            'stage_id' => 'required|integer|between:1,5',
            'notes' => 'sometimes|max:250',
            'tasks' => 'sometimes|array|max:3',
            'tasks.*.title' => 'required|string|distinct|min:5|max:55',
        ];
    }
}
