<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Traits\ProjectSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    #[Test]
    public function non_admin_user_cannot_access_project_messaging_routes(): void
    {
        $this->getJson($this->project->path().'/messages/scheduled')
            ->assertForbidden();
    }

    #[Test]
    public function admin_user_can_access_project_messaging_routes(): void
    {
        $this->user->markAsAdmin();

        $this->getJson($this->project->path().'/messages/scheduled')
            ->assertNoContent();
    }

    #[Test]
    public function non_admin_user_cannot_access_project_export(): void
    {
        $this->getJson($this->project->path().'/export')
            ->assertForbidden();
    }

    #[Test]
    public function admin_user_can_access_project_export(): void
    {
        $this->user->markAsAdmin();

        $this->getJson($this->project->path().'/export')
            ->assertOk();
    }
}
