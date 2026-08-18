<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectStageUpdateData;
use App\Models\Stage;
use Closure;
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
            /**
             * The stage ID to update the project to.
             *
             * @example 3
             */
            'stage' => ['required', 'integer', 'exists:stages,id'],
            /**
             * Reason for postponing the project. Required when moving to a postponed stage.
             *
             * @example Waiting for client approval
             */
            'postponed_reason' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $stageId = $this->input('stage');
                    $stage = Stage::find($stageId);

                    if ($stage && $stage->name === 'Postponed' && empty($value)) {
                        $fail('The postponed_reason field is required when moving to a postponed stage.');
                    }
                },
            ],
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
