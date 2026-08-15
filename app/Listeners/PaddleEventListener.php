<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Log;
use Laravel\Paddle\Events\WebhookReceived;
use Throwable;

final readonly class PaddleEventListener
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {}

    /**
     * Handle the received Paddle webhook event.
     */
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $eventName = $payload['alert_name'] ?? 'unknown';
        $subscriptionId = $payload['subscription_id'] ?? null;

        // Log all received payloads
        Log::info('Paddle webhook received', [
            'event' => $eventName,
            'subscription_id' => $subscriptionId,
        ]);

        // Map Paddle webhook events to audit log events
        $auditEvent = $this->mapToAuditEvent($eventName);

        if ($auditEvent) {
            $this->auditLogService->log(
                event: $auditEvent,
                auditable: null,
                oldValues: null,
                newValues: [
                    'paddle_event' => $eventName,
                    'subscription_id' => $subscriptionId,
                    'alert_name' => $eventName,
                    'user_email' => $payload['email'] ?? null,
                ],
                metadata: [
                    'paddle_payload' => $payload,
                ]
            );
        }

        if ($payload['alert_name'] === 'subscription_payment_succeeded') {
            try {
                // Your custom logic here...
                // Log or trigger something if needed
                Log::info('Handled annual payment success');
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function mapToAuditEvent(string $paddleEvent): ?string
    {
        return match ($paddleEvent) {
            'subscription_created' => 'billing.subscription_created',
            'subscription_updated' => 'billing.subscription_updated',
            'subscription_cancelled' => 'billing.subscription_cancelled',
            'subscription_payment_succeeded' => 'billing.payment_succeeded',
            'subscription_payment_failed' => 'billing.payment_failed',
            default => null,
        };
    }
}
