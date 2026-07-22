<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\DataTransferObjects\Admin\AdminStageData;
use App\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('stages')->ignore($this->route('stage')),
            ],
        ];

        if ($this->isMethod('post')) {
            $rules['name'][] = function ($attribute, $value, $fail): void {
                if (Stage::count() >= 5) {
                    $fail('Cannot add more than 5 stages.');
                }
            };
        }

        return $rules;
    }

    #[Override]
    public function messages()
    {
        return [
            'name.unique' => 'The stage name must be unique.',
            'name.max' => 'The stage name must not exceed 255 characters.',
            'name.required' => 'The stage name is required.',
        ];
    }

    public function toDto(): AdminStageData
    {
        return AdminStageData::fromValidated($this->validated());
    }
}
