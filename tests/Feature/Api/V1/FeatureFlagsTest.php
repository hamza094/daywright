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

    #[Test]
    public function me_response_not_includes_feature_flags_for_non_admin_user_if_not_active(): void
    {
        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJson([
                'features' => [],
            ]);
    }

    #[Test]
    public function me_response_includes_feature_flags_for_admin_user(): void
    {
        $this->user->markAsAdmin();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJson([
                'features' => [
                    'project_export' => true,
                    'project_messaging' => true,
                ],
            ]);
    }
}
