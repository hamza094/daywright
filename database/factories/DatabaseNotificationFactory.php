<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Notification\NotificationPayloadData;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class DatabaseNotificationFactory extends Factory
{
    protected $model = DatabaseNotification::class;

    public function definition()
    {
        $projectSlug = Str::slug(fake()->words(3, true));

        return [
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ProjectInvitation',
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'data' => (new NotificationPayloadData(
                message: 'You have been invited to a project.',
                notifier: new NotificationActorData(
                    uuid: (string) Str::uuid(),
                    name: fake()->name(),
                    username: fake()->userName(),
                    avatarPath: null,
                    email: fake()->safeEmail(),
                ),
                link: '/api/v1/projects/'.$projectSlug,
            ))->toArray(),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
