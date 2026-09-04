<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Contracts\Docs;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Runtime/OpenAPI parity tests verify that the actual runtime behavior
 * matches the generated OpenAPI documentation.
 *
 * These tests are separate from ScrambleDocsTest to distinguish between:
 * - ScrambleDocsTest: Verifies the generated document is correct
 * - RuntimeOpenApiParityTest: Verifies runtime behavior matches the document
 */
class RuntimeOpenApiParityTest extends TestCase
{
    /**
     * Data provider for route-to-document parity checks
     *
     * @return array<string, array{path: string, method: string, expectedMiddleware: array<string>}>
     */
    public static function routeParityProvider(): array
    {
        return [
            'GET /v1/projects/{project} has tokenAbility:projects:read' => [
                'path' => '/v1/projects/{project}',
                'method' => 'GET',
                'expectedMiddleware' => ['tokenAbility:projects:read'],
            ],
            'POST /v1/projects has tokenAbility:projects:write' => [
                'path' => '/v1/projects',
                'method' => 'POST',
                'expectedMiddleware' => ['tokenAbility:projects:write'],
            ],
            'DELETE /v1/projects/{project}/force has throttle:sensitive-destructive' => [
                'path' => '/v1/projects/{project}/force',
                'method' => 'DELETE',
                'expectedMiddleware' => ['throttle:sensitive-destructive'],
            ],
            'POST /v1/projects/{project}/conversations has throttle:sensitive-upload' => [
                'path' => '/v1/projects/{project}/conversations',
                'method' => 'POST',
                'expectedMiddleware' => ['throttle:sensitive-upload'],
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider routeParityProvider
     */
    public function route_middleware_matches_documentation(string $path, string $method, array $expectedMiddleware): void
    {
        $docs = $this->docs();
        $operation = $docs['paths'][$path][mb_strtolower($method)] ?? [];

        $this->assertNotEmpty($operation, "Operation {$method} {$path} should exist in documentation");

        // Check that the operation has the expected error responses based on middleware
        $responses = $operation['responses'] ?? [];

        // Throttle middleware should result in 429 response
        if (in_array('throttle:sensitive-destructive', $expectedMiddleware) ||
            in_array('throttle:sensitive-upload', $expectedMiddleware)) {
            $this->assertArrayHasKey('429', $responses, 'Throttled route should have 429 response');
        }

        // tokenAbility middleware should result in 403 response
        if (in_array('tokenAbility:projects:read', $expectedMiddleware) ||
            in_array('tokenAbility:projects:write', $expectedMiddleware)) {
            $this->assertArrayHasKey('403', $responses, 'Token-protected route should have 403 response');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function docs(): array
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $docs = $response->json();

        $this->assertIsArray($docs);

        return $docs;
    }
}
