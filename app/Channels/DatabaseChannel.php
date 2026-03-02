<?php

declare(strict_types=1);

namespace App\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel as Channel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Override;

class DatabaseChannel extends Channel
{
    #[Override]
    public function send($notifiable, Notification $notification): void
    {
        $data = json_encode($this->getData($notifiable, $notification));

        DB::table('notifications')->insertOrIgnore([
            'id' => $notification->id,
            'type' => $notification::class,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->id,
            'data' => $data,
            'signature' => hash('sha256', $data),
            'read_at' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
