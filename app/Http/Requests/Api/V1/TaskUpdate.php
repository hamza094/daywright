<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskDueNotifies;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Timezone;

class TaskUpdate extends FormRequest
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
                    * - Field must be valid date
                    *
                    @example "2024-12-09T15:25:00.00"
                    */
            'due_at' => 'date|sometimes|required_with:notified',
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

    protected function prepareForValidation(): void
    {
        $dueAt = $this->input('due_at');

        if ($dueAt) {

            $parsedDueAt = Carbon::parse($dueAt);
            $formattedTime = $parsedDueAt->format('Y-m-d H:i:s');
            $convertedTime = Timezone::convertFromLocal($formattedTime);

            $this->merge([
                'due_at' => $convertedTime,
            ]);
        }
    }
}
