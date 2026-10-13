<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ValidatesLimitOption;
use App\Services\Webhooks\ZoomWebhookInboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecoverPendingWebhooks extends Command
{
    use ValidatesLimitOption;

    private const int MAX_LIMIT = 500;

    protected $signature = 'webhooks:recover-pending {--limit=100}';

    protected $description = 'Recover pending and expired Zoom webhook inbox rows';

    public function __construct(
        private readonly ZoomWebhookInboxService $zoomWebhookInboxService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = $this->parseLimit(self::MAX_LIMIT);

        if ($limit === null) {
            return 1;
        }

        $this->info("Recovering pending webhooks (limit: {$limit})...");

        try {
            $result = $this->zoomWebhookInboxService->dispatchRecoverable($limit);

            $this->info("Selected: {$result['selected']}");
            $this->info("Dispatched: {$result['dispatched']}");
            $this->info("Skipped: {$result['skipped']}");
            $this->info("Failed: {$result['failed']}");

            if ($result['failed'] > 0) {
                $this->warn('Some webhooks failed to dispatch');

                return 1;
            }

            $this->info('Webhook recovery completed successfully.');

            return 0;
        } catch (Throwable $exception) {
            Log::error('Webhook recovery command failed', [
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            $this->error('Webhook recovery failed: '.$exception::class);

            return 1;
        }
    }
}
