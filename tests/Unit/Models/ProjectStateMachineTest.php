<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ProjectStage;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

/**
 * Unit tests for Project state machine validation.
 *
 * Tests the HasStateMachine trait implementation on the Project model,
 * verifying that stage transitions are properly validated according to the
 * defined validTransitions() rules.
 *
 * Level: Unit testing
 */
class ProjectStateMachineTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /**
     * @return array<string, array{from: ProjectStage, to: ProjectStage}>
     */
    public static function validTransitionsProvider(): array
    {
        return [
            'planning to design' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Design],
            'planning to postponed' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Postponed],
            'design to planning' => ['from' => ProjectStage::Design, 'to' => ProjectStage::Planning],
            'design to development' => ['from' => ProjectStage::Design, 'to' => ProjectStage::Development],
            'design to postponed' => ['from' => ProjectStage::Design, 'to' => ProjectStage::Postponed],
            'development to design' => ['from' => ProjectStage::Development, 'to' => ProjectStage::Design],
            'development to testing' => ['from' => ProjectStage::Development, 'to' => ProjectStage::Testing],
            'development to postponed' => ['from' => ProjectStage::Development, 'to' => ProjectStage::Postponed],
            'testing to development' => ['from' => ProjectStage::Testing, 'to' => ProjectStage::Development],
            'testing to delivery' => ['from' => ProjectStage::Testing, 'to' => ProjectStage::Delivery],
            'testing to postponed' => ['from' => ProjectStage::Testing, 'to' => ProjectStage::Postponed],
            'delivery to development' => ['from' => ProjectStage::Delivery, 'to' => ProjectStage::Development],
            'delivery to completed' => ['from' => ProjectStage::Delivery, 'to' => ProjectStage::Completed],
            'delivery to postponed' => ['from' => ProjectStage::Delivery, 'to' => ProjectStage::Postponed],
            'postponed to planning' => ['from' => ProjectStage::Postponed, 'to' => ProjectStage::Planning],
            'postponed to design' => ['from' => ProjectStage::Postponed, 'to' => ProjectStage::Design],
            'postponed to development' => ['from' => ProjectStage::Postponed, 'to' => ProjectStage::Development],
            'postponed to testing' => ['from' => ProjectStage::Postponed, 'to' => ProjectStage::Testing],
            'postponed to delivery' => ['from' => ProjectStage::Postponed, 'to' => ProjectStage::Delivery],
        ];
    }

    /**
     * @return array<string, array{from: ProjectStage, to: ProjectStage}>
     */
    public static function invalidTransitionsProvider(): array
    {
        return [
            'completed to planning' => ['from' => ProjectStage::Completed, 'to' => ProjectStage::Planning],
            'completed to development' => ['from' => ProjectStage::Completed, 'to' => ProjectStage::Development],
            'completed to testing' => ['from' => ProjectStage::Completed, 'to' => ProjectStage::Testing],
            'completed to delivery' => ['from' => ProjectStage::Completed, 'to' => ProjectStage::Delivery],
            'completed to postponed' => ['from' => ProjectStage::Completed, 'to' => ProjectStage::Postponed],
            'planning to development' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Development],
            'planning to testing' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Testing],
            'planning to delivery' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Delivery],
            'planning to completed' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Completed],
            'design to testing' => ['from' => ProjectStage::Design, 'to' => ProjectStage::Testing],
            'design to delivery' => ['from' => ProjectStage::Design, 'to' => ProjectStage::Delivery],
            'design to completed' => ['from' => ProjectStage::Design, 'to' => ProjectStage::Completed],
            'development to delivery' => ['from' => ProjectStage::Development, 'to' => ProjectStage::Delivery],
            'development to completed' => ['from' => ProjectStage::Development, 'to' => ProjectStage::Completed],
            'testing to completed' => ['from' => ProjectStage::Testing, 'to' => ProjectStage::Completed],
            'delivery to planning' => ['from' => ProjectStage::Delivery, 'to' => ProjectStage::Planning],
            'delivery to design' => ['from' => ProjectStage::Delivery, 'to' => ProjectStage::Design],
            'delivery to testing' => ['from' => ProjectStage::Delivery, 'to' => ProjectStage::Testing],
            'same state' => ['from' => ProjectStage::Planning, 'to' => ProjectStage::Planning],
        ];
    }

    /**
     * @test
     *
     * @dataProvider validTransitionsProvider
     */
    public function valid_transitions_succeed(ProjectStage $from, ProjectStage $to): void
    {
        $project = Project::factory()->for($this->user)->create([
            'stage_id' => $from->value,
        ]);

        $project->transitionTo($to, 'stage_id');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'stage_id' => $to->value,
        ]);
    }

    /**
     * @test
     *
     * @dataProvider invalidTransitionsProvider
     */
    public function invalid_transitions_throw_exception(ProjectStage $from, ProjectStage $to): void
    {
        $project = Project::factory()->for($this->user)->create([
            'stage_id' => $from->value,
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage("Cannot transition from {$from->name} to {$to->name}");

        $project->transitionTo($to, 'stage_id');
    }

    /** @test */
    public function invalid_state_transition_exception_has_correct_status_code(): void
    {
        $project = Project::factory()->for($this->user)->create([
            'stage_id' => ProjectStage::Completed->value,
        ]);

        try {
            $project->transitionTo(ProjectStage::Planning, 'stage_id');
            $this->fail('Expected InvalidStateTransitionException to be thrown');
        } catch (InvalidStateTransitionException $e) {
            $this->assertEquals(422, $e->status());
            $this->assertEquals('invalid_state_transition', $e->errorCode());
        }
    }

    /** @test */
    public function invalid_state_transition_exception_includes_meta_information(): void
    {
        $project = Project::factory()->for($this->user)->create([
            'stage_id' => ProjectStage::Completed->value,
        ]);

        try {
            $project->transitionTo(ProjectStage::Planning, 'stage_id');
            $this->fail('Expected InvalidStateTransitionException to be thrown');
        } catch (InvalidStateTransitionException $e) {
            $meta = $e->meta(request());
            $this->assertEquals(Project::class, $meta['model']);
            $this->assertEquals('Completed', $meta['current_state']);
            $this->assertEquals('Planning', $meta['attempted_state']);
        }
    }
}
