<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Webhooks;

use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\WebhookInboxState;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
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

        Queue::assertPushed(ProcessZoomWebhookInbox::class, function ($job) use ($inbox) {
            return $job->webhookInboxId === $inbox->id;
        });
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

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 1);
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
    public function redispatch_recoverable_finds_due_received_rows(): void
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

        $result = $this->service->redispatchRecoverable(10);

        $this->assertEquals(2, $result['dispatched']);
        $this->assertEquals(0, $result['failed']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 2);
    }

    #[Test]
    public function redispatch_recoverable_finds_expired_processing_rows(): void
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

        $result = $this->service->redispatchRecoverable(10);

        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['failed']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 1);
    }

    #[Test]
    public function redispatch_recoverable_fails_expired_rows_that_exhausted_attempts(): void
    {
        Queue::fake();

        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Processing,
            'attempts' => 5,
            'claim_token' => 'expired-token',
            'claimed_at' => now()->subMinutes(6),
            'claim_expires_at' => now()->subMinute(),
        ]);

        $result = $this->service->redispatchRecoverable(10);

        $this->assertEquals(['dispatched' => 0, 'failed' => 0], $result);
        $inbox->refresh();
        $this->assertEquals(WebhookInboxState::Failed, $inbox->state);
        $this->assertNull($inbox->claim_token);
        $this->assertNotNull($inbox->failed_at);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function redispatch_recoverable_respects_limit(): void
    {
        Queue::fake();

        WebhookInbox::factory()->count(15)->create([
            'state' => WebhookInboxState::Received,
            'available_at' => null,
        ]);

        $result = $this->service->redispatchRecoverable(10);

        $this->assertEquals(10, $result['dispatched']);
        $this->assertEquals(0, $result['failed']);

        Queue::assertPushed(ProcessZoomWebhookInbox::class, 10);
    }
}
