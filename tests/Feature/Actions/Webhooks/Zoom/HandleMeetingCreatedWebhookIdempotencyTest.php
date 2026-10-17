<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Webhooks\Zoom;

use App\Actions\Webhooks\Zoom\HandleMeetingCreatedWebhook;
use App\DataTransferObjects\Zoom\MeetingCreatedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;

final class HandleMeetingCreatedWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    public function test_webhook_from_non_api_source_is_ignored(): void
    {
        $meeting = MeetingTestHelper::createCreatingMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'manual',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(MeetingSyncStatus::Creating, $meeting->sync_status);
        $this->assertNull($meeting->meeting_id);
    }

    public function test_webhook_without_operation_tracking_field_is_ignored(): void
    {
        $meeting = MeetingTestHelper::createCreatingMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: null,
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(MeetingSyncStatus::Creating, $meeting->sync_status);
        $this->assertNull($meeting->meeting_id);
    }

    public function test_webhook_with_unknown_operation_id_is_ignored(): void
    {
        MeetingTestHelper::createCreatingMeeting($this->project, $this->user, [
            'sync_operation_id' => 'different-operation-id',
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'unknown-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $this->assertDatabaseCount('meetings', 1);
    }

    public function test_webhook_attaches_meeting_id_to_creating_state(): void
    {
        $meeting = MeetingTestHelper::createCreatingMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
        ]);

        $this->assertEquals('test-operation-id', $meeting->sync_operation_id);
        $this->assertNull($meeting->meeting_id);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(123, $meeting->meeting_id);
        $this->assertEquals('https://zoom.us/j/123', $meeting->join_url);
        $this->assertNotNull($meeting->sync_available_at);
    }

    public function test_webhook_attaches_meeting_id_to_create_unknown_state(): void
    {
        $meeting = MeetingTestHelper::createCreateUnknownMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(123, $meeting->meeting_id);
        $this->assertEquals('https://zoom.us/j/123', $meeting->join_url);
        $this->assertNotNull($meeting->sync_available_at);
    }

    public function test_webhook_ignored_when_already_active_with_same_id(): void
    {
        $meeting = MeetingTestHelper::createActiveMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
            'meeting_id' => 123,
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(MeetingSyncStatus::Active, $meeting->sync_status);
        $this->assertEquals(123, $meeting->meeting_id);
    }

    public function test_webhook_does_not_overwrite_different_meeting_id(): void
    {
        $meeting = MeetingTestHelper::createActiveMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
            'meeting_id' => 456,
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(456, $meeting->meeting_id);
    }

    public function test_webhook_ignored_for_invalid_sync_states(): void
    {
        $meeting = MeetingTestHelper::createFailedMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
            'meeting_id' => null,
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(MeetingSyncStatus::Failed, $meeting->sync_status);
        $this->assertNull($meeting->meeting_id);
    }

    public function test_webhook_does_not_overwrite_existing_meeting_id(): void
    {
        $meeting = MeetingTestHelper::createCreatingMeeting($this->project, $this->user, [
            'sync_operation_id' => 'test-operation-id',
            'meeting_id' => 999,
        ]);

        $dto = new MeetingCreatedWebhookData(
            meetingId: 123,
            operationId: 'test-operation-id',
            joinUrl: 'https://zoom.us/j/123',
            creationSource: 'open_api',
            requestId: 'test-request-id',
        );

        $action = app(HandleMeetingCreatedWebhook::class);
        $action->handle($dto);

        $meeting->refresh();
        $this->assertEquals(999, $meeting->meeting_id);
    }
}
