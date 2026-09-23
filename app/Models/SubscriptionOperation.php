<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionOperationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Paddle\Subscription as PaddleSubscription;
use LogicException;

/**
 * @property-read int $id
 * @property string $operation_uuid
 * @property int $user_id
 * @property int|null $subscription_id
 * @property string|null $paddle_subscription_id
 * @property SubscriptionOperationType $type
 * @property string|null $target_plan
 * @property string $idempotency_key_hash
 * @property string $request_fingerprint
 * @property SubscriptionOperationStatus $status
 * @property int $attempts
 * @property CarbonInterface|null $available_at
 * @property string|null $claim_token
 * @property CarbonInterface|null $claimed_at
 * @property CarbonInterface|null $claim_expires_at
 * @property CarbonInterface|null $provider_attempted_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $failed_at
 * @property CarbonInterface|null $last_attempt_at
 * @property string|null $last_error_class
 * @property string|null $last_error_code
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read User $user
 * @property-read PaddleSubscription|null $subscription
 *
 * @method static Builder<SubscriptionOperation> active()
 * @method static Builder<SubscriptionOperation> claimableAt(CarbonInterface $at, int $maxAttempts)
 * @method static Builder<SubscriptionOperation> readyForRecoveryAt(CarbonInterface $at)
 * @method static Builder<SubscriptionOperation> processingWithExpiredClaimAt(CarbonInterface $at)
 * @method static Builder<SubscriptionOperation> unexpiredClaimOwnedBy(string $claimToken, CarbonInterface $at)
 */
final class SubscriptionOperation extends Model
{
    /** @use HasFactory<SubscriptionOperationFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'claim_token',
        'idempotency_key_hash',
        'request_fingerprint',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => SubscriptionOperationType::class,
        'status' => SubscriptionOperationStatus::class,
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'claimed_at' => 'datetime',
        'claim_expires_at' => 'datetime',
        'provider_attempted_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaddleSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PaddleSubscription::class, 'subscription_id');
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to active (non-terminal) operations.
     *
     * @param  Builder<SubscriptionOperation>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            SubscriptionOperationStatus::Pending,
            SubscriptionOperationStatus::Processing,
            SubscriptionOperationStatus::Unknown,
            SubscriptionOperationStatus::ManualReview,
        ]);
    }

    /**
     * Eligible operations due for background recovery.
     *
     * @param  Builder<SubscriptionOperation>  $query
     */
    public function scopeClaimableAt(Builder $query, CarbonInterface $at, int $maxAttempts = 5): void
    {
        $query->where('attempts', '<', $maxAttempts)
            ->readyForRecoveryAt($at);
    }

    /**
     * Keeps the scheduler and the atomic worker claim on the same eligibility rules.
     * The second check is required because another worker may run between selection and claim.
     *
     * @param  Builder<SubscriptionOperation>  $query
     */
    public function scopeReadyForRecoveryAt(Builder $query, CarbonInterface $at): void
    {
        $query->where(function (Builder $eligible) use ($at): void {
            $eligible->where(function (Builder $waiting) use ($at): void {
                $waiting->where('status', SubscriptionOperationStatus::Unknown)
                    ->where(fn (Builder $delay) => $delay
                        ->whereNull('available_at')
                        ->orWhere('available_at', '<=', $at));
            })->orWhere(fn (Builder $expired) => $expired->processingWithExpiredClaimAt($at));
        });
    }

    /**
     * @param  Builder<SubscriptionOperation>  $query
     */
    public function scopeProcessingWithExpiredClaimAt(Builder $query, CarbonInterface $at): void
    {
        $query->where('status', SubscriptionOperationStatus::Processing)
            ->where('claim_expires_at', '<=', $at);
    }

    /**
     * Prevents a worker with an expired or replaced token from modifying operation state.
     *
     * @param  Builder<SubscriptionOperation>  $query
     */
    public function scopeUnexpiredClaimOwnedBy(Builder $query, string $claimToken, CarbonInterface $at): void
    {
        $query->where('status', SubscriptionOperationStatus::Processing)
            ->where('claim_token', $claimToken)
            ->where('claim_expires_at', '>', $at);
    }

    // ==========================================
    // Business Methods
    // ==========================================

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isNonTerminal(): bool
    {
        return $this->status->isNonTerminal();
    }

    public function hasProviderAttempted(): bool
    {
        return $this->provider_attempted_at !== null;
    }

    /**
     * Safely transitions the operation to a new status.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionTo(SubscriptionOperationStatus $target, array $attributes = []): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException("Cannot transition SubscriptionOperation from [{$this->status->value}] to [{$target->value}].");
        }

        $this->status = $target;
        $this->fill($attributes);
        $this->save();
    }

    protected static function newFactory(): SubscriptionOperationFactory
    {
        return SubscriptionOperationFactory::new();
    }
}
