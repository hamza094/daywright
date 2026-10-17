<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs\Webhooks;

use App\Enums\WebhookInboxState;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\WebhookInbox;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessZoomWebhookInboxTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_calls_service_process_method(): void
    {
        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'event_type' => 'meeting.deleted',
            'payload' => [
                'meetingId' => 999999999,
                'requestId' => 'req-123',
            ],
        ]);

        $service = $this->app->make(ZoomWebhookInboxService::class);
        $job = new ProcessZoomWebhookInbox($inbox->id);
        $job->handle($service);

        $inbox->refresh();
        $this->assertEquals(WebhookInboxState::Completed, $inbox->state);
        $this->assertEquals(1, $inbox->attempts);
    }

    #[Test]
    public function job_is_configured_for_webhooks_queue(): void
    {
        $job = new ProcessZoomWebhookInbox(1);

        $this->assertEquals('webhooks', $job->queue);
    }

    #[Test]
    public function job_has_single_retry_policy(): void
    {
        $job = new ProcessZoomWebhookInbox(1);

        $this->assertEquals(1, $job->tries);
    }

    #[Test]
    public function job_has_timeout_configuration(): void
    {
        $job = new ProcessZoomWebhookInbox(1);

        $this->assertEquals(60, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
    }

    #[Test]
    public function job_uses_integer_webhook_inbox_id(): void
    {
        $job = new ProcessZoomWebhookInbox(123);

        $this->assertIsInt($job->webhookInboxId);
        $this->assertEquals(123, $job->webhookInboxId);
    }
}
