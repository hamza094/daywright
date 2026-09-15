<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Zoom;

use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use Tests\TestCase;

class MeetingStartedWebhookDataTest extends TestCase
{
    /** @test */
    public function dto_serialization_round_trips_without_losing_types(): void
    {
        $original = new MeetingStartedWebhookData(
            meetingId: 123456789,
            startTime: '2026-09-15T10:00:00Z',
            requestId: 'req-123',
        );

        $array = $original->toArray();
        $reconstructed = MeetingStartedWebhookData::fromArray($array);

        $this->assertEquals($original->meetingId, $reconstructed->meetingId);
        $this->assertEquals($original->startTime, $reconstructed->startTime);
        $this->assertEquals($original->requestId, $reconstructed->requestId);
    }

    /** @test */
    public function from_array_handles_nullable_fields(): void
    {
        $data = [
            'meetingId' => 123456789,
            'startTime' => null,
            'requestId' => null,
        ];

        $dto = MeetingStartedWebhookData::fromArray($data);

        $this->assertNull($dto->startTime);
        $this->assertNull($dto->requestId);
    }
}
