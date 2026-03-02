<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Override;
use Safe\DateTimeImmutable;

class MeetingDateTime implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

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
        if ($value instanceof DateTimeImmutable) {
            $now = new DateTimeImmutable;

            return $value > $now;
        }

        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    #[Override]
    public function message()
    {
        return 'The :attribute date and time and must be in the future.';

    }
}
