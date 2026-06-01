<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Override;

class TaskAssigneeMember implements Rule
{
    /**
     * Create a new rule instance.
     */
    public function __construct(protected $task) {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    #[Override]
    public function passes($attribute, $value)
    {
        return $this->task->assignee()->where('user_id', $value)->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    #[Override]
    public function message()
    {
        return 'The selected user is not a current member of task.';
    }
}
