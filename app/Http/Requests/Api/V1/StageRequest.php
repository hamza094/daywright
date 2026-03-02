<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

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

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [

            /**
             * @example 1
             */
            'stage' => ['required', 'int',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((int) $value === (int) $this->project->stage_id) {
                        $fail('The selected stage must be different from the current project stage.');
                    }
                },
            ],

            /**
             * @example null
             */
            'postponed_reason' => ['sometimes', 'required', 'string',
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
