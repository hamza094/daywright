<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

/**
 * Unit tests for Meeting state machine validation.
 *
 * Tests the HasStateMachine trait implementation on the Meeting model,
 * verifying that state transitions are properly validated according to the
 * defined validTransitions() rules.
 *
 * Level: Unit testing
 */
class MeetingStateMachineTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /**
     * @return array<string, array{from: MeetingSyncStatus, to: MeetingSyncStatus}>
     */
    public static function validTransitionsProvider(): array
    {
        return [
            'pending to active' => ['from' => MeetingSyncStatus::Pending, 'to' => MeetingSyncStatus::Active],
            'pending to failed' => ['from' => MeetingSyncStatus::Pending, 'to' => MeetingSyncStatus::Failed],
            'active to updating' => ['from' => MeetingSyncStatus::Active, 'to' => MeetingSyncStatus::Updating],
            'active to deleting' => ['from' => MeetingSyncStatus::Active, 'to' => MeetingSyncStatus::Deleting],
            'active to failed' => ['from' => MeetingSyncStatus::Active, 'to' => MeetingSyncStatus::Failed],
            'updating to active' => ['from' => MeetingSyncStatus::Updating, 'to' => MeetingSyncStatus::Active],
            'updating to update_failed' => ['from' => MeetingSyncStatus::Updating, 'to' => MeetingSyncStatus::UpdateFailed],
            'deleting to deleted' => ['from' => MeetingSyncStatus::Deleting, 'to' => MeetingSyncStatus::Deleted],
            'deleting to delete_failed' => ['from' => MeetingSyncStatus::Deleting, 'to' => MeetingSyncStatus::DeleteFailed],
            'failed to active' => ['from' => MeetingSyncStatus::Failed, 'to' => MeetingSyncStatus::Active],
            'failed to pending' => ['from' => MeetingSyncStatus::Failed, 'to' => MeetingSyncStatus::Pending],
            'update_failed to updating' => ['from' => MeetingSyncStatus::UpdateFailed, 'to' => MeetingSyncStatus::Updating],
            'delete_failed to deleting' => ['from' => MeetingSyncStatus::DeleteFailed, 'to' => MeetingSyncStatus::Deleting],
        ];
    }

    /**
     * @return array<string, array{from: MeetingSyncStatus, to: MeetingSyncStatus}>
     */
    public static function invalidTransitionsProvider(): array
    {
        return [
            'deleted to active' => ['from' => MeetingSyncStatus::Deleted, 'to' => MeetingSyncStatus::Active],
            'deleted to updating' => ['from' => MeetingSyncStatus::Deleted, 'to' => MeetingSyncStatus::Updating],
            'pending to deleting' => ['from' => MeetingSyncStatus::Pending, 'to' => MeetingSyncStatus::Deleting],
            'active to pending' => ['from' => MeetingSyncStatus::Active, 'to' => MeetingSyncStatus::Pending],
            'updating to deleting' => ['from' => MeetingSyncStatus::Updating, 'to' => MeetingSyncStatus::Deleting],
            'deleting to updating' => ['from' => MeetingSyncStatus::Deleting, 'to' => MeetingSyncStatus::Updating],
            'same state' => ['from' => MeetingSyncStatus::Active, 'to' => MeetingSyncStatus::Active],
        ];
    }

    /**
     * @test
     *
     * @dataProvider validTransitionsProvider
     */
    public function valid_transitions_succeed(MeetingSyncStatus $from, MeetingSyncStatus $to): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => $from,
        ]);

        $meeting->transitionTo($to, 'sync_status');

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_status' => $to->value,
        ]);
    }

    /**
     * @test
     *
     * @dataProvider invalidTransitionsProvider
     */
    public function invalid_transitions_throw_exception(MeetingSyncStatus $from, MeetingSyncStatus $to): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => $from,
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage("Cannot transition from {$from->value} to {$to->value}");

        $meeting->transitionTo($to, 'sync_status');
    }

    /** @test */
    public function invalid_state_transition_exception_has_correct_status_code(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Deleted,
        ]);

        try {
            $meeting->transitionTo(MeetingSyncStatus::Active, 'sync_status');
            $this->fail('Expected InvalidStateTransitionException to be thrown');
        } catch (InvalidStateTransitionException $e) {
            $this->assertEquals(422, $e->status());
            $this->assertEquals('invalid_state_transition', $e->errorCode());
        }
    }

    /** @test */
    public function invalid_state_transition_exception_includes_meta_information(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::Deleted,
        ]);

        try {
            $meeting->transitionTo(MeetingSyncStatus::Active, 'sync_status');
            $this->fail('Expected InvalidStateTransitionException to be thrown');
        } catch (InvalidStateTransitionException $e) {
            $meta = $e->meta(request());
            $this->assertEquals(Meeting::class, $meta['model']);
            $this->assertEquals('deleted', $meta['current_state']);
            $this->assertEquals('active', $meta['attempted_state']);
        }
    }
}
