<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Notification;

use App\DataTransferObjects\Notification\NotificationActorData;
use App\DataTransferObjects\Notification\NotificationPayloadData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationPayloadDataTest extends TestCase
{
    #[Test]
    public function it_serializes_and_restores_the_notification_contract(): void
    {
        $payload = new NotificationPayloadData(
            message: 'Updated project DayWright',
            notifier: new NotificationActorData(
                uuid: '8beaf5dc-a58c-4be5-8cfd-1f594fa8ce1a',
                name: 'Hamza',
                username: 'hamza',
                avatarPath: 'avatars/hamza.png',
                email: 'hamza@example.com',
            ),
            link: '/api/v1/projects/daywright',
        );

        $this->assertSame($payload->toArray(), NotificationPayloadData::fromArray($payload->toArray())->toArray());
    }
}
