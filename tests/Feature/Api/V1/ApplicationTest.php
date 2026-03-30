<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Exports\ProjectsExport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ApplicationTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function only_allowed_users_can_access_project_features(): void
    {
        $this->withoutExceptionHandling()->postJson($this->project->path().'/tasks',
            ['title' => 'My Project Task'])->assertCreated();

        /** @var User $user */
        $user = User::factory()->create();

        $this->project->invite($user);

        Sanctum::actingAs($user);

        $this->getJson($this->project->path().'/accept-invitation')->assertOk();

        $this->postJson($this->project->path().'/tasks',
            ['title' => 'My Project Task Updated'])->assertCreated();

        Sanctum::actingAs(User::factory()->create());

        $this->expectException(AuthorizationException::class);

        $this->postJson($this->project->path().
           '/tasks');
    }

    /** @test */
    public function auth_user_can_export_project_file(): void
    {
        $this->user->forceFill(['is_admin' => true])->save();
        Excel::fake();
        $this->withoutExceptionHandling()->getJson($this->project->path().'/export');

        Excel::assertDownloaded('Project '.$this->project->name.'.xls', fn (ProjectsExport $export) =>
            // Assert that the correct export is downloaded.
            $export->query()->get()->contains('name', $this->project->name));
    }
}
