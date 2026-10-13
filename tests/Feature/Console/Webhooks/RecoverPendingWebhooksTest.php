<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Webhooks;

use App\Enums\WebhookInboxState;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\WebhookInbox;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RecoverPendingWebhooksTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function it_dispatches_due_webhooks_and_reports_counts(): void
    {
        Queue::fake();

        $inbox = WebhookInbox::factory()->create([
            'state' => WebhookInboxState::Received,
            'available_at' => now()->subSecond(),
        ]);

        $this->artisan('webhooks:recover-pending')
            ->expectsOutput('Selected: 1')
            ->expectsOutput('Dispatched: 1')
            ->expectsOutput('Skipped: 0')
            ->expectsOutput('Failed: 0')
            ->assertSuccessful();

        Queue::assertPushed(
            ProcessZoomWebhookInbox::class,
            fn (ProcessZoomWebhookInbox $job): bool => $job->webhookInboxId === $inbox->id,
        );
    }

    #[Test]
    public function it_rejects_negative_limit(): void
    {
        $this->artisan('webhooks:recover-pending', ['--limit' => -1])
            ->expectsOutput('Limit must be between 1 and 500')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_rejects_zero_limit(): void
    {
        $this->artisan('webhooks:recover-pending', ['--limit' => 0])
            ->expectsOutput('Limit must be between 1 and 500')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_captures_limit_at_max(): void
    {
        $this->artisan('webhooks:recover-pending', ['--limit' => 999])
            ->expectsOutput('Selected: 0')
            ->assertExitCode(0);
    }
}
