<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class FeatureFlagsTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    #[Test]
    public function non_admin_user_cannot_access_project_messaging_routes(): void
    {
        $this->getJson($this->apiV1ProjectRoute('projects.messages.scheduled', $this->project))
            ->assertForbidden();
    }

    #[Test]
    public function admin_user_can_access_project_messaging_routes(): void
    {
        $this->user->forceFill(['is_admin' => true])->save();

        $this->getJson($this->apiV1ProjectRoute('projects.messages.scheduled', $this->project))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function non_admin_user_cannot_access_project_export(): void
    {
        $this->getJson($this->apiV1ProjectRoute('projects.export', $this->project))
            ->assertForbidden();
    }

    #[Test]
    public function admin_user_can_access_project_export(): void
    {
        $this->user->forceFill(['is_admin' => true])->save();

        $this->getJson($this->apiV1ProjectRoute('projects.export', $this->project))
            ->assertOk();
    }

    #[Test]
    public function me_response_not_includes_feature_flags_for_non_admin_user_if_not_active(): void
    {
        $this->getJson($this->apiV1Route('users.me.show'))
            ->assertOk()
            ->assertJsonPath('data.features', []);
    }

    #[Test]
    public function me_response_includes_feature_flags_for_admin_user(): void
    {
        $this->user->forceFill(['is_admin' => true])->save();

        $this->getJson($this->apiV1Route('users.me.show'))
            ->assertOk()
            ->assertJsonPath('data.features.project_export', true)
            ->assertJsonPath('data.features.project_messaging', true);
    }
}
