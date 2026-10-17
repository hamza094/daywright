<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

trait ValidatesLimitOption
{
    /**
     * Parse and validate the --limit option.
     *
     * Returns the validated integer clamped to $maxLimit,
     * or null if the input is non-numeric or <= 0 (in which case an error is output).
     */
    protected function parseLimit(int $maxLimit): ?int
    {
        $raw = (string) $this->option('limit');

        if (! ctype_digit($raw) || (int) $raw <= 0) {
            $this->error('Limit must be between 1 and '.$maxLimit);

            return null;
        }

        return min((int) $raw, $maxLimit);
    }
}
