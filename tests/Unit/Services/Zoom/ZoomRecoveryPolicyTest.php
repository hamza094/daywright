<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Zoom;

use App\Models\Meeting;
use App\Services\Zoom\ZoomRecoveryPolicy;
use Tests\TestCase;

final class ZoomRecoveryPolicyTest extends TestCase
{
    private ZoomRecoveryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ZoomRecoveryPolicy;
    }

    /** @test */
    public function it_returns_max_attempts(): void
    {
        $this->assertEquals(5, $this->policy->maxAttempts());
    }

    /** @test */
    public function it_returns_backoff_seconds_for_known_attempts(): void
    {
        $this->assertEquals(60, $this->policy->getBackoffSeconds(0));
        $this->assertEquals(300, $this->policy->getBackoffSeconds(1));
        $this->assertEquals(900, $this->policy->getBackoffSeconds(2));
        $this->assertEquals(3600, $this->policy->getBackoffSeconds(3));
    }

    /** @test */
    public function it_returns_default_backoff_for_unknown_attempts(): void
    {
        $this->assertEquals(3600, $this->policy->getBackoffSeconds(10));
    }

    /** @test */
    public function it_validates_claim_with_valid_token_and_future_lease(): void
    {
        $meeting = new Meeting([
            'sync_claim_token' => 'expected-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->assertTrue($this->policy->isClaimValid($meeting, 'expected-token'));
    }

    /** @test */
    public function it_invalidates_claim_with_wrong_token(): void
    {
        $meeting = new Meeting([
            'sync_claim_token' => 'wrong-token',
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->assertFalse($this->policy->isClaimValid($meeting, 'expected-token'));
    }

    /** @test */
    public function it_invalidates_claim_with_expired_lease(): void
    {
        $meeting = new Meeting([
            'sync_claim_token' => 'expected-token',
            'sync_lease_expires_at' => now()->subMinutes(5),
        ]);

        $this->assertFalse($this->policy->isClaimValid($meeting, 'expected-token'));
    }

    /** @test */
    public function it_invalidates_claim_with_null_lease(): void
    {
        $meeting = new Meeting([
            'sync_claim_token' => 'expected-token',
            'sync_lease_expires_at' => null,
        ]);

        $this->assertFalse($this->policy->isClaimValid($meeting, 'expected-token'));
    }

    /** @test */
    public function it_validates_operation_id_when_matching(): void
    {
        $meeting = new Meeting([
            'sync_operation_id' => 'op-123',
        ]);

        $this->assertTrue($this->policy->isOperationCurrent($meeting, 'op-123'));
    }

    /** @test */
    public function it_invalidates_operation_id_when_changed(): void
    {
        $meeting = new Meeting([
            'sync_operation_id' => 'new-op-id',
        ]);

        $this->assertFalse($this->policy->isOperationCurrent($meeting, 'old-op-id'));
    }

    /** @test */
    public function it_allows_retry_when_under_max_attempts(): void
    {
        $this->assertTrue($this->policy->shouldRetry(0));
        $this->assertTrue($this->policy->shouldRetry(4));
    }

    /** @test */
    public function it_denies_retry_when_at_or_over_max_attempts(): void
    {
        $this->assertFalse($this->policy->shouldRetry(5));
        $this->assertFalse($this->policy->shouldRetry(10));
    }

    /** @test */
    public function it_skips_when_lease_is_expired(): void
    {
        $meeting = new Meeting([
            'sync_lease_expires_at' => now()->subMinutes(5),
        ]);

        $this->assertTrue($this->policy->shouldSkipExpiredLease($meeting, now()));
    }

    /** @test */
    public function it_does_not_skip_when_lease_is_future(): void
    {
        $meeting = new Meeting([
            'sync_lease_expires_at' => now()->addMinutes(5),
        ]);

        $this->assertFalse($this->policy->shouldSkipExpiredLease($meeting, now()));
    }

    /** @test */
    public function it_does_not_skip_when_lease_is_null(): void
    {
        $meeting = new Meeting([
            'sync_lease_expires_at' => null,
        ]);

        $this->assertFalse($this->policy->shouldSkipExpiredLease($meeting, now()));
    }

    /** @test */
    public function it_returns_claim_state_array(): void
    {
        $state = $this->policy->getClaimState();

        $this->assertIsArray($state);
        $this->assertArrayHasKey('sync_claim_token', $state);
        $this->assertArrayHasKey('sync_lease_expires_at', $state);
        $this->assertArrayHasKey('sync_available_at', $state);
        $this->assertNull($state['sync_claim_token']);
        $this->assertNull($state['sync_lease_expires_at']);
        $this->assertNull($state['sync_available_at']);
    }
}
