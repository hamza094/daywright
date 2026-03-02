<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Override;

class ActiveProjectMember implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
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
        $activeProjectMemberIds = $this->task->project->activeMembers()->pluck('users.id')->toArray();
        $invalidMembers = array_diff($value, $activeProjectMemberIds);

        return $invalidMembers === [];
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    #[Override]
    public function message()
    {
        return 'One or more users are not active project members.';
    }
}
