<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscription\ResolveSubscriptionOperation as ResolverAction;
use App\Models\SubscriptionOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolveSubscriptionOperation extends Command
{
    protected $signature = 'subscriptions:resolve-operation
        {operation_uuid : The public operation UUID}
        {--mark-completed : Verify with Paddle and mark completed}
        {--mark-failed : Mark the operation failed with an incident reference}
        {--reference= : Required incident or ticket reference when marking failed}';

    protected $description = 'Manually resolve a subscription operation that requires review';

    public function __construct(
        private readonly ResolverAction $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $validated = $this->validateAndPrepareInput();

        if ($validated === null) {
            return self::FAILURE;
        }

        return $this->executeResolution($validated);
    }

    /**
     * @return array{operation: SubscriptionOperation, uuid: string, mark_completed: bool, reference: string}|null
     */
    private function validateAndPrepareInput(): ?array
    {
        $uuid = (string) $this->argument('operation_uuid');
        $markCompleted = (bool) $this->option('mark-completed');
        $markFailed = (bool) $this->option('mark-failed');
        $reference = $this->option('reference');

        if ($markCompleted === $markFailed) {
            $this->error('Provide exactly one of --mark-completed or --mark-failed.');

            return null;
        }

        if ($markFailed && (! is_string($reference) || trim($reference) === '')) {
            $this->error('A ticket or incident reference is required when marking an operation failed.');

            return null;
        }

        $operation = SubscriptionOperation::query()
            ->where('operation_uuid', $uuid)
            ->first();

        if (! $operation instanceof SubscriptionOperation) {
            $this->error("Subscription operation not found: {$uuid}");

            return null;
        }

        if ($operation->isTerminal()) {
            $this->error("Operation {$uuid} is already in a terminal state [{$operation->status->value}].");

            return null;
        }

        return [
            'operation' => $operation,
            'uuid' => $uuid,
            'mark_completed' => $markCompleted,
            'reference' => (string) $reference,
        ];
    }

    /**
     * @param  array{operation: SubscriptionOperation, uuid: string, mark_completed: bool, reference: string}  $validated
     */
    private function executeResolution(array $validated): int
    {
        $operation = $validated['operation'];
        $uuid = $validated['uuid'];
        $markCompleted = $validated['mark_completed'];
        $reference = $validated['reference'];

        try {
            if ($markCompleted) {
                return $this->resolveCompleted($operation);
            }

            return $this->resolveFailed($operation, $reference);
        } catch (Throwable $exception) {
            Log::error('Manual subscription operation resolution failed', [
                'operation_uuid' => $uuid,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);

            $this->error('Resolution failed. Review the operation and safe error metadata in the logs.');

            return self::FAILURE;
        }
    }

    private function resolveCompleted(SubscriptionOperation $operation): int
    {
        $resolved = $this->resolver->markCompleted($operation);

        Log::info('Manual subscription operation resolution completed', [
            'operation_uuid' => $resolved->operation_uuid,
            'status' => $resolved->status->value,
            'resolution' => 'mark_completed',
        ]);

        $this->info("Operation {$resolved->operation_uuid} was verified with Paddle and marked completed.");

        return self::SUCCESS;
    }

    private function resolveFailed(SubscriptionOperation $operation, string $reference): int
    {
        $resolved = $this->resolver->markFailed($operation, $reference);

        Log::info('Manual subscription operation resolution completed', [
            'operation_uuid' => $resolved->operation_uuid,
            'status' => $resolved->status->value,
            'resolution' => 'mark_failed',
            'reference' => $reference,
        ]);

        $this->info("Operation {$resolved->operation_uuid} was marked failed with reference [{$reference}].");

        return self::SUCCESS;
    }
}
