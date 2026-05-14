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
        $this->assertStringNotContainsString('http://daywright.test', $description);
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
        $this->assertArrayHasKey('PublicUnauthenticatedErrorEnvelope', $components['schemas'] ?? []);
        $this->assertArrayHasKey('PublicForbiddenErrorEnvelope', $components['schemas'] ?? []);
        $this->assertArrayHasKey('PublicNotFoundErrorEnvelope', $components['schemas'] ?? []);
        $this->assertArrayHasKey('PublicRateLimitErrorEnvelope', $components['schemas'] ?? []);
        $this->assertArrayHasKey('PublicInternalServerErrorEnvelope', $components['schemas'] ?? []);

        $this->assertSame(
            '#/components/schemas/PublicUnauthenticatedErrorEnvelope',
            $components['responses']['PublicUnauthenticatedError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/PublicForbiddenErrorEnvelope',
            $components['responses']['PublicForbiddenError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/PublicNotFoundErrorEnvelope',
            $components['responses']['PublicNotFoundError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/PublicApiValidationErrorEnvelope',
            $components['responses']['PublicValidationError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/PublicInternalServerErrorEnvelope',
            $components['responses']['PublicInternalServerError']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            'Authentication is required.',
            $components['schemas']['PublicUnauthenticatedErrorEnvelope']['example']['message'] ?? null,
        );

        $this->assertSame(
            'unauthenticated',
            $components['schemas']['PublicUnauthenticatedErrorEnvelope']['example']['code'] ?? null,
        );

        $this->assertSame(
            'You are not authorized to perform this action.',
            $components['schemas']['PublicForbiddenErrorEnvelope']['example']['message'] ?? null,
        );

        $this->assertSame(
            'Resource not found.',
            $components['schemas']['PublicNotFoundErrorEnvelope']['example']['message'] ?? null,
        );

        $this->assertSame(
            'An unexpected server error occurred.',
            $components['schemas']['PublicInternalServerErrorEnvelope']['example']['message'] ?? null,
        );

        $this->assertSame(
            42,
            $components['schemas']['PublicRateLimitErrorEnvelope']['example']['meta']['retry_after_seconds'] ?? null,
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

    public function test_docs_json_documents_phase_three_token_and_subscription_contracts(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $schemas = $response->json('components.schemas');

        $tokenCollectionItemRef = $paths['/v1/api-tokens']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null;
        $subscriptionDataOptions = $paths['/v1/user/subscriptions']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $subscriptionSwapDataOptions = $paths['/v1/subscriptions']['patch']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $subscriptionCancelDataOptions = $paths['/v1/subscriptions']['delete']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];

        $this->assertSame(
            '#/components/schemas/ApiTokenStoreRequestData',
            $paths['/v1/api-tokens']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame('#/components/schemas/PersonalAccessToken', $tokenCollectionItemRef);

        $this->assertSame(
            '#/components/schemas/SubscriptionPlanRequestData',
            $paths['/v1/subscriptions']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/SubscriptionPlanRequestData',
            $paths['/v1/subscriptions']['patch']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertContains(
            '#/components/schemas/SubscriptionDetails',
            array_column($subscriptionDataOptions, '$ref'),
        );

        $this->assertContains(
            '#/components/schemas/SubscriptionDetails',
            array_column($subscriptionSwapDataOptions, '$ref'),
        );

        $this->assertContains(
            '#/components/schemas/SubscriptionDetails',
            array_column($subscriptionCancelDataOptions, '$ref'),
        );

        $subscriptionCheckoutShape = $paths['/v1/subscriptions']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'] ?? [];

        $this->assertSame('string', $subscriptionCheckoutShape['paylink']['type'] ?? null);

        $this->assertSame(
            'monthly',
            $paths['/v1/subscriptions']['delete']['parameters']['0']['example'] ?? null,
        );

        $this->assertArrayHasKey('PersonalAccessToken', $schemas);
        $this->assertArrayHasKey('SubscriptionDetails', $schemas);
        $this->assertArrayNotHasKey('SubscriptionCheckout', $schemas);
        $this->assertArrayHasKey('SubscriptionReceipt', $schemas);
    }

    public function test_docs_json_documents_phase_three_user_account_contracts(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $schemas = $response->json('components.schemas');

        $usersIndexItemRef = $paths['/v1/users']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null;
        $userShowDataRef = $paths['/v1/users/{user}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null;
        $userUpdateDataOptions = $paths['/v1/users/{user}']['put']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $avatarShape = $paths['/v1/users/{user}/avatar']['post']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'] ?? [];
        $invitationItemRef = $paths['/v1/me/invitations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null;

        $this->assertSame(
            '#/components/schemas/UserUpdateRequestData',
            $paths['/v1/users/{user}']['put']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertSame('#/components/schemas/UserSummary', $usersIndexItemRef);

        $this->assertSame('#/components/schemas/UserProfile', $userShowDataRef);

        $this->assertContains(
            '#/components/schemas/UserProfile',
            array_column($userUpdateDataOptions, '$ref'),
        );

        $this->assertSame('string', $avatarShape['avatar']['type'] ?? null);
        $this->assertSame('string', $avatarShape['path']['type'] ?? null);

        $this->assertSame('#/components/schemas/ProjectInvitation', $invitationItemRef);

        $this->assertArrayHasKey('UserSummary', $schemas);
        $this->assertArrayHasKey('UserProfile', $schemas);
        $this->assertArrayHasKey('UserProfileInfo', $schemas);
        $this->assertArrayNotHasKey('UserAvatarUpload', $schemas);
        $this->assertArrayHasKey('UserAvatarUploadRequestData', $schemas);
    }

    public function test_docs_json_documents_phase_four_dashboard_read_contracts(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');

        $chartParams = $paths['/v1/dashboard/chart-data']['get']['parameters'] ?? [];
        $chartDataShape = $paths['/v1/dashboard/chart-data']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'] ?? [];
        $insightDataShape = $paths['/v1/dashboard/insights']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['properties'] ?? [];
        $taskMetaShape = $paths['/v1/dashboard/tasks']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties'] ?? [];
        $activityParams = $paths['/v1/dashboard/activities']['get']['parameters'] ?? [];

        $this->assertSame(['year', 'month'], array_column($chartParams, 'name'));
        $this->assertSame('integer', $chartParams[0]['schema']['type'] ?? null);
        $this->assertSame('integer', $chartParams[1]['schema']['type'] ?? null);
        $this->assertSame('integer', $chartDataShape['active_projects']['type'] ?? null);
        $this->assertSame('integer', $chartDataShape['total_projects']['type'] ?? null);

        $this->assertArrayHasKey('kpis', $insightDataShape);
        $this->assertArrayHasKey('insights', $insightDataShape);
        $this->assertSame('object', $insightDataShape['kpis']['type'] ?? null);
        $this->assertSame('array', $insightDataShape['insights']['type'] ?? null);

        $this->assertSame('array', $taskMetaShape['applied_filters']['type'] ?? null);
        $this->assertSame('integer', $taskMetaShape['total']['type'] ?? null);

        $this->assertSame(['start_date', 'end_date'], array_column($activityParams, 'name'));
        $this->assertSame('date', $activityParams[0]['schema']['format'] ?? null);
        $this->assertSame('date', $activityParams[1]['schema']['format'] ?? null);
    }

    public function test_docs_json_documents_phase_four_notification_list_contracts(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $schemas = $response->json('components.schemas');

        $notificationParams = $paths['/v1/notifications']['get']['parameters'] ?? [];
        $notificationIndexSchema = $paths['/v1/notifications']['get']['responses']['200']['content']['application/json']['schema']['properties'] ?? [];
        $notificationMetaShape = $notificationIndexSchema['meta']['properties'] ?? [];
        $notificationLinksShape = $notificationIndexSchema['links']['properties'] ?? [];
        $notificationItemsRef = $notificationIndexSchema['data']['items']['$ref'] ?? null;

        $this->assertSame(['filter[status]', 'page', 'per_page'], array_column($notificationParams, 'name'));
        $this->assertSame('string', $notificationParams[0]['schema']['type'] ?? null);
        $this->assertSame('integer', $notificationParams[1]['schema']['type'] ?? null);
        $this->assertSame('integer', $notificationParams[2]['schema']['type'] ?? null);

        $this->assertSame('#/components/schemas/NotificationResource', $notificationItemsRef);
        $this->assertSame('integer', $notificationMetaShape['current_page']['type'] ?? null);
        $this->assertSame('integer', $notificationMetaShape['total']['type'] ?? null);
        $this->assertSame('string', $notificationLinksShape['first']['type'][0] ?? $notificationLinksShape['first']['type'] ?? null);

        $this->assertSame(
            '#/components/schemas/NotificationStatusUpdateRequestData',
            $paths['/v1/notifications/{notification}/status']['patch']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );

        $this->assertArrayHasKey('NotificationResource', $schemas);
        $this->assertArrayHasKey('NotificationStatusUpdateRequestData', $schemas);
    }

    public function test_docs_json_documents_phase_five_collaboration_contracts(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');
        $schemas = $response->json('components.schemas');

        $projectShowParameters = $paths['/v1/projects/{project}']['get']['parameters'] ?? [];
        $taskIndexParameters = $paths['/v1/projects/{project}/tasks']['get']['parameters'] ?? [];
        $taskIndexSchema = $paths['/v1/projects/{project}/tasks']['get']['responses']['200']['content']['application/json']['schema']['properties'] ?? [];
        $conversationStoreRequestBody = $paths['/v1/projects/{project}/conversations']['post']['requestBody']['content'] ?? [];
        $conversationIndexDataOptions = $paths['/v1/projects/{project}/conversations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $conversationStoreDataOptions = $paths['/v1/projects/{project}/conversations']['post']['responses']['201']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $invitationIndexParameters = $paths['/v1/projects/{project}/invitations']['get']['parameters'] ?? [];
        $invitationStoreDataOptions = $paths['/v1/projects/{project}/invitations']['post']['responses']['201']['content']['application/json']['schema']['properties']['data']['anyOf'] ?? [];
        $taskSearchParameters = $paths['/v1/projects/{project}/tasks/{task}/member/search']['get']['parameters'] ?? [];
        $publicProjectSchema = $schemas['PublicProject']['properties'] ?? [];
        $projectLimitSchema = $schemas['ProjectUsageLimit']['properties'] ?? [];

        $this->assertSame(
            '#/components/schemas/ProjectStoreRequestData',
            $paths['/v1/projects']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ProjectUpdateRequestData',
            $paths['/v1/projects/{project}']['put']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ProjectListItem',
            $paths['/v1/projects']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/PublicProject',
            $paths['/v1/projects/{project}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null,
        );
        $this->assertNotNull($this->parameterByName($projectShowParameters, 'project'));

        $this->assertSame(
            '#/components/schemas/TaskStoreRequestData',
            $paths['/v1/projects/{project}/tasks']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/TaskUpdateRequestData',
            $paths['/v1/projects/{project}/tasks/{task}']['put']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/ProjectTaskListItem',
            $paths['/v1/projects/{project}/tasks']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertContains('filter[state]', array_column($taskIndexParameters, 'name'));
        $this->assertContains('page', array_column($taskIndexParameters, 'name'));
        $this->assertContains('per_page', array_column($taskIndexParameters, 'name'));
        $this->assertContains('request', array_column($taskIndexParameters, 'name'));
        $this->assertSame('Legacy alias for `filter[state]=archived`.', $this->parameterByName($taskIndexParameters, 'request')['description'] ?? null);
        $this->assertSame('object', $taskIndexSchema['links']['type'] ?? null);
        $this->assertSame('object', $taskIndexSchema['meta']['type'] ?? null);
        $this->assertStringContainsString('same top-level `data`, `links`, and `meta` envelope', $paths['/v1/projects/{project}/tasks']['get']['description'] ?? '');

        $this->assertArrayHasKey('multipart/form-data', $conversationStoreRequestBody);
        $this->assertSame(
            '#/components/schemas/ConversationStoreRequestData',
            $conversationStoreRequestBody['multipart/form-data']['schema']['$ref'] ?? null,
        );
        $this->assertContains(
            '#/components/schemas/ProjectConversation',
            $this->itemsRefs($conversationIndexDataOptions),
        );
        $this->assertContains(
            '#/components/schemas/ProjectConversation',
            array_column($conversationStoreDataOptions, '$ref'),
        );

        $this->assertSame(
            '#/components/schemas/ProjectInvitationStoreRequestData',
            $paths['/v1/projects/{project}/invitations']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertContains(
            '#/components/schemas/ProjectInvitation',
            array_column($invitationStoreDataOptions, '$ref'),
        );
        $this->assertContains('filter[status]', array_column($invitationIndexParameters, 'name'));
        $this->assertContains('status', array_column($invitationIndexParameters, 'name'));
        $this->assertSame(
            '#/components/schemas/PendingInvitationUser',
            $paths['/v1/projects/{project}/invitations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );

        $this->assertSame(
            '#/components/schemas/TaskMemberAssignRequestData',
            $paths['/v1/projects/{project}/tasks/{task}/assign']['patch']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/TaskMemberUnassignRequestData',
            $paths['/v1/projects/{project}/tasks/{task}/unassign']['patch']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertContains('search', array_column($taskSearchParameters, 'name'));
        $this->assertSame(
            '#/components/schemas/TaskMember',
            $paths['/v1/projects/{project}/tasks/{task}/member/search']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );

        $this->assertArrayHasKey('ProjectListItem', $schemas);
        $this->assertArrayHasKey('ProjectConversation', $schemas);
        $this->assertArrayHasKey('ProjectInvitation', $schemas);
        $this->assertArrayHasKey('ProjectStoreRequestData', $schemas);
        $this->assertArrayHasKey('TaskStoreRequestData', $schemas);
        $this->assertArrayHasKey('TaskMember', $schemas);
        $this->assertArrayHasKey('PendingInvitationUser', $schemas);
        $this->assertSame('array', $publicProjectSchema['limits']['type'] ?? null);
        $this->assertSame('#/components/schemas/ProjectUsageLimit', $publicProjectSchema['limits']['items']['$ref'] ?? null);
        $this->assertArrayHasKey('ProjectUsageLimit', $schemas);
        $this->assertSame('string', $projectLimitSchema['key']['type'] ?? null);
        $this->assertSame('string', $projectLimitSchema['label']['type'] ?? null);
        $this->assertSame('string', $projectLimitSchema['scope']['type'] ?? null);
        $this->assertSame('integer', $projectLimitSchema['limit']['properties']['used']['type'][0] ?? $projectLimitSchema['limit']['properties']['used']['type'] ?? null);
        $this->assertSame('integer', $projectLimitSchema['limit']['properties']['max']['type'][0] ?? $projectLimitSchema['limit']['properties']['max']['type'] ?? null);
    }

    public function test_docs_json_passes_phase_six_public_docs_verification(): void
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $paths = $response->json('paths');

        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertNotSame('', trim((string) ($operation['summary'] ?? '')), "Missing summary for {$method} {$path}");
                $this->assertNotSame('', trim((string) ($operation['description'] ?? '')), "Missing description for {$method} {$path}");
                $this->assertNotSame([], $operation['tags'] ?? [], "Missing tags for {$method} {$path}");
            }
        }

        $this->assertArrayNotHasKey('/v1/admin/users', $paths);
        $this->assertArrayNotHasKey('/v1/password/reset/{token}', $paths);
        $this->assertArrayNotHasKey('/v1/projects/{project}/export', $paths);
        $this->assertArrayNotHasKey('/v1/projects/{project}/messages/scheduled', $paths);
        $this->assertArrayNotHasKey('/v1/projects/{project}/meetings', $paths);
        $this->assertArrayNotHasKey('/v1/webhooks/zoom/meetings/update', $paths);

        $this->assertSame([], $paths['/v1/register']['post']['security'] ?? null);
        $this->assertSame([], $paths['/v1/login']['post']['security'] ?? null);
        $this->assertSame([], $paths['/v1/forgot-password']['post']['security'] ?? null);
        $this->assertSame([], $paths['/v1/reset-password']['post']['security'] ?? null);
        $this->assertSame([], $paths['/v1/session/login']['post']['security'] ?? null);
        $this->assertSame([], $paths['/v1/auth/redirect/{provider}']['get']['security'] ?? null);
        $this->assertSame([], $paths['/v1/auth/callback/{provider}']['get']['security'] ?? null);
        $this->assertSame([], $paths['/v1/twofactor/login-confirm']['post']['security'] ?? null);

        $this->assertArrayHasKey('multipart/form-data', $paths['/v1/users/{user}/avatar']['post']['requestBody']['content'] ?? []);
        $this->assertArrayHasKey('multipart/form-data', $paths['/v1/projects/{project}/conversations']['post']['requestBody']['content'] ?? []);

        $this->assertSame(
            '#/components/responses/PublicValidationError',
            $paths['/v1/login']['post']['responses']['422']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/responses/PublicUnauthenticatedError',
            $paths['/v1/api-tokens']['get']['responses']['401']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/responses/PublicForbiddenError',
            $paths['/v1/projects/{project}/force']['delete']['responses']['403']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/responses/PublicValidationError',
            $paths['/v1/projects/{project}/tasks/{task}/assign']['patch']['responses']['422']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/responses/PublicInternalServerError',
            $paths['/v1/projects/{project}/tasks']['get']['responses']['500']['$ref'] ?? null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @return array<string, mixed>|null
     */
    private function parameterByName(array $parameters, string $name): ?array
    {
        foreach ($parameters as $parameter) {
            if (($parameter['name'] ?? null) === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, string>
     */
    private function itemsRefs(array $options): array
    {
        $refs = [];

        foreach ($options as $option) {
            if (isset($option['items']['$ref']) && is_string($option['items']['$ref'])) {
                $refs[] = $option['items']['$ref'];
            }
        }

        return $refs;
    }
}
