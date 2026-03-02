<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\TaskStatus;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class TaskStatusRequest extends FormRequest
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
            'label' => $this->isMethod('post') ? 'required|max:25|min:3' : 'sometimes|max:25|min:3',
            'color' => $this->isMethod('post') ? 'required|hex_color' : 'sometimes|hex_color',
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->isMethod('post') && TaskStatus::count() >= 6) {
                    $validator->errors()->add(
                        'label',
                        'The maximum allowed number of statuses has been reached.'
                    );
                }
            },
        ];
    }
}
