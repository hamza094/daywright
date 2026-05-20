<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ActivityTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */

    // filter
    public function it_filters_activities_by_project_specified(): void
    {
        $task = $this->project->addTask('test task');

        $response = $this->getJson($this->apiV1ProjectRoute('projects.activities', $this->project))->assertOk();

        $response->assertJsonStructure([
            'data' => [
                ['description', 'time', 'subject', 'user'],
            ],
            'meta' => ['current_page', 'last_page', 'path', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);

        $data = $response->json()['data'];

        $this->assertCount(2, $data);
        $this->assertEquals('Task "'.($task->title).'" added', $data[0]['description']);
        $this->assertEquals('New project created', $data[1]['description']);
        $this->assertSame($this->user->id, $data[0]['user']['id']);
        $this->assertSame($this->user->uuid, $data[0]['user']['uuid']);
        $this->assertSame($this->user->username, $data[0]['user']['username']);
        $this->assertArrayNotHasKey('email', $data[0]['user']);
    }

    /** @test */
    public function it_filters_activities_by_tasks(): void
    {
        $task = $this->project->addTask('test task');

        $response = $this->getJson($this->activityUrl(['type' => 'tasks']))
            ->assertJsonCount(1, 'data')
            ->assertOk();

        $this->assertEquals('Task "'.($task->title).'" added', $response->json()['data'][0]['description']);
    }

    /** @test */
    public function it_rejects_legacy_top_level_alias(): void
    {
        $this->project->addTask('test task');

        $this->getJson($this->apiV1ProjectRoute('projects.activities', $this->project, query: [
            'tasks' => 1,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tasks']);
    }

    /** @test */
    public function it_rejects_unsupported_nested_filter_keys(): void
    {
        $this->project->addTask('test task');

        $this->getJson($this->apiV1ProjectRoute('projects.activities', $this->project, query: [
            'filter' => ['status' => 'mine'],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter']);
    }

    /** @test */
    public function it_filters_activities_by_authenticated_user(): void
    {
        $this->project->addTask('test task');

        $response = $this->getJson($this->activityUrl(['type' => 'mine']))->assertOk();

        $this->assertEquals('New project created', $response->json()['data'][1]['description']);
    }

    /** @test */
    public function it_shows_error_when_no_related_activities_are_found(): void
    {
        $this->project->addTask('test task');

        $response = $this->getJson($this->activityUrl(['type' => 'members']))
            ->assertOk();

        $response->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('links.prev', null)
            ->assertJsonPath('links.next', null);
    }

    /** @test */
    public function it_validates_activity_filter_type(): void
    {
        $this->getJson($this->activityUrl(['type' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.type');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function activityUrl(array $filters = []): string
    {
        return $this->apiV1ProjectRoute('projects.activities', $this->project, query: [
            'filter' => $filters,
        ]);
    }
}
