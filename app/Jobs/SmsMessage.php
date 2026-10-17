<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\Integrations\Sms\SmsMissingRecipientException;
use App\Exceptions\Integrations\Sms\SmsProviderException;
use App\Models\Message;
use App\Models\Project;
use App\Services\VonageSmsService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class SmsMessage implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        private readonly int $projectId,
        private readonly int $messageId
    ) {
        $this->onQueue('default');
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['project:'.$this->projectId, 'message:'.$this->messageId];
    }

    /**
     * Execute the job.
     */
    public function handle(VonageSmsService $service): void
    {
        // Early return if batch is cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $message = Message::find($this->messageId);
        $project = Project::find($this->projectId);

        // Idempotency check: don't send if message is already delivered
        if ($message?->delivered) {
            return;
        }

        if (! $project || ! $message) {
            Log::warning('SmsMessage job failed: Required models not found', [
                'message_id' => $this->messageId,
                'project_id' => $this->projectId,
                'project_exists' => $project !== null,
                'message_exists' => $message !== null,
            ]);

            $exception = new RuntimeException("Required models not found for SmsMessage [project: {$this->projectId}, message: {$this->messageId}]");
            $this->fail($exception);

            throw $exception;
        }

        try {
            $service->send($project, $message);
        } catch (SmsMissingRecipientException $e) {
            Log::warning('SmsMessage failed permanently: missing recipient', [
                'message_id' => $this->messageId,
                'project_id' => $this->projectId,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);

            throw $e;
        } catch (SmsProviderException $e) {
            if (! $e->isTransient()) {
                Log::error('SmsMessage failed permanently: provider rejected', [
                    'message_id' => $this->messageId,
                    'project_id' => $this->projectId,
                    'provider_status' => $e->providerStatus(),
                    'error' => $e->getMessage(),
                ]);

                $this->fail($e);

                throw $e;
            }

            // Transient error: re-throw so queue worker retries up to $this->tries
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SmsMessage job failed', [
            'message_id' => $this->messageId,
            'project_id' => $this->projectId,
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
            'exception_message' => $exception->getMessage(),
        ]);
    }
}
