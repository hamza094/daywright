<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ScrambleDocsTest extends TestCase
{
    public function test_docs_json_uses_relative_api_server_url(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();
        $this->assertSame('/api', $response->json('servers.0.url'));
        $this->assertArrayHasKey('/v1/users/{user}/avatar', $response->json('paths'));
    }
}
