<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\TaskAssigneeMember;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class TaskMemberUnassignRequest extends FormRequest
{
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
            'member' => ['required', 'exists:users,id', new TaskAssigneeMember($this->task)],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'member.required' => 'Please provide a task member to unassign.',
            'member.exists' => 'The selected task member was not found.',
        ];
    }
}
