<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookInboxState;
use Carbon\CarbonInterface;
use Database\Factories\WebhookInboxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property string $provider
 * @property string $event_key
 * @property string $event_type
 * @property string $provider_request_id
 * @property int|null $provider_occurred_at
 * @property array<string, mixed> $payload
 * @property WebhookInboxState $state
 * @property int $attempts
 * @property CarbonInterface|null $available_at
 * @property string|null $claim_token
 * @property CarbonInterface|null $claimed_at
 * @property CarbonInterface|null $claim_expires_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $failed_at
 * @property string|null $last_error_class
 * @property int|null $last_error_code
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @method static Builder<WebhookInbox> processingWithExpiredClaimAt(CarbonInterface $at)
 * @method static Builder<WebhookInbox> claimableAt(CarbonInterface $at, int $maxAttempts)
 * @method static Builder<WebhookInbox> unexpiredClaimOwnedBy(string $claimToken, CarbonInterface $at)
 */
final class WebhookInbox extends Model
{
    /** @use HasFactory<WebhookInboxFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'payload',
        'claim_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'encrypted:array',
        'state' => WebhookInboxState::class,
        'provider_occurred_at' => 'integer',
        'last_error_code' => 'integer',
        'available_at' => 'datetime',
        'claimed_at' => 'datetime',
        'claim_expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function newFactory(): WebhookInboxFactory
    {
        return WebhookInboxFactory::new();
    }

    /**
     * @param  Builder<WebhookInbox>  $query
     */
    protected function scopeClaimableAt(Builder $query, CarbonInterface $at, int $maxAttempts): void
    {
        $query->where('attempts', '<', $maxAttempts)
            ->where(function (Builder $eligible) use ($at): void {
                // A waiting webhook can run once its retry delay has elapsed.
                $eligible->where(function (Builder $waiting) use ($at): void {
                    $waiting->where('state', WebhookInboxState::Received)
                        ->where(fn (Builder $delay) => $delay
                            ->whereNull('available_at')
                            ->orWhere('available_at', '<=', $at));
                });

                // A crashed worker's webhook can run again after its claim expires.
                $eligible->orWhere(fn (Builder $expired) => $expired->processingWithExpiredClaimAt($at));
            });
    }

    /**
     * @param  Builder<WebhookInbox>  $query
     */
    protected function scopeProcessingWithExpiredClaimAt(Builder $query, CarbonInterface $at): void
    {
        $query->where('state', WebhookInboxState::Processing)
            ->where('claim_expires_at', '<=', $at);
    }

    /**
     * Prevents a worker with an expired or replaced token from changing inbox state.
     *
     * @param  Builder<WebhookInbox>  $query
     */
    protected function scopeUnexpiredClaimOwnedBy(Builder $query, string $claimToken, CarbonInterface $at): void
    {
        $query->where('state', WebhookInboxState::Processing)
            ->where('claim_token', $claimToken)
            ->where('claim_expires_at', '>', $at);
    }
}
