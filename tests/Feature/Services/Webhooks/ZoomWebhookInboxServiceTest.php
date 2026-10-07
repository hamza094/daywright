<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Webhooks;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\WebhookInboxState;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ZoomWebhookInboxServiceTest extends TestCase
{
    use RefreshDatabase;

    private ZoomWebhookInboxService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(ZoomWebhookInboxService::class);
    }

    #[Test]
    public function acceptance_creates_exactly_one_durable_row(): void
    {
        Queue::fake();

        $data = new MeetingUpdatedWebhookData(
            meetingId: 123456789,
            changes: ['topic' => 'Updated Topic'],
            requestId: 'req-123',
        );

        $inbox = $this->service->accept(
            eventKey: 'test-event-key',
            eventType: 'meeting.updated',
            requestId: 'req-123',
            occurredAt: 1234567890,
            data: $data,
        );

        $this->assertInstanceOf(WebhookInbox::class, $inbox);
        $this->assertEquals('zoom', $inbox->provider);
        $this->assertEquals('test-event-key', $inbox->event_key);
        $this->assertEquals('meeting.updated', $inbox->event_type);
        $this->assertEquals(WebhookInboxState::Received, $inbox->state);
        $this->assertEquals(0, $inbox->attempts);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, fn ($job): bool => $job->webhookInboxId === $inbox->id);
    }

    #[Test]
    public function duplicate_acceptance_returns_existing_row(): void
    {
        Queue::fake();

        $data = new MeetingUpdatedWebhookData(
            meetingId: 123456789,
            changes: ['topic' => 'Updated Topic'],
            requestId: 'req-123',
        );

        $firstInbox = $this->service->accept(
            eventKey: 'duplicate-key',
            eventType: 'meeting.updated',
            requestId: 'req-123',
            occurredAt: 1234567890,
            data: $data,
        );

        $secondInbox = $this->service->accept(
            eventKey: 'duplicate-key',
            eventType: 'meeting.updated',
            requestId: 'req-456',
            occurredAt: 1234567891,
            data: $data,
        );

        $this->assertEquals($firstInbox->id, $secondInbox->id);
        $this->assertFalse($secondInbox->wasRecentlyCreated);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 2);
    }

    #[Test]
    public function duplicate_acceptance_does_not_dispatch_a_completed_row(): void
    {
        Queue::fake();
        $inbox = WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'completed-duplicate-key',
            'event_type' => 'meeting.updated',
            'state' => WebhookInboxState::Completed,
        ]);

        $accepted = $this->service->accept(
            eventKey: $inbox->event_key,
            eventType: $inbox->event_type,
            requestId: 'req-duplicate',
            occurredAt: 1234567890,
            data: new MeetingUpdatedWebhookData(123456789, ['topic' => 'Updated Topic'], 'req-duplicate'),
        );

        $this->assertSame($inbox->id, $accepted->id);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function queue_failure_keeps_the_accepted_row_available_for_recovery(): void
    {
        $queueManager = $this->app->make('queue');
        $queueConnection = Mockery::mock($queueManager->connection())->makePartial();
        $queueConnection->shouldReceive('push')
            ->once()
            ->andThrow(new RuntimeException('Queue unavailable'));
        $queueManagerMock = Mockery::mock($queueManager)->makePartial();
        $queueManagerMock->shouldReceive('connection')->andReturn($queueConnection);
        Log::spy();
        Queue::swap($queueManagerMock);

        $inbox = $this->service->accept(
            eventKey: 'queue-failure-key',
            eventType: 'meeting.updated',
            requestId: 'req-queue-failure',
            occurredAt: null,
            data: new MeetingUpdatedWebhookData(123456789, ['topic' => 'Updated Topic'], 'req-queue-failure'),
        );

        $this->assertSame(WebhookInboxState::Received, $inbox->fresh()->state);
        $this->assertSame(0, $inbox->fresh()->attempts);
        $this->assertTrue(
            WebhookInbox::query()
                ->whereKey($inbox->id)
                ->claimableAt(now(), 5)
                ->exists(),
        );
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function duplicate_acceptance_does_not_dispatch_a_delayed_retry(): void
    {
        Queue::fake();
        $inbox = WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'delayed-duplicate-key',
            'event_type' => 'meeting.updated',
            'state' => WebhookInboxState::Received,
            'available_at' => now()->addMinute(),
        ]);

        $accepted = $this->service->accept(
            eventKey: $inbox->event_key,
            eventType: $inbox->event_type,
            requestId: 'req-delayed-duplicate',
            occurredAt: 1234567890,
            data: new MeetingUpdatedWebhookData(123456789, ['topic' => 'Updated Topic'], 'req-delayed-duplicate'),
        );

        $this->assertSame($inbox->id, $accepted->id);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function process_claims_and_processes_received_row(): void
    {
        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'attempts' => 0,
            'event_type' => 'meeting.deleted',
            'payload' => [
                'meetingId' => 999999999,
                'requestId' => 'req-123',
            ],
        ]);

        $this->service->process($inbox->id);

        $inbox->refresh();
        $this->assertEquals(WebhookInboxState::Completed, $inbox->state);
        $this->assertEquals(1, $inbox->attempts);
    }

    #[Test]
    public function processing_claim_is_limited_to_requested_inbox(): void
    {
        $target = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'event_type' => 'meeting.deleted',
            'payload' => [
                'meetingId' => 999999999,
                'requestId' => 'req-target',
            ],
        ]);

        $other = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'claim_token' => 'other-token',
            'claim_expires_at' => now()->subMinute(),
        ]);

        $this->service->process($target->id);

        $other->refresh();
        $this->assertEquals(WebhookInboxState::Processing, $other->state);
        $this->assertEquals('other-token', $other->claim_token);
    }

    #[Test]
    public function failures_wait_for_each_retry_delay_and_stop_after_five_attempts(): void
    {
        $this->travelTo(now()->startOfSecond());
        $inbox = WebhookInbox::factory()->create(['event_type' => 'unsupported.event']);

        foreach ([15, 30, 60, 120] as $index => $delay) {
            $this->service->process($inbox->id);
            $inbox->refresh();

            $this->assertSame($index + 1, $inbox->attempts);
            $this->assertSame(WebhookInboxState::Received, $inbox->state);
            $this->assertEquals(now()->addSeconds($delay), $inbox->available_at);
            $this->assertNull($inbox->claim_token);
            $this->assertNull($inbox->claim_expires_at);
            $this->assertNull($inbox->failed_at);
            $this->assertSame(InvalidArgumentException::class, $inbox->last_error_class);

            $this->travel($delay - 1)->seconds();
            $this->service->process($inbox->id);
            $this->assertSame($index + 1, $inbox->fresh()->attempts);
            $this->travel(1)->seconds();
        }

        $this->service->process($inbox->id);
        $inbox->refresh();

        $this->assertSame(5, $inbox->attempts);
        $this->assertSame(WebhookInboxState::Failed, $inbox->state);
        $this->assertEquals(now(), $inbox->failed_at);
        $this->assertNull($inbox->available_at);
        $this->assertNull($inbox->claim_token);
        $this->assertNull($inbox->claim_expires_at);

        $this->service->process($inbox->id);
        $this->assertSame(5, $inbox->fresh()->attempts);
    }

    #[Test]
    public function processing_waits_for_the_existing_claim_to_expire(): void
    {
        $this->travelTo(now()->startOfSecond());
        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'attempts' => 1,
            'claim_token' => 'previous-worker',
            'claim_expires_at' => now()->addMinute(),
            'event_type' => 'meeting.deleted',
            'payload' => ['meetingId' => 999999999, 'requestId' => 'req-recovery'],
        ]);

        $this->service->process($inbox->id);
        $inbox->refresh();
        $this->assertSame(1, $inbox->attempts);
        $this->assertSame('previous-worker', $inbox->claim_token);
        $this->assertSame(WebhookInboxState::Processing, $inbox->state);

        $this->travel(1)->minutes();
        $this->service->process($inbox->id);
        $inbox->refresh();
        $this->assertSame(2, $inbox->attempts);
        $this->assertSame(WebhookInboxState::Completed, $inbox->state);
        $this->assertNull($inbox->claim_token);
    }

    #[Test]
    public function only_one_worker_claims_an_expired_row_and_old_tokens_cannot_update_it(): void
    {
        $oldClaimToken = 'old-worker-token';
        $firstClaimToken = (string) str()->uuid();
        $secondClaimToken = (string) str()->uuid();
        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'attempts' => 1,
            'claim_token' => $oldClaimToken,
            'claim_expires_at' => now()->subSecond(),
        ]);

        $firstClaim = WebhookInbox::query()
            ->whereKey($inbox->id)
            ->claimableAt(now(), 5)
            ->increment('attempts', 1, [
                'state' => WebhookInboxState::Processing,
                'claim_token' => $firstClaimToken,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(5),
            ]);

        $secondClaim = WebhookInbox::query()
            ->whereKey($inbox->id)
            ->claimableAt(now(), 5)
            ->increment('attempts', 1, [
                'state' => WebhookInboxState::Processing,
                'claim_token' => $secondClaimToken,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(5),
            ]);

        $oldWorkerUpdate = WebhookInbox::query()
            ->whereKey($inbox->id)
            ->unexpiredClaimOwnedBy($oldClaimToken, now())
            ->update(['state' => WebhookInboxState::Completed]);
        $oldWorkerFailure = WebhookInbox::query()
            ->whereKey($inbox->id)
            ->unexpiredClaimOwnedBy($oldClaimToken, now())
            ->update(['state' => WebhookInboxState::Failed]);

        $inbox->refresh();

        $this->assertSame(1, $firstClaim);
        $this->assertSame(0, $secondClaim);
        $this->assertSame(0, $oldWorkerUpdate);
        $this->assertSame(0, $oldWorkerFailure);
        $this->assertSame(2, $inbox->attempts);
        $this->assertSame($firstClaimToken, $inbox->claim_token);
        $this->assertSame(WebhookInboxState::Processing, $inbox->state);
    }

    #[Test]
    public function completed_and_failed_rows_are_no_ops(): void
    {
        $completedInbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Completed,
        ]);

        $failedInbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Failed,
        ]);

        $this->service->process($completedInbox->id);
        $this->service->process($failedInbox->id);

        $completedInbox->refresh();
        $failedInbox->refresh();

        $this->assertEquals(WebhookInboxState::Completed, $completedInbox->state);
        $this->assertEquals(WebhookInboxState::Failed, $failedInbox->state);
    }

    #[Test]
    public function dispatch_recoverable_finds_due_received_rows(): void
    {
        Queue::fake();

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'available_at' => null,
        ]);

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'available_at' => now()->subMinute(),
        ]);

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'available_at' => now()->addHour(),
        ]);

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Completed,
        ]);

        $result = $this->service->dispatchRecoverable(10);

        $this->assertEquals(2, $result['selected']);
        $this->assertEquals(2, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['failed']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 2);
    }

    #[Test]
    public function dispatch_recoverable_finds_expired_processing_rows(): void
    {
        Queue::fake();

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'claim_expires_at' => now()->subMinute(),
        ]);

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'claim_expires_at' => now()->addMinute(),
        ]);

        WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Failed,
        ]);

        $result = $this->service->dispatchRecoverable(10);

        $this->assertEquals(1, $result['selected']);
        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['failed']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 1);
    }

    #[Test]
    public function dispatch_recoverable_fails_expired_rows_that_exhausted_attempts(): void
    {
        Queue::fake();

        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'attempts' => 5,
            'claim_token' => 'expired-token',
            'claimed_at' => now()->subMinutes(6),
            'claim_expires_at' => now()->subMinute(),
        ]);

        $result = $this->service->dispatchRecoverable(10);

        $this->assertEquals(0, $result['selected']);
        $this->assertEquals(0, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['failed']);
        $inbox->refresh();
        $this->assertEquals(WebhookInboxState::Failed, $inbox->state);
        $this->assertNull($inbox->claim_token);
        $this->assertNotNull($inbox->failed_at);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function dispatch_recoverable_respects_limit(): void
    {
        Queue::fake();

        WebhookInbox::factory()->count(15)->create([
            'state' => WebhookInboxState::Received,
            'available_at' => null,
        ]);

        $result = $this->service->dispatchRecoverable(10);

        $this->assertEquals(10, $result['selected']);
        $this->assertEquals(10, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['failed']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 10);
    }

    #[Test]
    public function legacy_seconds_timestamp_is_normalized_when_read_from_database(): void
    {
        // Create a legacy row with seconds timestamp (as it would be before fix)
        $secondsTimestamp = 1728278400; // Seconds
        $expectedMilliseconds = 1728278400000; // Expected after normalization

        $inbox = WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'legacy-test-key',
            'event_type' => 'meeting.updated',
            'provider_occurred_at' => $secondsTimestamp, // Stored as seconds
            'state' => WebhookInboxState::Received,
        ]);

        // When accessed, the accessor should normalize to milliseconds
        $this->assertEquals($expectedMilliseconds, $inbox->provider_occurred_at);
    }

    #[Test]
    public function already_milliseconds_timestamp_is_not_double_converted(): void
    {
        // Create a row with milliseconds timestamp (as it would be after fix)
        $millisecondsTimestamp = 1728278400000; // Already in milliseconds

        $inbox = WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'milliseconds-test-key',
            'event_type' => 'meeting.updated',
            'provider_occurred_at' => $millisecondsTimestamp, // Stored as milliseconds
            'state' => WebhookInboxState::Received,
        ]);

        // When accessed, should NOT multiply again (avoid double conversion)
        $this->assertEquals($millisecondsTimestamp, $inbox->provider_occurred_at);
    }

    #[Test]
    public function null_timestamp_remains_null(): void
    {
        // Create a row with null timestamp (delete webhooks)
        $inbox = WebhookInbox::factory()->create([
            'provider' => 'zoom',
            'event_key' => 'null-timestamp-key',
            'event_type' => 'meeting.deleted',
            'provider_occurred_at' => null,
            'state' => WebhookInboxState::Received,
        ]);

        // Should remain null
        $this->assertNull($inbox->provider_occurred_at);
    }
}
