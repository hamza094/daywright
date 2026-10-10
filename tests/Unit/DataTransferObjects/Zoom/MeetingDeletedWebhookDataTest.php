<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Zoom;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeetingDeletedWebhookDataTest extends TestCase
{
    #[Test]
    public function dto_serialization_round_trips_without_losing_types(): void
    {
        $original = new MeetingDeletedWebhookData(
            meetingId: 123456789,
            requestId: 'req-123',
        );

        $array = $original->toArray();
        $reconstructed = MeetingDeletedWebhookData::fromArray($array);

        $this->assertEquals($original->meetingId, $reconstructed->meetingId);
        $this->assertEquals($original->requestId, $reconstructed->requestId);
    }

    #[Test]
    public function from_array_handles_nullable_request_id(): void
    {
        $data = [
            'meetingId' => 123456789,
            'requestId' => null,
        ];

        $dto = MeetingDeletedWebhookData::fromArray($data);

        $this->assertNull($dto->requestId);
    }
}
