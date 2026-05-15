<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Contracts\Docs;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ScrambleDocsTest extends TestCase
{
    public function test_docs_json_exposes_released_public_surface(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        $this->assertSame('/api', $docs['servers'][0]['url'] ?? null);

        foreach ([
            '/v1/users/search',
            '/v1/twofactor/login-confirm',
            '/v1/users/{user}/avatar',
        ] as $path) {
            $this->assertArrayHasKey($path, $paths);
        }

        foreach ([
            '/v1/admin/users',
            '/v1/password/reset/{token}',
            '/v1/projects/{project}/export',
            '/v1/projects/{project}/messages/scheduled',
            '/v1/projects/{project}/meetings',
            '/v1/webhooks/zoom/meetings/update',
        ] as $path) {
            $this->assertArrayNotHasKey($path, $paths);
        }

        foreach ([
            '/v1/login' => 'post',
            '/v1/session/login' => 'post',
            '/v1/auth/callback/{provider}' => 'get',
            '/v1/twofactor/login-confirm' => 'post',
        ] as $path => $method) {
            $this->assertSame([], $paths[$path][$method]['security'] ?? null);
        }
    }

    public function test_docs_json_keeps_released_operations_described_and_tagged(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $tags = $docs['tags'] ?? [];

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

        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertNotSame('', trim((string) ($operation['summary'] ?? '')), "Missing summary for {$method} {$path}");
                $this->assertNotSame('', trim((string) ($operation['description'] ?? '')), "Missing description for {$method} {$path}");
                $this->assertNotSame([], $operation['tags'] ?? [], "Missing tags for {$method} {$path}");
            }
        }

        $this->assertSame(['Authentication'], $paths['/v1/session/login']['post']['tags'] ?? null);
        $this->assertSame(['API Tokens'], $paths['/v1/api-tokens']['get']['tags'] ?? null);
        $this->assertSame(['Projects'], $paths['/v1/projects/{project}']['get']['tags'] ?? null);
        $this->assertSame(['Conversations'], $paths['/v1/projects/{project}/conversations']['get']['tags'] ?? null);
        $this->assertSame(['Tasks'], $paths['/v1/projects/{project}/tasks']['get']['tags'] ?? null);
    }

    public function test_docs_json_uses_shared_public_error_components(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $components = $docs['components'] ?? [];
        $schemas = $components['schemas'] ?? [];
        $responses = $components['responses'] ?? [];

        foreach ([
            'PublicUnauthenticatedErrorEnvelope',
            'PublicApiValidationErrorEnvelope',
            'PublicForbiddenErrorEnvelope',
            'PublicNotFoundErrorEnvelope',
            'PublicRateLimitErrorEnvelope',
            'PublicInternalServerErrorEnvelope',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }

        $this->assertSame(
            '#/components/schemas/PublicUnauthenticatedErrorEnvelope',
            $responses['PublicUnauthenticatedError']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            'Authentication is required.',
            $schemas['PublicUnauthenticatedErrorEnvelope']['example']['message'] ?? null,
        );
        $this->assertSame(
            42,
            $schemas['PublicRateLimitErrorEnvelope']['example']['meta']['retry_after_seconds'] ?? null,
        );

        $this->assertSame(
            '#/components/responses/PublicUnauthenticatedError',
            $paths['/v1/users/{user}']['get']['responses']['401']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/responses/PublicForbiddenError',
            $paths['/v1/projects/{project}/force']['delete']['responses']['403']['$ref'] ?? null,
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

    public function test_docs_json_documents_auth_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        $this->assertSame(
            '#/components/schemas/LoginRequestData',
            $paths['/v1/login']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/TwoFactorLoginRequestData',
            $paths['/v1/twofactor/login-confirm']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $expectedSuccessSchemas = [
            '#/components/schemas/AuthenticatedSession',
            '#/components/schemas/TwoFactorChallenge',
        ];

        $this->assertSame(
            $expectedSuccessSchemas,
            $this->schemaRefs($paths['/v1/session/login']['post']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? []),
        );
        $this->assertSame(
            $expectedSuccessSchemas,
            $this->schemaRefs($paths['/v1/auth/callback/{provider}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? []),
        );
        $this->assertContains(
            '#/components/schemas/CurrentUser',
            $this->schemaRefs($paths['/v1/users/me']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? []),
        );

        foreach ([
            'AuthenticatedSession',
            'CurrentUser',
            'TwoFactorChallenge',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }
    }

    public function test_docs_json_documents_user_and_subscription_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        $this->assertSame(
            '#/components/schemas/ApiTokenStoreRequestData',
            $paths['/v1/api-tokens']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/PersonalAccessToken',
            $paths['/v1/api-tokens']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/SubscriptionPlanRequestData',
            $paths['/v1/users/me/subscription']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertContains(
            '#/components/schemas/SubscriptionDetails',
            $this->schemaRefs($paths['/v1/users/me/subscription']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? []),
        );
        $this->assertSame(
            'string',
            $paths['/v1/users/me/subscription']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties']['paylink']['type'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/UserSummary',
            $paths['/v1/users']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/UserProfile',
            $paths['/v1/users/{user}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ProjectInvitation',
            $paths['/v1/users/me/invitations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );

        foreach ([
            'PersonalAccessToken',
            'SubscriptionDetails',
            'UserSummary',
            'UserProfile',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }
    }

    public function test_docs_json_documents_dashboard_and_notification_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        $chartParams = $paths['/v1/dashboard/chart-data']['get']['parameters'] ?? [];
        $notificationParams = $paths['/v1/notifications']['get']['parameters'] ?? [];

        $this->assertSame(['year', 'month'], array_column($chartParams, 'name'));
        $this->assertSame('integer', $chartParams[0]['schema']['type'] ?? null);
        $this->assertSame('integer', $chartParams[1]['schema']['type'] ?? null);

        $this->assertSame(['filter[status]', 'page', 'per_page'], array_column($notificationParams, 'name'));
        $this->assertSame(
            '#/components/schemas/NotificationResource',
            $paths['/v1/notifications']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/NotificationStatusUpdateRequestData',
            $paths['/v1/notifications/{notification}/status']['patch']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        foreach ([
            'NotificationResource',
            'NotificationStatusUpdateRequestData',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }
    }

    public function test_docs_json_documents_project_collaboration_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        $this->assertSame(
            '#/components/schemas/ProjectStoreRequestData',
            $paths['/v1/projects']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/PublicProject',
            $paths['/v1/projects/{project}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/TaskStoreRequestData',
            $paths['/v1/projects/{project}/tasks']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ProjectTaskListItem',
            $paths['/v1/projects/{project}/tasks']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ConversationStoreRequestData',
            $paths['/v1/projects/{project}/conversations']['post']['requestBody']['content']['multipart/form-data']['schema']['$ref'] ?? null,
        );
        $this->assertContains(
            '#/components/schemas/ProjectConversation',
            $this->schemaRefs($paths['/v1/projects/{project}/conversations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? []),
        );
        $this->assertSame(
            '#/components/schemas/ProjectInvitationStoreRequestData',
            $paths['/v1/projects/{project}/invitations']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/PendingInvitationUser',
            $paths['/v1/projects/{project}/invitations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/TaskMember',
            $paths['/v1/projects/{project}/tasks/{task}/members/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ProjectUsageLimit',
            $schemas['PublicProject']['properties']['limits']['items']['$ref'] ?? null,
        );

        foreach ([
            'ProjectConversation',
            'ProjectUsageLimit',
            'TaskMember',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
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

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function schemaRefs(array $schema): array
    {
        $refs = [];

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $refs[] = $schema['$ref'];
        }

        if (isset($schema['items']['$ref']) && is_string($schema['items']['$ref'])) {
            $refs[] = $schema['items']['$ref'];
        }

        foreach (($schema['anyOf'] ?? []) as $option) {
            if (isset($option['$ref']) && is_string($option['$ref'])) {
                $refs[] = $option['$ref'];
            }

            if (isset($option['items']['$ref']) && is_string($option['items']['$ref'])) {
                $refs[] = $option['items']['$ref'];
            }
        }

        return array_values(array_unique($refs));
    }
}
