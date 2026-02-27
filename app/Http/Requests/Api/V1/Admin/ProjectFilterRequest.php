<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProjectHealthStatus;
use Illuminate\Foundation\Http\FormRequest;

class ProjectFilterRequest extends FormRequest
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
            'sort' => ['sometimes', 'required', 'in:asc,desc'],
            'search' => ['sometimes', 'string', 'max:255'],
            'filter' => ['sometimes', 'in:active,trashed'],
            'members' => ['sometimes', 'required'],
            'status' => ['sometimes', 'required', 'in:'.implode(',', array_column(ProjectHealthStatus::cases(), 'value'))],
            'tasks' => ['sometimes', 'required'],
            'stage' => ['sometimes', 'required', 'int', 'min:0', 'max:6'],
            'from' => ['sometimes', 'required', 'date', 'required_with:to'],
            'to' => ['sometimes', 'required', 'date', 'required_with:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && is_string($this->status)) {
            $this->merge([
                'status' => mb_strtolower($this->status),
            ]);
        }
    }
}
