<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Override;

class UserTasksRequest extends FormRequest
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
            'user_created' => 'sometimes|boolean',
            'task_assigned' => 'sometimes|boolean',
            'completed' => 'sometimes|boolean',
            'overdue' => 'sometimes|boolean',
            'remaining' => 'sometimes|boolean',
        ];
    }

    /**
     * Convenience: return only known filter keys
     */
    public function filters(): array
    {
        return $this->only([
            'user_created',
            'task_assigned',
            'completed',
            'overdue',
            'remaining',
        ]);
    }

    /**
     * Handle a passed validation attempt.
     */
    #[Override]
    protected function passedValidation(): void
    {
        if (
            ! $this->hasAnyFilter(['completed', 'overdue', 'remaining', 'user_created', 'task_assigned'])
        ) {
            throw ValidationException::withMessages([
                'filters' => 'At least one filter must be provided.',
            ]);
        }
    }

    /**
     * Check if any of the specified filter keys are present and filled.
     */
    protected function hasAnyFilter(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->filled($key) && $this->boolean($key)) {
                return true;
            }
        }

        return false;
    }
}
