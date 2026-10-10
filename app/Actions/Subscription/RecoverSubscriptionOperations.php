<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Enums\Subscription\SubscriptionOperationRecoveryOutcome;
use App\Models\SubscriptionOperation;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class RecoverSubscriptionOperations
{
    public function __construct(
        private RecoverSubscriptionOperation $recoverOperation,
    ) {}

    /**
     * @return array{
     *     selected: int,
     *     recovered: int,
     *     unresolved: int,
     *     manual_review: int,
     *     skipped: int,
     *     failed: int
     * }
     */
    public function execute(int $limit = 25): array
    {
        $operations = SubscriptionOperation::query()
            ->claimableAt(now(), maxAttempts: 5)
            ->limit($limit)
            ->get();

        $recovered = 0;
        $unresolved = 0;
        $manualReview = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($operations as $operation) {
            try {
                match ($this->recoverOperation->execute($operation)) {
                    SubscriptionOperationRecoveryOutcome::Recovered => $recovered++,
                    SubscriptionOperationRecoveryOutcome::Unresolved => $unresolved++,
                    SubscriptionOperationRecoveryOutcome::ManualReview => $manualReview++,
                    SubscriptionOperationRecoveryOutcome::Skipped => $skipped++,
                };
            } catch (Throwable $e) {
                $failed++;
                Log::error('Failed to recover subscription operation', [
                    'operation_uuid' => $operation->operation_uuid,
                    'exception_class' => $e::class,
                    'exception_code' => $e->getCode(),
                ]);
            }
        }

        return [
            'selected' => $operations->count(),
            'recovered' => $recovered,
            'unresolved' => $unresolved,
            'manual_review' => $manualReview,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }
}
