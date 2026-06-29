<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Jobs\MailMessage;
use App\Jobs\SmsMessage;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class DispatchProjectMessageAction
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    public function execute(Project $project, Message $message): bool
    {
        $claim = DB::transaction(function () use ($message, $project): ?array {
            $lockedMessage = $this->lockMessage($message);

            if (! $this->canDispatch($lockedMessage)) {
                return null;
            }

            $claimToken = $this->claimToken();

            $lockedMessage->update([
                'batch_id' => $claimToken,
            ]);

            DB::afterCommit(function () use ($project, $lockedMessage, $claimToken): void {
                $this->dispatchClaimedMessage($project, (int) $lockedMessage->getKey(), $claimToken);
            });

            return [
                'message_id' => (int) $lockedMessage->getKey(),
                'claim_token' => $claimToken,
            ];
        }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);

        return $claim !== null;
    }

    private function dispatchClaimedMessage(Project $project, int $messageId, string $claimToken): void
    {
        $claimedMessage = $this->findClaimedMessage($messageId, $claimToken);

        if (! $claimedMessage instanceof Message) {
            return;
        }

        try {
            $batch = $this->dispatchBatch($project, $claimedMessage);

            $this->markBatchStarted($messageId, $claimToken, $batch->id);
        } catch (Throwable $throwable) {
            $this->releaseClaim($messageId, $claimToken);

            throw $throwable;
        }
    }

    private function dispatchBatch(Project $project, Message $message): Batch
    {
        $jobs = $message->type === 'mail'
            ? $message->users->map(fn ($user): MailMessage => new MailMessage($project->id, $message->id, $user->id))
            : collect([new SmsMessage($project->id, $message->id)]);

        return Bus::batch($jobs)
            ->allowFailures()
            /**
             * allowFailures() is used to ensure the batch completes even if individual jobs fail.
             * This means the message will be marked as delivered even if one recipient fails.
             * Trade-off: Without per-recipient delivery tracking, failed recipients won't be retried.
             * This is acceptable for task notifications where delivery is not critical.
             * For critical notifications, per-recipient tracking should be added.
             */
            ->then(function () use ($message): void {
                Message::query()
                    ->whereKey($message->getKey())
                    ->update(['delivered' => true]);
            })
            ->catch(function (Batch $batch, Throwable $throwable): void {
                Log::error('Message batch failed', [
                    'batch_id' => $batch->id,
                    'exception' => $throwable,
                ]);
            })
            ->dispatch();
    }

    private function canDispatch(Message $message): bool
    {
        return ! $message->delivered && $message->batch_id === null;
    }

    private function claimToken(): string
    {
        return 'claim:'.Str::uuid()->toString();
    }

    private function markBatchStarted(int $messageId, string $claimToken, string $batchId): void
    {
        Message::query()
            ->whereKey($messageId)
            ->where('batch_id', $claimToken)
            ->update(['batch_id' => $batchId]);
    }

    private function releaseClaim(int $messageId, string $claimToken): void
    {
        Message::query()
            ->whereKey($messageId)
            ->where('batch_id', $claimToken)
            ->update(['batch_id' => null]);
    }

    private function findClaimedMessage(int $messageId, string $claimToken): ?Message
    {
        return Message::query()
            ->with('users')
            ->whereKey($messageId)
            ->where('batch_id', $claimToken)
            ->first();
    }

    private function lockMessage(Message $message): Message
    {
        return Message::query()
            ->whereKey($message->getKey())
            ->lockForUpdate()
            ->firstOrFail();

    }
}
