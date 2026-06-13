<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Zoom;

use App\DataTransferObjects\Zoom\Meeting;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for Zoom Meeting Data Transfer Object.
 *
 * Tests the Meeting DTO which maps Zoom API responses to internal data structures.
 * These tests verify:
 * - Rejection of payloads missing critical fields
 * - Correct mapping of all Zoom response fields
 * - Normalization of boolean and integer types from API responses
 * - Handling of int64 meeting IDs (Zoom uses large integer IDs)
 *
 * Level: Unit/DTO testing
 */
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

    #[Test]
    public function it_maps_zoom_meeting_response_correctly(): void
    {
        $response = [
            'id' => 123456789012345,
            'topic' => 'Test Meeting',
            'agenda' => 'Test Agenda',
            'created_at' => '2024-05-16T18:00:07Z',
            'duration' => 30,
            'join_url' => 'https://zoom.us/j/1234567890?pwd=secret',
            'password' => 'secret',
            'join_before_host' => true,
            'start_time' => '2024-05-18T18:00:07Z',
            'start_url' => 'https://zoom.us/s/1234567890?pwd=secret',
            'status' => 'waiting',
            'timezone' => 'UTC',
        ];

        $meeting = Meeting::fromResponse($response);

        $this->assertEquals(123456789012345, $meeting->meeting_id);
        $this->assertEquals('Test Meeting', $meeting->topic);
        $this->assertEquals('Test Agenda', $meeting->agenda);
        $this->assertEquals('2024-05-16 18:00:07', $meeting->created_at);
        $this->assertEquals(30, $meeting->duration);
        $this->assertEquals('https://zoom.us/j/1234567890?pwd=secret', $meeting->join_url);
        $this->assertEquals('secret', $meeting->password);
        $this->assertTrue($meeting->join_before_host);
        $this->assertEquals('2024-05-18 18:00:07', $meeting->start_time);
        $this->assertEquals('https://zoom.us/s/1234567890?pwd=secret', $meeting->start_url);
        $this->assertEquals('waiting', $meeting->status);
        $this->assertEquals('UTC', $meeting->timezone);
    }

    #[Test]
    public function it_normalizes_boolean_and_integer_types(): void
    {
        $response = [
            'id' => 123,
            'topic' => 'Test',
            'agenda' => '',
            'created_at' => '2024-05-16T18:00:07Z',
            'duration' => 30,
            'join_url' => 'https://zoom.us/j/123',
            'password' => 'secret',
            'join_before_host' => 'true', // String from API
            'start_time' => '2024-05-18T18:00:07Z',
            'start_url' => 'https://zoom.us/s/123',
            'status' => 'waiting',
            'timezone' => 'UTC',
        ];

        $meeting = Meeting::fromResponse($response);

        $this->assertTrue($meeting->join_before_host); // Should be normalized to boolean
    }

    #[Test]
    public function it_handles_int64_meeting_ids(): void
    {
        $largeId = '9223372036854775807'; // Max int64

        $response = [
            'id' => $largeId,
            'topic' => 'Test',
            'agenda' => '',
            'created_at' => '2024-05-16T18:00:07Z',
            'duration' => 30,
            'join_url' => 'https://zoom.us/j/123',
            'password' => 'secret',
            'join_before_host' => false,
            'start_time' => '2024-05-18T18:00:07Z',
            'start_url' => 'https://zoom.us/s/123',
            'status' => 'waiting',
            'timezone' => 'UTC',
        ];

        $meeting = Meeting::fromResponse($response);

        $this->assertEquals($largeId, $meeting->meeting_id);
    }
}
