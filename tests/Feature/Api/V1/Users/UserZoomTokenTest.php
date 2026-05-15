<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;

class UserZoomTokenTest extends TestCase
{
    use InteractsWithZoom,ProjectSetup,RefreshDatabase;

    /** @test */
    public function successfully_get_zak_token(): void
    {
        $this->fakeZoom();

        $response = $this->getJson($this->apiV1Route('users.me.zoom-token'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['zak_token'],
            ]);
    }

    /** @test */
    public function successfully_get_zoom_jwt_token(): void
    {
        $response = $this->getJson($this->apiV1Route('users.me.zoom-jwt-token', query: [
            'role' => 1,
            'meetingId' => 123456789,
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['jwt_token'],
            ]);
    }
}
