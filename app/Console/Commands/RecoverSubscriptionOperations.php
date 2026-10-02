<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscription\RecoverSubscriptionOperations as BatchRecoverAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RecoverSubscriptionOperations extends Command
{
    protected $signature = 'subscriptions:recover-operations {--limit=25}';

    protected $description = 'Recover due unknown and expired processing subscription operations';

    public function __construct(
        private readonly BatchRecoverAction $batchRecover,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Recovering subscription operations (limit: {$limit})...");

        try {
            $result = $this->batchRecover->execute($limit);

            $this->info("Selected: {$result['selected']}");
            $this->info("Recovered: {$result['recovered']}");
            $this->info("Unresolved: {$result['unresolved']}");
            $this->info("Manual review: {$result['manual_review']}");
            $this->info("Skipped: {$result['skipped']}");
            $this->info("Failed: {$result['failed']}");

            if ($result['failed'] > 0) {
                $this->warn('Some subscription operations failed to recover.');

                return self::FAILURE;
            }

            $this->info('Subscription operations recovery completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Subscription recovery command failed', [
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            $this->error('Subscription recovery failed: '.$exception::class);

            return self::FAILURE;
        }
    }
}
