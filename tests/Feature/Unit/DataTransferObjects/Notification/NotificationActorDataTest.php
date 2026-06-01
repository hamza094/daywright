<?php

declare(strict_types=1);

namespace Tests\Feature\Unit\DataTransferObjects\Notification;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationActorDataTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_the_expected_notification_payload_from_a_user(): void
    {
        $user = User::factory()->create([
            'username' => null,
            'avatar_path' => null,
        ]);

        $payload = NotificationActorData::fromUser($user)->toArray();

        $this->assertSame([
            'uuid' => $user->uuid,
            'name' => $user->name,
            'username' => null,
            'avatar_path' => null,
            'email' => $user->email,
        ], $payload);
    }
}
