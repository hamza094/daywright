<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Zoom;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use Tests\TestCase;

class MeetingUpdatedWebhookDataTest extends TestCase
{
    /** @test */
    public function dto_serialization_round_trips_without_losing_types(): void
    {
        $original = new MeetingUpdatedWebhookData(
            meetingId: 123456789,
            changes: [
                'topic' => 'Updated Topic',
                'duration' => 45,
                'start_time' => '2026-09-15T10:00:00Z',
            ],
            requestId: 'req-123',
        );

        $array = $original->toArray();
        $reconstructed = MeetingUpdatedWebhookData::fromArray($array);

        $this->assertEquals($original->meetingId, $reconstructed->meetingId);
        $this->assertEquals($original->changes, $reconstructed->changes);
        $this->assertEquals($original->requestId, $reconstructed->requestId);
    }

    /** @test */
    public function from_array_handles_nullable_request_id(): void
    {
        $data = [
            'meetingId' => 123456789,
            'changes' => ['topic' => 'Test'],
            'requestId' => null,
        ];

        $dto = MeetingUpdatedWebhookData::fromArray($data);

        $this->assertNull($dto->requestId);
    }

    /** @test */
    public function from_array_handles_missing_request_id(): void
    {
        $data = [
            'meetingId' => 123456789,
            'changes' => ['topic' => 'Test'],
        ];

        $dto = MeetingUpdatedWebhookData::fromArray($data);

        $this->assertNull($dto->requestId);
    }
}
