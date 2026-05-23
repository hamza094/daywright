<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Message;
use Carbon\Carbon;

final class ScheduleProjectMessageAction
{
    public function execute(Message $message, string $deliveredAt): void
    {
        $message->delivered_at = Carbon::parse($deliveredAt);
        $message->save();
    }
}
