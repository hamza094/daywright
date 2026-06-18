<?php

declare(strict_types=1);

namespace App\Services\Project;

use Throwable;

final class MeetingSyncErrorFormatter
{
    public function format(Throwable $exception): string
    {
        $message = method_exists($exception, 'publicMessage')
            ? $exception->publicMessage()
            : 'Meeting sync failed. Please try again.';

        return mb_strlen((string) $message) > 1000
            ? mb_substr((string) $message, 0, 1000).'...'
            : $message;
    }
}
