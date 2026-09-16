<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Actions\Webhooks\Zoom\HandlePersistedZoomWebhookAction;
use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use App\DataTransferObjects\Zoom\MeetingEndedWebhookData;
use App\DataTransferObjects\Zoom\MeetingStartedWebhookData;
use App\DataTransferObjects\Zoom\MeetingUpdatedWebhookData;
use App\Enums\WebhookInboxState;
use App\Jobs\Webhooks\ProcessZoomWebhookInbox;
use App\Models\WebhookInbox;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class ZoomWebhookInboxService
{
    private const int MAX_ATTEMPTS = 5;

    private const array RETRY_BACKOFF = [
        1 => 15,
        2 => 30,
        3 => 60,
        4 => 120,
    ];

    public function __construct(
        private HandlePersistedZoomWebhookAction $handlePersistedWebhook,
    ) {}

    /**
     * Persists each provider event once and dispatches only newly accepted webhooks.
     */
    public function accept(
        string $eventKey,
        string $eventType,
        string $requestId,
        ?int $occurredAt,
        MeetingUpdatedWebhookData|MeetingStartedWebhookData|MeetingEndedWebhookData|MeetingDeletedWebhookData $data,
    ): WebhookInbox {
        $inbox = WebhookInbox::query()->createOrFirst(
            [
                'provider' => 'zoom',
                'event_key' => $eventKey,
            ],
            [
                'event_type' => $eventType,
                'provider_request_id' => $requestId,
                'provider_occurred_at' => $occurredAt,
                'payload' => $data->toArray(),
                'state' => WebhookInboxState::Received,
            ],
        );

        if ($inbox->wasRecentlyCreated) {
            $this->dispatchProcessingJob($inbox);
        }

        return $inbox;
    }

    /**
     * Processes the webhook only when this worker acquires its active claim.
     */
    public function process(int $webhookInboxId): void
    {
        $claimToken = Str::uuid()->toString();
        $claimed = $this->claimForProcessing($webhookInboxId, $claimToken);

        if ($claimed === null) {
            return;
        }

        try {
            $this->handlePersistedWebhook->execute($claimed);
            $this->completeActiveClaim($webhookInboxId, $claimToken);
        } catch (Throwable $exception) {
            $this->recordProcessingFailure($webhookInboxId, $claimToken, $claimed->attempts, $exception);
        }
    }

    /**
     * Requeues due or lease-expired webhooks and closes exhausted claims.
     *
     * @return array{dispatched: int, failed: int}
     */
    public function redispatchRecoverable(int $limit): array
    {
        $now = now();
        $this->failExpiredExhaustedClaims($now);
        $dispatched = 0;
        $failed = 0;

        $recoverableWebhooks = WebhookInbox::query()
            ->claimableAt($now, self::MAX_ATTEMPTS)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($recoverableWebhooks as $webhook) {
            if ($this->dispatchProcessingJob($webhook)) {
                $dispatched++;
            } else {
                $failed++;
            }
        }

        return [
            'dispatched' => $dispatched,
            'failed' => $failed,
        ];
    }

    /**
     * Only one worker can acquire the row while its five-minute claim remains valid.
     * Incrementing attempts in the same update counts only successful claims.
     */
    private function claimForProcessing(int $webhookInboxId, string $claimToken): ?WebhookInbox
    {
        $claimedAt = now();

        $affected = WebhookInbox::query()
            ->whereKey($webhookInboxId)
            ->claimableAt($claimedAt, self::MAX_ATTEMPTS)
            ->increment('attempts', 1, [
                'state' => WebhookInboxState::Processing,
                'claim_token' => $claimToken,
                'claimed_at' => $claimedAt,
                'claim_expires_at' => $claimedAt->copy()->addMinutes(5),
            ]);

        if ($affected !== 1) {
            return null;
        }

        return WebhookInbox::query()
            ->whereKey($webhookInboxId)
            ->where('claim_token', $claimToken)
            ->first();
    }

    private function completeActiveClaim(int $webhookInboxId, string $claimToken): void
    {
        $completedAt = now();

        WebhookInbox::query()
            ->whereKey($webhookInboxId)
            ->unexpiredClaimOwnedBy($claimToken, $completedAt)
            ->update([
                'state' => WebhookInboxState::Completed,
                'completed_at' => $completedAt,
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
                'last_error_class' => null,
                'last_error_code' => null,
            ]);
    }

    private function recordProcessingFailure(int $webhookInboxId, string $claimToken, int $attempts, Throwable $exception): void
    {
        report($exception);

        Log::warning('Webhook processing failed', [
            'webhook_inbox_id' => $webhookInboxId,
            'attempts' => $attempts,
            'exception_class' => $exception::class,
            'exception_code' => $exception->getCode(),
        ]);

        $failedAt = now();
        $shouldRetry = $attempts < self::MAX_ATTEMPTS;

        // Attempts 1-4 become available after a delay; attempt 5 ends processing.
        // Recovery dispatches the next job once available_at is due.
        $retryAt = $shouldRetry
            ? $failedAt->copy()->addSeconds(self::RETRY_BACKOFF[$attempts])
            : null;

        WebhookInbox::query()
            ->whereKey($webhookInboxId)
            ->unexpiredClaimOwnedBy($claimToken, $failedAt)
            ->update([
                'state' => $shouldRetry ? WebhookInboxState::Received : WebhookInboxState::Failed,
                'available_at' => $retryAt,
                'failed_at' => $shouldRetry ? null : $failedAt,
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
                'last_error_class' => $exception::class,
                'last_error_code' => $exception->getCode(),
            ]);
    }

    /**
     * Leaves the durable inbox row recoverable when the queue cannot be reached.
     */
    private function dispatchProcessingJob(WebhookInbox $webhookInbox): bool
    {
        try {
            ProcessZoomWebhookInbox::dispatch($webhookInbox->id);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('Failed to dispatch webhook inbox job', [
                'webhook_inbox_id' => $webhookInbox->id,
                'event_key' => $webhookInbox->event_key,
                'event_type' => $webhookInbox->event_type,
            ]);

            return false;
        }
    }

    /**
     * Terminates rows abandoned by a worker during their final attempt.
     */
    private function failExpiredExhaustedClaims(CarbonInterface $at): void
    {
        WebhookInbox::query()
            ->processingWithExpiredClaimAt($at)
            ->where('attempts', '>=', self::MAX_ATTEMPTS)
            ->update([
                'state' => WebhookInboxState::Failed,
                'failed_at' => $at,
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
                'last_error_class' => 'lease_expired',
            ]);
    }
}
