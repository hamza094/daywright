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

    public function test_docs_json_contains_api_overview_content(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $description = (string) $response->json('info.description');

        $this->assertStringContainsString('# DayWright API Overview', $description);
        $this->assertStringContainsString('## Authentication', $description);
        $this->assertStringContainsString('Laravel Sanctum personal access tokens', $description);
        $this->assertStringContainsString('## Pagination', $description);
        $this->assertStringContainsString('## Rate Limiting', $description);
        $this->assertStringContainsString('## Idempotency', $description);
        $this->assertStringContainsString('Idempotency-Key', $description);
        $this->assertStringContainsString('## Webhooks', $description);
        $this->assertStringContainsString('x-zm-request-id', $description);
        $this->assertStringContainsString('## Caching', $description);
        $this->assertStringContainsString('## Status Codes', $description);
        $this->assertStringContainsString('/api/v2', $description);
    }
}
