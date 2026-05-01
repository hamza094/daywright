<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskDueNotifies;
use App\Rules\Iso8601Timestamp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class TaskUpdateRequest extends FormRequest
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
        $project = $this->project;

        return [
            /*
            * Task's Title
            */
            'title' => [
                'sometimes',
                'required',
                'max:55',
                Rule::unique('tasks')/* ->ignore($this->task) */ ->where(fn ($query) => $query->where('project_id', $project->id)),
            ],
            /*
                * Task's Description
                */
            'description' => 'sometimes|max:1000',

            /*
                    * Task's Due Date
                    * - This field required with notified
                    * - Field must be a valid ISO 8601 timestamp with a timezone offset
                    *
                    @example "2024-12-09T15:25:00+00:00"
                    */
            'due_at' => [
                'sometimes',
                'required_with:notified',
                'bail',
                'string',
                new Iso8601Timestamp,
            ],
            /**
             * TaskStatus id which task associated to
             *
             * @example 1
             */
            'status_id' => 'required|int|max:4|sometimes',
            /*
                    * Notified task users about task due date
                    */
            'notified' => [
                'sometimes',
                'required',
                Rule::in(TaskDueNotifies::values()),
            ],
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if (! $this->has('due_at')) {
            return;
        }

        $normalizedDueAt = Iso8601Timestamp::normalizeToUtc((string) $this->input('due_at'));

        if ($normalizedDueAt === null) {
            return;
        }

        $this->merge([
            'due_at' => $normalizedDueAt,
        ]);
    }
}
