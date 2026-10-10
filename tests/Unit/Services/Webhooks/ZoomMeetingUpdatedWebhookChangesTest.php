<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Webhooks;

use App\DataTransferObjects\Zoom\Meeting as ZoomMeeting;
use App\Services\Webhooks\ZoomMeetingUpdatedWebhookChanges;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ZoomMeetingUpdatedWebhookChangesTest extends TestCase
{
    private ZoomMeetingUpdatedWebhookChanges $changes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changes = new ZoomMeetingUpdatedWebhookChanges;
    }

    #[Test]
    public function iso_8601_and_database_formatted_utc_start_time_values_match(): void
    {
        $meeting = $this->zoomMeeting(
            start_time: '2026-09-19 10:00:00',
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'start_time' => '2026-09-19T10:00:00Z',
            ], $meeting),
            'ISO-8601 UTC start_time should match database-formatted UTC start_time'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'start_time' => '2026-09-19T12:00:00+02:00',
            ], $meeting),
            'ISO-8601 timezone offset start_time should match database-formatted UTC start_time'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'start_time' => '2026-09-19 10:00:00',
            ], $meeting),
            'Identical database-formatted start_time should match'
        );
    }

    #[Test]
    public function numeric_and_boolean_equivalent_values_match(): void
    {
        $meeting = $this->zoomMeeting(
            duration: 45,
            join_before_host: true,
        );

        // Numeric equivalents
        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'duration' => 45,
            ], $meeting),
            'Integer duration should match'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'duration' => '45',
            ], $meeting),
            'String numeric duration should match integer duration'
        );

        // Boolean equivalents
        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => true,
            ], $meeting),
            'Boolean true should match boolean true'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => 'true',
            ], $meeting),
            'String "true" should match boolean true'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => 1,
            ], $meeting),
            'Integer 1 should match boolean true'
        );

        // Boolean false equivalents
        $falseMeeting = $this->zoomMeeting(join_before_host: false);
        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => false,
            ], $falseMeeting),
            'Boolean false should match boolean false'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => 'false',
            ], $falseMeeting),
            'String "false" should match boolean false'
        );

        $this->assertTrue(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => 0,
            ], $falseMeeting),
            'Integer 0 should match boolean false'
        );
    }

    #[Test]
    public function mismatching_value_returns_false(): void
    {
        $meeting = $this->zoomMeeting(
            topic: 'Zoom Topic',
            duration: 30,
            start_time: '2026-09-19 10:00:00',
            join_before_host: false,
        );

        $this->assertFalse(
            $this->changes->zoomHasRequestedFields([
                'topic' => 'Different Topic',
            ], $meeting)
        );

        $this->assertFalse(
            $this->changes->zoomHasRequestedFields([
                'duration' => 60,
            ], $meeting)
        );

        $this->assertFalse(
            $this->changes->zoomHasRequestedFields([
                'start_time' => '2026-09-19T11:00:00Z',
            ], $meeting)
        );

        $this->assertFalse(
            $this->changes->zoomHasRequestedFields([
                'join_before_host' => true,
            ], $meeting)
        );
    }

    #[Test]
    public function snapshot_mapping_includes_all_fields_and_join_url(): void
    {
        $meeting = $this->zoomMeeting(
            id: 999,
            topic: 'Team Standup',
            duration: 30,
            agenda: 'Daily sync',
            start_time: '2026-09-19 09:00:00',
            timezone: 'America/New_York',
            password: 'secret-password',
            join_before_host: true,
            join_url: 'https://zoom.us/j/999?pwd=xyz',
        );

        $snapshot = $this->changes->fieldsFromZoom($meeting);

        $this->assertSame([
            'topic' => 'Team Standup',
            'duration' => 30,
            'agenda' => 'Daily sync',
            'start_time' => '2026-09-19 09:00:00',
            'timezone' => 'America/New_York',
            'password' => 'secret-password',
            'join_before_host' => true,
            'join_url' => 'https://zoom.us/j/999?pwd=xyz',
        ], $snapshot);
    }

    private function zoomMeeting(
        int $id = 123,
        string $topic = 'Test Topic',
        int $duration = 30,
        string $agenda = '',
        string $start_time = '2026-09-19 10:00:00',
        string $timezone = 'UTC',
        string $password = '',
        bool $join_before_host = false,
        string $join_url = 'https://zoom.us/j/123',
    ): ZoomMeeting {
        return new ZoomMeeting(
            meeting_id: $id,
            topic: $topic,
            agenda: $agenda,
            created_at: '2026-09-19 09:00:00',
            duration: $duration,
            start_time: $start_time,
            start_url: "https://zoom.us/s/{$id}",
            join_url: $join_url,
            status: 'waiting',
            timezone: $timezone,
            password: $password,
            join_before_host: $join_before_host,
            tracking_fields: [],
        );
    }
}
