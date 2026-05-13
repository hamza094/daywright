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

    public function test_docs_json_limits_public_scope_for_phase_zero(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');

        $this->assertArrayHasKey('/v1/users/search', $paths);
        $this->assertArrayHasKey('/v1/twofactor/login-confirm', $paths);

        $this->assertArrayNotHasKey('/v1/admin/users', $paths);
        $this->assertArrayNotHasKey('/v1/password/reset/{token}', $paths);
        $this->assertArrayNotHasKey('/v1/projects/{project}/export', $paths);
        $this->assertArrayNotHasKey('/v1/projects/{project}/messages/scheduled', $paths);
        $this->assertArrayNotHasKey('/v1/projects/{project}/meetings', $paths);

        $twoFactorLoginOperation = $paths['/v1/twofactor/login-confirm']['post'] ?? null;

        $this->assertIsArray($twoFactorLoginOperation);
        $this->assertSame([], $twoFactorLoginOperation['security'] ?? []);
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
        $this->assertStringContainsString('Token login does not expose a public 2FA continuation step.', $description);
        $this->assertStringContainsString('does not create a session or issue an access token', $description);
        $this->assertStringContainsString('POST /twofactor/login-confirm', $description);
        $this->assertStringContainsString('## Pagination', $description);
        $this->assertStringContainsString('## Rate Limiting', $description);
        $this->assertStringContainsString('## Idempotency', $description);
        $this->assertStringContainsString('Idempotency-Key', $description);
        $this->assertStringContainsString('## Webhooks', $description);
        $this->assertStringContainsString('x-zm-request-id', $description);
        $this->assertStringContainsString('## Caching', $description);
        $this->assertStringContainsString('## Status Codes', $description);
        $this->assertStringNotContainsString('/api/v2', $description);
    }

    public function test_docs_json_uses_shared_public_tags_for_phase_one(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $tags = $response->json('tags');

        $this->assertSame([
            'Authentication',
            'Users',
            'Invitations',
            'API Tokens',
            'Subscription',
            'Dashboard',
            'Notifications',
            'Projects',
            'Stages',
            'Tasks',
            'Conversations',
        ], array_column($tags, 'name'));

        $this->assertSame(['Authentication'], $paths['/v1/session/login']['post']['tags'] ?? null);
        $this->assertSame(['API Tokens'], $paths['/v1/api-tokens']['get']['tags'] ?? null);
        $this->assertSame(['Projects'], $paths['/v1/projects/{project}']['get']['tags'] ?? null);
        $this->assertSame(['Conversations'], $paths['/v1/projects/{project}/conversations']['get']['tags'] ?? null);
        $this->assertSame(['Tasks'], $paths['/v1/projects/{project}/tasks']['get']['tags'] ?? null);
    }

    public function test_docs_json_uses_shared_public_error_components_for_phase_one(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $components = $response->json('components');

        $this->assertArrayHasKey('PublicApiErrorEnvelope', $components['schemas'] ?? []);
        $this->assertArrayHasKey('PublicApiValidationErrorEnvelope', $components['schemas'] ?? []);

        $this->assertSame(
            '#/components/schemas/PublicApiErrorEnvelope',
            $components['responses']['PublicUnauthenticatedError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/PublicApiValidationErrorEnvelope',
            $components['responses']['PublicValidationError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/responses/PublicUnauthenticatedError',
            $paths['/v1/users/{user}']['get']['responses']['401']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/responses/PublicNotFoundError',
            $paths['/v1/users/{user}']['get']['responses']['404']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/responses/PublicValidationError',
            $paths['/v1/users/{user}/avatar']['post']['responses']['422']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/responses/PublicInternalServerError',
            $paths['/v1/login']['post']['responses']['500']['$ref'] ?? null,
        );
    }

    public function test_docs_json_uses_stable_public_schema_names_for_phase_one(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $schemas = $response->json('components.schemas');

        $this->assertArrayHasKey('AuthenticatedUser', $schemas);
        $this->assertArrayHasKey('AuthenticatedSession', $schemas);
        $this->assertArrayHasKey('TokenStoreResponse', $schemas);
        $this->assertArrayHasKey('TaskStatusIndex', $schemas);
        $this->assertArrayHasKey('PublicProject', $schemas);
        $this->assertArrayHasKey('PublicTask', $schemas);
        $this->assertArrayHasKey('PublicStage', $schemas);
    }

    public function test_docs_json_documents_auth_request_bodies_and_current_user_payload_for_phase_two(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $schemas = $response->json('components.schemas');

        $this->assertSame(
            '#/components/schemas/LoginRequestData',
            $paths['/v1/login']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/ForgotPasswordRequestData',
            $paths['/v1/forgot-password']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/ResetPasswordRequestData',
            $paths['/v1/reset-password']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/TwoFactorLoginRequestData',
            $paths['/v1/twofactor/login-confirm']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $currentUserDataOptions = $paths['/v1/users/me']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];

        $this->assertContains(
            '#/components/schemas/CurrentUser',
            array_column($currentUserDataOptions, '$ref'),
        );

        $this->assertArrayHasKey('AuthenticatedToken', $schemas);
        $this->assertArrayHasKey('CurrentUser', $schemas);
        $this->assertArrayHasKey('TwoFactorChallenge', $schemas);
    }

    public function test_docs_json_documents_multi_shape_auth_success_responses_for_phase_two(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');

        $sessionLoginSuccessOptions = $paths['/v1/session/login']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $oauthCallbackSuccessOptions = $paths['/v1/auth/callback/{provider}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];

        $this->assertSame([
            '#/components/schemas/AuthenticatedSession',
            '#/components/schemas/TwoFactorChallenge',
        ], array_column($sessionLoginSuccessOptions, '$ref'));

        $this->assertSame([
            '#/components/schemas/AuthenticatedSession',
            '#/components/schemas/TwoFactorChallenge',
        ], array_column($oauthCallbackSuccessOptions, '$ref'));
    }
}
