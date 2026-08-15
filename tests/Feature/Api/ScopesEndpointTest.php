<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopesEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_endpoint_returns_all_scopes_with_metadata(): void
    {
        $response = $this->getJson('/api/v1/scopes');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'value',
                    'label',
                    'description',
                ],
            ],
        ]);

        $scopes = $response->json('data');
        $this->assertCount(7, $scopes);

        // Verify each scope has the expected structure
        foreach ($scopes as $scope) {
            $this->assertArrayHasKey('value', $scope);
            $this->assertArrayHasKey('label', $scope);
            $this->assertArrayHasKey('description', $scope);
        }

        // Verify all enum values are present
        $scopeValues = array_column($scopes, 'value');
        foreach (ApiScope::values() as $expectedScope) {
            $this->assertContains($expectedScope, $scopeValues);
        }
    }

    public function test_scopes_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/scopes');
        $response->assertStatus(200);
    }
}
