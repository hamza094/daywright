<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\TaskStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

final class MaxStatusCount implements ValidationRule
{
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (TaskStatus::count() >= 6) {
            $fail('The maximum allowed number of statuses has been reached.');
        }
    }
}
