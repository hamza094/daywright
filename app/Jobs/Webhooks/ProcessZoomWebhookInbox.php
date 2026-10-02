<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessZoomWebhookInbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(public int $webhookInboxId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(ZoomWebhookInboxService $service): void
    {
        $service->process($this->webhookInboxId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Process Zoom webhook inbox job failed', [
            'webhook_inbox_id' => $this->webhookInboxId,
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
        ]);
    }
}
