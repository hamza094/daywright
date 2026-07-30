<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectStageUpdateData;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class StageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function projectStageUpdateData(): ProjectStageUpdateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ProjectStageUpdateData::fromArray($validated);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', 'integer', 'exists:stages,id'],
            'postponed_reason' => ['sometimes', 'required', 'string'],
        ];
    }

    #[Override]
    public function messages()
    {
        return [
            'stage.required' => 'The stage field is required.',
            'stage.in' => 'The selected stage is invalid. Please choose a valid stage.',
        ];
    }
}
