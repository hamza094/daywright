<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use App\DataTransferObjects\Task\AssignTaskMembersData;
use App\Rules\ActiveProjectMember;
use Closure;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;

#[SchemaName('TaskMemberAssignRequestData')]
class TaskMembersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * User identifiers to assign to the task.
             * - Prevents assigning a task to users who are already assigned.
             * - Ensures tasks can only be assigned to active members of the project.
             *
             * @var array<int,int>
             *
             * @example [3, 5]
             */
            'user_ids' => ['required',
                'array',
                'min:1',
                $this->userIdsValidation(),
                new ActiveProjectMember($this->task),
            ],
            /**
             * Individual user identifier.
             *
             * @var int
             *
             * @example 3
             */
            'user_ids.*' => ['required', 'exists:users,id', 'distinct'],
        ];
    }

    public function toDto(): AssignTaskMembersData
    {
        $validated = $this->validated();

        // Normalize 'user_ids' to 'members' for the DTO
        $memberIds = $validated['user_ids'] ?? [];

        return AssignTaskMembersData::fromValidated(['members' => $memberIds]);
    }

    protected function userIdsValidation(): Closure
    {
        return function (string $attribute, $value, Closure $fail): void {
            // Guard: ensure the input is an array before querying
            if (! is_array($value) || $value === []) {
                return;
            }

            $existingMembersCount = $this->task->assignee()
                ->whereIn('user_id', $value)
                ->count();

            if ($existingMembersCount > 0) {
                $fail('One or more users are already assigned to the task.');
            }
        };
    }
}
