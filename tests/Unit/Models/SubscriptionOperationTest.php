<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Subscription\SubscriptionOperationStatus;
use App\Enums\Subscription\SubscriptionOperationType;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubscriptionOperationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_subscription_operation_via_factory(): void
    {
        $user = User::factory()->create();
        $operation = SubscriptionOperation::factory()->for($user)->create([
            'type' => SubscriptionOperationType::Swap,
            'target_plan' => 'pro',
            'status' => SubscriptionOperationStatus::Pending,
        ]);

        $this->assertDatabaseHas('subscription_operations', [
            'id' => $operation->id,
            'user_id' => $user->id,
            'type' => 'swap',
            'status' => 'pending',
            'target_plan' => 'pro',
        ]);
        $this->assertSame($user->id, $operation->user->id);
        $this->assertFalse($operation->isTerminal());
        $this->assertTrue($operation->isNonTerminal());
        $this->assertFalse($operation->hasProviderAttempted());
    }

    #[Test]
    public function it_identifies_terminal_and_non_terminal_states(): void
    {
        $terminalStatuses = [
            SubscriptionOperationStatus::Completed,
            SubscriptionOperationStatus::Failed,
        ];

        $nonTerminalStatuses = [
            SubscriptionOperationStatus::Pending,
            SubscriptionOperationStatus::Processing,
            SubscriptionOperationStatus::Unknown,
            SubscriptionOperationStatus::ManualReview,
        ];

        foreach ($terminalStatuses as $status) {
            $this->assertTrue($status->isTerminal(), "Status {$status->value} should be terminal.");
            $this->assertFalse($status->isNonTerminal(), "Status {$status->value} should not be non-terminal.");
        }

        foreach ($nonTerminalStatuses as $status) {
            $this->assertFalse($status->isTerminal(), "Status {$status->value} should not be terminal.");
            $this->assertTrue($status->isNonTerminal(), "Status {$status->value} should be non-terminal.");
        }
    }

    #[Test]
    public function it_validates_valid_lifecycle_transitions(): void
    {
        $operation = SubscriptionOperation::factory()->create([
            'status' => SubscriptionOperationStatus::Pending,
        ]);

        // Pending -> Processing
        $operation->transitionTo(SubscriptionOperationStatus::Processing, [
            'claim_token' => 'test-token',
            'claimed_at' => now(),
            'claim_expires_at' => now()->addMinutes(5),
            'provider_attempted_at' => now(),
        ]);
        $this->assertSame(SubscriptionOperationStatus::Processing, $operation->status);
        $this->assertTrue($operation->hasProviderAttempted());

        // Processing -> Unknown
        $operation->transitionTo(SubscriptionOperationStatus::Unknown, [
            'available_at' => now()->addMinute(),
        ]);
        $this->assertSame(SubscriptionOperationStatus::Unknown, $operation->status);

        // Unknown -> ManualReview
        $operation->transitionTo(SubscriptionOperationStatus::ManualReview);
        $this->assertSame(SubscriptionOperationStatus::ManualReview, $operation->status);

        // ManualReview -> Completed
        $operation->transitionTo(SubscriptionOperationStatus::Completed, [
            'completed_at' => now(),
        ]);
        $this->assertSame(SubscriptionOperationStatus::Completed, $operation->status);
        $this->assertTrue($operation->isTerminal());
    }

    #[Test]
    public function it_rejects_invalid_transitions(): void
    {
        $operation = SubscriptionOperation::factory()->completed()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot transition SubscriptionOperation from [completed] to [processing].');

        $operation->transitionTo(SubscriptionOperationStatus::Processing);
    }

    #[Test]
    public function it_hides_sensitive_internal_fields_from_serialization(): void
    {
        $operation = SubscriptionOperation::factory()->create([
            'claim_token' => 'secret-claim-token',
            'idempotency_key_hash' => 'hash-123',
            'request_fingerprint' => 'fingerprint-123',
        ]);

        $array = $operation->toArray();

        $this->assertArrayNotHasKey('claim_token', $array);
        $this->assertArrayNotHasKey('idempotency_key_hash', $array);
        $this->assertArrayNotHasKey('request_fingerprint', $array);
    }

    #[Test]
    public function it_filters_active_non_terminal_operations(): void
    {
        $user = User::factory()->create();

        SubscriptionOperation::factory()->for($user)->create(['status' => SubscriptionOperationStatus::Pending]);
        SubscriptionOperation::factory()->for($user)->processing()->create();
        SubscriptionOperation::factory()->for($user)->unknown()->create();
        SubscriptionOperation::factory()->for($user)->manualReview()->create();
        SubscriptionOperation::factory()->for($user)->completed()->create();
        SubscriptionOperation::factory()->for($user)->failed()->create();

        $activeOperations = SubscriptionOperation::query()->where('user_id', $user->id)->active()->get();

        $this->assertCount(4, $activeOperations);
        foreach ($activeOperations as $op) {
            $this->assertTrue($op->isNonTerminal());
        }
    }

    #[Test]
    public function it_scopes_claimable_operations_for_recovery(): void
    {
        $now = Carbon::parse('2026-09-21 12:00:00');
        Carbon::setTestNow($now);

        // Ready: unknown and available_at has passed
        $readyUnknown = SubscriptionOperation::factory()->unknown()->create([
            'available_at' => $now->copy()->subMinute(),
            'attempts' => 1,
        ]);

        // Not ready: unknown but available_at in future
        SubscriptionOperation::factory()->unknown()->create([
            'available_at' => $now->copy()->addMinutes(5),
            'attempts' => 1,
        ]);

        // Ready: processing but claim expired
        $expiredProcessing = SubscriptionOperation::factory()->processing()->create([
            'claim_expires_at' => $now->copy()->subMinute(),
            'attempts' => 1,
        ]);

        // Not ready: processing and claim unexpired
        SubscriptionOperation::factory()->processing()->create([
            'claim_expires_at' => $now->copy()->addMinutes(3),
            'attempts' => 1,
        ]);

        // Not ready: max attempts exceeded
        SubscriptionOperation::factory()->unknown()->create([
            'available_at' => $now->copy()->subMinute(),
            'attempts' => 5,
        ]);

        $claimable = SubscriptionOperation::claimableAt($now, maxAttempts: 5)->get();

        $this->assertCount(2, $claimable);
        $this->assertTrue($claimable->contains('id', $readyUnknown->id));
        $this->assertTrue($claimable->contains('id', $expiredProcessing->id));

        Carbon::setTestNow();
    }
}
