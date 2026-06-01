<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ScheduleProjectMessageAction
{
    public function execute(Message $message, string $deliveredAt): void
    {
        try {
            $parsed = Carbon::parse($deliveredAt);
        } catch (Throwable) {
            throw ValidationException::withMessages(['delivered_at' => 'Invalid datetime format.']);
        }

        if ($parsed->isPast()) {
            throw ValidationException::withMessages(['delivered_at' => 'Scheduled time must be in the future.']);
        }

        $message->delivered_at = $parsed;
        $message->save();
    }
}
