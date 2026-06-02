<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Zoom;

use App\DataTransferObjects\Zoom\Meeting;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    #[Test]
    public function it_rejects_zoom_meeting_payloads_missing_critical_fields(): void
    {
        $this->expectException(ZoomExternalFailureException::class);
        $this->expectExceptionMessage('Zoom meeting response is missing or invalid for [join_url].');

        Meeting::fromResponse([
            'id' => 124,
            'topic' => 'Topic',
            'agenda' => 'Agenda',
            'created_at' => '2024-05-16T18:00:07Z',
            'duration' => 30,
            'password' => 'secret',
            'join_before_host' => false,
            'start_time' => '2024-05-18T18:00:07Z',
            'start_url' => 'https://zoom.us/s/1234567890?pwd=secret',
            'status' => 'waiting',
            'timezone' => 'UTC',
        ]);
    }
}
