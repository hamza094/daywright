<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Message;

final class ScheduleProjectMessageAction
{
    public function execute(Message $message, string $deliveredAt): void
    {
        $message->delivered_at = $deliveredAt;
        $message->save();
    }
}
