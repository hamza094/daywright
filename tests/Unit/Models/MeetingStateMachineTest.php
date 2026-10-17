<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Meeting\MeetingSyncOperationType;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Meeting;
use App\QueryBuilder\MeetingBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Meeting\MeetingTestHelper;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

use function Safe\json_encode;

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
            'pending to creating' => ['from' => MeetingSyncStatus::Pending, 'to' => MeetingSyncStatus::Creating],
            'pending to active' => ['from' => MeetingSyncStatus::Pending, 'to' => MeetingSyncStatus::Active],
            'pending to failed' => ['from' => MeetingSyncStatus::Pending, 'to' => MeetingSyncStatus::Failed],
            'creating to active' => ['from' => MeetingSyncStatus::Creating, 'to' => MeetingSyncStatus::Active],
            'creating to failed' => ['from' => MeetingSyncStatus::Creating, 'to' => MeetingSyncStatus::Failed],
            'creating to create_unknown' => ['from' => MeetingSyncStatus::Creating, 'to' => MeetingSyncStatus::CreateUnknown],
            'create_unknown to active' => ['from' => MeetingSyncStatus::CreateUnknown, 'to' => MeetingSyncStatus::Active],
            'create_unknown to failed' => ['from' => MeetingSyncStatus::CreateUnknown, 'to' => MeetingSyncStatus::Failed],
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
            'create_unknown to creating' => ['from' => MeetingSyncStatus::CreateUnknown, 'to' => MeetingSyncStatus::Creating],
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
        $this->expectExceptionMessage("Cannot transition from {$from->name} to {$to->name}");

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
            $this->assertEquals('Deleted', $meta['current_state']);
            $this->assertEquals('Active', $meta['attempted_state']);
        }
    }

    /** @test */
    public function create_unknown_to_creating_is_invalid(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_status' => MeetingSyncStatus::CreateUnknown,
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('Cannot transition from CreateUnknown to Creating');

        $meeting->transitionTo(MeetingSyncStatus::Creating, 'sync_status');
    }

    /** @test */
    public function sync_operation_id_is_unique(): void
    {
        $operationId = Str::uuid()->toString();

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_id' => $operationId,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_id' => $operationId,
        ]);
    }

    /** @test */
    public function recovery_timestamps_are_cast_correctly(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_started_at' => Carbon::now(),
            'sync_lease_expires_at' => Carbon::now()->addMinutes(5),
            'sync_available_at' => Carbon::now()->addMinute(),
        ]);

        $this->assertInstanceOf(Carbon::class, $meeting->sync_started_at);
        $this->assertInstanceOf(Carbon::class, $meeting->sync_lease_expires_at);
        $this->assertInstanceOf(Carbon::class, $meeting->sync_available_at);
    }

    /** @test */
    public function sync_operation_type_is_persisted(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_type' => MeetingSyncOperationType::Update,
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'sync_operation_type' => MeetingSyncOperationType::Update->value,
        ]);

        $this->assertEquals(MeetingSyncOperationType::Update, $meeting->sync_operation_type);
    }

    /** @test */
    public function sync_payload_is_persisted(): void
    {
        $payload = json_encode(['topic' => 'Updated Topic', 'duration' => 45]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_payload' => $payload,
        ]);

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
        ]);

        $this->assertEquals($payload, $meeting->sync_payload);
    }

    /** @test */
    public function sync_payload_is_encrypted(): void
    {
        $payload = json_encode(['topic' => 'Updated Topic', 'duration' => 45]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_payload' => $payload,
        ]);

        $rawPayload = DB::table('meetings')->where('id', $meeting->id)->value('sync_payload');

        $this->assertNotEquals($payload, $rawPayload);
        $this->assertStringNotContainsString('Updated Topic', $rawPayload);
    }

    /** @test */
    public function sync_payload_is_restored_correctly_through_model(): void
    {
        $payload = json_encode(['topic' => 'Updated Topic', 'duration' => 45]);

        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_payload' => $payload,
        ]);

        $reloadedMeeting = Meeting::find($meeting->id);

        $this->assertEquals($payload, $reloadedMeeting->sync_payload);
    }

    /** @test */
    public function is_create_operation_helper_returns_correct_value(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_type' => MeetingSyncOperationType::Create,
        ]);

        $this->assertTrue($meeting->isCreateOperation());
        $this->assertFalse($meeting->isUpdateOperation());
        $this->assertFalse($meeting->isDeleteOperation());
    }

    /** @test */
    public function is_update_operation_helper_returns_correct_value(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_type' => MeetingSyncOperationType::Update,
        ]);

        $this->assertFalse($meeting->isCreateOperation());
        $this->assertTrue($meeting->isUpdateOperation());
        $this->assertFalse($meeting->isDeleteOperation());
    }

    /** @test */
    public function is_delete_operation_helper_returns_correct_value(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_type' => MeetingSyncOperationType::Delete,
        ]);

        $this->assertFalse($meeting->isCreateOperation());
        $this->assertFalse($meeting->isUpdateOperation());
        $this->assertTrue($meeting->isDeleteOperation());
    }

    /** @test */
    public function operation_helpers_return_false_when_type_is_null(): void
    {
        $meeting = MeetingTestHelper::createMeeting($this->project, $this->user, [
            'sync_operation_type' => null,
        ]);

        $this->assertFalse($meeting->isCreateOperation());
        $this->assertFalse($meeting->isUpdateOperation());
        $this->assertFalse($meeting->isDeleteOperation());
    }

    /** @test */
    public function meeting_uses_custom_query_builder(): void
    {
        $query = Meeting::query();

        $this->assertInstanceOf(MeetingBuilder::class, $query);
    }

    /** @test */
    public function query_builder_has_ready_for_any_recovery_at_method(): void
    {
        $query = Meeting::query();

        $this->assertTrue(method_exists($query, 'readyForAnyRecoveryAt'));
    }
}
