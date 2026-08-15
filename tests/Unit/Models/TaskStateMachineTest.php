<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\TaskSystemStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

/**
 * Unit tests for Task state machine validation.
 *
 * Tests the HasStateMachine trait implementation on the Task model,
 * verifying that state transitions are properly validated according to the
 * defined validTransitions() rules.
 *
 * Level: Unit testing
 */
class TaskStateMachineTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /**
     * @return array<string, array{from: TaskSystemStatus, to: TaskSystemStatus}>
     */
    public static function validTransitionsProvider(): array
    {
        return [
            'pending to in_progress' => ['from' => TaskSystemStatus::Pending, 'to' => TaskSystemStatus::InProgress],
            'pending to cancelled' => ['from' => TaskSystemStatus::Pending, 'to' => TaskSystemStatus::Cancelled],
            'in_progress to under_review' => ['from' => TaskSystemStatus::InProgress, 'to' => TaskSystemStatus::UnderReview],
            'in_progress to pending' => ['from' => TaskSystemStatus::InProgress, 'to' => TaskSystemStatus::Pending],
            'in_progress to cancelled' => ['from' => TaskSystemStatus::InProgress, 'to' => TaskSystemStatus::Cancelled],
            'under_review to completed' => ['from' => TaskSystemStatus::UnderReview, 'to' => TaskSystemStatus::Completed],
            'under_review to in_progress' => ['from' => TaskSystemStatus::UnderReview, 'to' => TaskSystemStatus::InProgress],
            'under_review to cancelled' => ['from' => TaskSystemStatus::UnderReview, 'to' => TaskSystemStatus::Cancelled],
        ];
    }

    /**
     * @return array<string, array{from: TaskSystemStatus, to: TaskSystemStatus}>
     */
    public static function invalidTransitionsProvider(): array
    {
        return [
            'completed to pending' => ['from' => TaskSystemStatus::Completed, 'to' => TaskSystemStatus::Pending],
            'completed to in_progress' => ['from' => TaskSystemStatus::Completed, 'to' => TaskSystemStatus::InProgress],
            'completed to under_review' => ['from' => TaskSystemStatus::Completed, 'to' => TaskSystemStatus::UnderReview],
            'cancelled to pending' => ['from' => TaskSystemStatus::Cancelled, 'to' => TaskSystemStatus::Pending],
            'cancelled to in_progress' => ['from' => TaskSystemStatus::Cancelled, 'to' => TaskSystemStatus::InProgress],
            'cancelled to under_review' => ['from' => TaskSystemStatus::Cancelled, 'to' => TaskSystemStatus::UnderReview],
            'pending to completed' => ['from' => TaskSystemStatus::Pending, 'to' => TaskSystemStatus::Completed],
            'pending to under_review' => ['from' => TaskSystemStatus::Pending, 'to' => TaskSystemStatus::UnderReview],
            'same state' => ['from' => TaskSystemStatus::Pending, 'to' => TaskSystemStatus::Pending],
        ];
    }

    /**
     * @test
     *
     * @dataProvider validTransitionsProvider
     */
    public function valid_transitions_succeed(TaskSystemStatus $from, TaskSystemStatus $to): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status_id' => $from->value,
        ]);

        $task->transitionTo($to, 'status_id');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status_id' => $to->value,
        ]);
    }

    /**
     * @test
     *
     * @dataProvider invalidTransitionsProvider
     */
    public function invalid_transitions_throw_exception(TaskSystemStatus $from, TaskSystemStatus $to): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status_id' => $from->value,
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage("Cannot transition from {$from->name} to {$to->name}");

        $task->transitionTo($to, 'status_id');
    }

    /** @test */
    public function invalid_state_transition_exception_has_correct_status_code(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status_id' => TaskSystemStatus::Completed->value,
        ]);

        try {
            $task->transitionTo(TaskSystemStatus::Pending, 'status_id');
            $this->fail('Expected InvalidStateTransitionException to be thrown');
        } catch (InvalidStateTransitionException $e) {
            $this->assertEquals(422, $e->status());
            $this->assertEquals('invalid_state_transition', $e->errorCode());
        }
    }

    /** @test */
    public function invalid_state_transition_exception_includes_meta_information(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'status_id' => TaskSystemStatus::Completed->value,
        ]);

        try {
            $task->transitionTo(TaskSystemStatus::Pending, 'status_id');
            $this->fail('Expected InvalidStateTransitionException to be thrown');
        } catch (InvalidStateTransitionException $e) {
            $meta = $e->meta(request());
            $this->assertEquals(Task::class, $meta['model']);
            $this->assertEquals('Completed', $meta['current_state']);
            $this->assertEquals('Pending', $meta['attempted_state']);
        }
    }
}
