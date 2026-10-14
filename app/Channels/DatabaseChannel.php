<?php

declare(strict_types=1);

namespace App\Channels;

use Illuminate\Notifications\Channels\DatabaseChannel as Channel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Override;

class DatabaseChannel extends Channel
{
    #[Override]
    public function send($notifiable, Notification $notification): void
    {
        $data = json_encode($this->getData($notifiable, $notification));

        $signature = (method_exists($notification, 'databaseSignature') ? $notification->databaseSignature($notifiable) : null)
            ?: hash('sha256', $data);

        DB::table('notifications')->insertOrIgnore([
            'id' => $notification->id ?: (string) Str::uuid(),
            'type' => $notification::class,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => (string) $notifiable->getKey(),
            'data' => $data,
            'signature' => $signature,
            'read_at' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
