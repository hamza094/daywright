<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Override;

class UserTasksRequest extends FormRequest
{
    private const array FILTER_KEYS = [
        'user_created',
        'task_assigned',
        'completed',
        'overdue',
        'remaining',
    ];

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
     * @return array<string, bool>
     */
    public function filters(): array
    {
        return collect(self::FILTER_KEYS)
            ->filter(fn (string $key): bool => $this->boolean($key))
            ->mapWithKeys(fn (string $key): array => [$key => true])
            ->all();
    }

    /**
     * Handle a passed validation attempt.
     */
    #[Override]
    protected function passedValidation(): void
    {
        if (
            ! $this->hasAnyFilter(self::FILTER_KEYS)
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
