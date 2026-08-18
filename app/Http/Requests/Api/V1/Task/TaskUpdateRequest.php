<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use App\DataTransferObjects\Task\TaskUpdateData;
use App\Enums\TaskDueNotifies;
use App\Enums\TaskSystemStatus;
use App\Rules\Iso8601Timestamp;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

#[SchemaName('TaskUpdateRequestData')]
class TaskUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): TaskUpdateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TaskUpdateData::fromArray($validated);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->project;

        return [
            /**
             * Updated task title. Titles must remain unique within the project.
             *
             * @var string
             *
             * @example Draft QA checklist
             */
            'title' => [
                'sometimes',
                'required',
                'max:55',
                Rule::unique('tasks')->ignore($this->task)->where(fn ($query) => $query->where('project_id', $project->id)),
            ],
            /**
             * Optional task description.
             *
             * @var string
             *
             * @example Confirm release notes, test scenarios, and sign-off owners.
             */
            'description' => 'sometimes|max:1000',

            /**
             * Task due date in ISO 8601 format with a timezone offset.
             * Required when `notified` is present.
             * Format: ISO 8601 date-time string with timezone.
             *
             * @example 2024-12-09T15:25:00+00:00
             */
            'due_at' => [
                'sometimes',
                'required_with:notified',
                'bail',
                'string',
                new Iso8601Timestamp,
            ],
            /**
             * Task status identifier.
             * 1 = Pending, 2 = In Progress, 3 = Under Review, 4 = Completed, 5 = Cancelled.
             *
             * @var int
             *
             * @example 1
             */
            'status_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::in(TaskSystemStatus::all()),
            ],
            /**
             * Notification strategy used for due-date reminders.
             *
             * @example 1 Day Before
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
