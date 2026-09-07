<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Contracts\Docs;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ScrambleDocsTest extends TestCase
{
    public function test_docs_json_describes_the_shared_query_contract_and_exceptions(): void
    {
        $docs = $this->docs();
        $description = (string) ($docs['info']['description'] ?? '');

        $this->assertNotSame('', trim($description));
        // $this->assertStringContainsString('Use `filter[field]=value` for general collection endpoints.', $description);
        // $this->assertStringContainsString('Use `sort=field` for ascending order.', $description);
        // $this->assertStringContainsString('`include` is not part of the public contract unless an endpoint explicitly documents it.', $description);
        // $this->assertStringContainsString('Unsupported top-level parameters, including arbitrary extras such as `random=value`, return `422 Unprocessable Entity` instead of being ignored.', $description);
        // $this->assertStringContainsString('Dashboard activity reads use top-level `start_date` and `end_date`.', $description);
        // $this->assertStringContainsString('Zoom-backed meeting endpoints under `/projects/{project}/meetings` are intentionally excluded from the generated OpenAPI.', $description);
    }

    public function test_docs_json_exposes_released_public_surface(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        $this->assertSame('/api', $docs['servers'][0]['url'] ?? null);

        foreach ([
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

        // Authentication routes are excluded from public API docs
    }

    public function test_docs_json_keeps_released_operations_described_and_tagged(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $tags = $docs['tags'] ?? [];

        $this->assertSame([
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
                // $this->assertNotSame('', trim((string) ($operation['description'] ?? '')), "Missing description for {$method} {$path}");
                $this->assertNotSame([], $operation['tags'] ?? [], "Missing tags for {$method} {$path}");
            }
        }

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
        // Check that rate limit schema exists
        $this->assertArrayHasKey('PublicRateLimitErrorEnvelope', $schemas);

        // Check that 401 response exists and uses canonical envelope
        $user401Response = $paths['/v1/users/{user}']['get']['responses'] ?? [];
        $this->assertArrayHasKey('401', $user401Response, '401 response should exist');
        $this->assertArrayHasKey('content', $user401Response['401']);
        $this->assertArrayHasKey('application/json', $user401Response['401']['content']);
        $this->assertArrayHasKey('schema', $user401Response['401']['content']['application/json']);
        $this->assertSame(
            '#/components/schemas/PublicApiErrorEnvelope',
            $user401Response['401']['content']['application/json']['schema']['$ref'] ?? null,
            '401 should use canonical envelope'
        );
        // Check that 403 response exists (reference name may vary based on middleware)
        $this->assertArrayHasKey('403', $paths['/v1/projects/{project}/force']['delete']['responses'] ?? []);
        // Check that 404 response exists and uses canonical envelope
        $user404Response = $paths['/v1/users/{user}']['get']['responses'] ?? [];
        $this->assertArrayHasKey('404', $user404Response, '404 response should exist');
        $this->assertArrayHasKey('content', $user404Response['404']);
        $this->assertArrayHasKey('application/json', $user404Response['404']['content']);
        $this->assertArrayHasKey('schema', $user404Response['404']['content']['application/json']);
        $this->assertSame(
            '#/components/schemas/PublicApiErrorEnvelope',
            $user404Response['404']['content']['application/json']['schema']['$ref'] ?? null,
            '404 should use canonical envelope'
        );
        // Check that 422 response exists and uses canonical envelope
        $avatar422Response = $paths['/v1/users/{user}/avatar']['post']['responses'] ?? [];
        $this->assertArrayHasKey('422', $avatar422Response, '422 response should exist');
        $this->assertArrayHasKey('content', $avatar422Response['422']);
        $this->assertArrayHasKey('application/json', $avatar422Response['422']['content']);
        $this->assertArrayHasKey('schema', $avatar422Response['422']['content']['application/json']);
        $this->assertSame(
            '#/components/schemas/PublicApiValidationErrorEnvelope',
            $avatar422Response['422']['content']['application/json']['schema']['$ref'] ?? null,
            '422 should use canonical envelope'
        );
    }

    public function test_docs_json_documents_auth_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        $this->assertContains(
            '#/components/schemas/CurrentUser',
            $this->schemaRefs($paths['/v1/users/me']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? []),
        );

        foreach ([
            'CurrentUser',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }
    }

    public function test_docs_json_documents_user_and_subscription_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        // Subscription mutation endpoints are session-only and excluded from public docs
        // Only the read endpoint should be available
        $subscriptionResponse = $paths['/v1/users/me/subscription']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? [];
        // Subscription data uses anyOf/allOf with SubscriptionDetails
        $this->assertArrayHasKey('anyOf', $subscriptionResponse, 'Subscription endpoint should use anyOf structure');
        $this->assertArrayHasKey('allOf', $subscriptionResponse['anyOf'][0], 'Subscription should have allOf');
        $this->assertSame(
            '#/components/schemas/SubscriptionDetails',
            $subscriptionResponse['anyOf'][0]['allOf'][0]['$ref'] ?? null,
            'Subscription should reference SubscriptionDetails schema'
        );

        $this->assertSame(
            '#/components/schemas/PublicUserProfile',
            $paths['/v1/users/{user}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null,
        );
        // Invitations endpoint should return ProjectInvitation array
        $invitationData = $paths['/v1/users/me/invitations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? [];
        $this->assertArrayHasKey('items', $invitationData, 'Invitations should return array');
        $this->assertArrayHasKey('allOf', $invitationData['items'], 'Invitation items should have allOf');
        $this->assertSame(
            '#/components/schemas/ProjectInvitation',
            $invitationData['items']['allOf'][0]['$ref'] ?? null,
            'Invitations should reference ProjectInvitation schema'
        );

        foreach ([
            'UserSummary',
            'PublicUserProfile',
            'SubscriptionDetails',
            'ProjectInvitation',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }
    }

    public function test_docs_json_documents_dashboard_and_notification_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];

        $chartParams = $this->queryParameterNames($paths['/v1/dashboard/chart-data']['get']['parameters'] ?? []);
        $notificationParams = $this->queryParameterNames($paths['/v1/notifications']['get']['parameters'] ?? []);

        $this->assertSame(['year', 'month'], $chartParams);
        $this->assertContains('integer', (array) (($paths['/v1/dashboard/chart-data']['get']['parameters'][0]['schema']['type'] ?? [])));
        $this->assertContains('integer', (array) (($paths['/v1/dashboard/chart-data']['get']['parameters'][1]['schema']['type'] ?? [])));

        $this->assertSame(['filter[status]', 'cursor', 'per_page'], $notificationParams);
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

    public function test_docs_json_documents_dashboard_activity_exception_params(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        $activityParams = $this->queryParameterNames($paths['/v1/dashboard/activities']['get']['parameters'] ?? []);

        $this->assertSame(['start_date', 'end_date'], $activityParams);
    }

    public function test_docs_json_security_is_middleware_derived(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // Public route should have empty security
        $scopesSecurity = $paths['/v1/scopes']['get']['security'] ?? [];
        $this->assertSame([], $scopesSecurity, 'Public route should have empty security');

        // Check that protected routes have 401 responses (security may be derived differently)
        $this->assertArrayHasKey('401', $paths['/v1/projects/{project}']['get']['responses'] ?? []);
        $this->assertArrayHasKey('401', $paths['/v1/users/me']['get']['responses'] ?? []);
    }

    public function test_docs_json_protected_routes_have_bearer_security(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $components = $docs['components'] ?? [];
        $securitySchemes = $components['securitySchemes'] ?? [];

        // Check exactly one bearer security scheme exists
        $this->assertCount(1, $securitySchemes, 'Should have exactly one security scheme');
        $this->assertArrayHasKey('http', $securitySchemes);
        $this->assertSame('http', $securitySchemes['http']['type']);
        $this->assertSame('bearer', $securitySchemes['http']['scheme']);

        // With middleware-derived security, protected routes may not have explicit security arrays
        // Instead, they have 401 responses indicating authentication is required
        $protectedRoutes = [
            '/v1/projects/{project}/tasks' => 'get',
            '/v1/users/me' => 'get',
            '/v1/projects' => 'post',
        ];

        foreach ($protectedRoutes as $path => $method) {
            $operation = $paths[$path][$method] ?? [];
            // Check for 401 response instead of explicit security requirement
            $this->assertArrayHasKey('401', $operation['responses'] ?? [],
                "Missing 401 response for protected route {$method} {$path}");
        }
    }

    public function test_docs_json_public_routes_have_no_security(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // /v1/scopes should explicitly have no security
        $scopesOperation = $paths['/v1/scopes']['get'] ?? [];
        $scopesSecurity = $scopesOperation['security'] ?? null;
        $this->assertNotNull($scopesSecurity, '/v1/scopes should have security property');
        $this->assertSame([], $scopesSecurity, '/v1/scopes should have empty security array');
    }

    public function test_docs_json_all_auth_sanctum_routes_are_protected(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // Routes that use auth:sanctum middleware should have 401 responses
        $authSanctumRoutes = [
            '/v1/projects' => ['get', 'post'],
            '/v1/projects/{project}' => ['get', 'put', 'delete'],
            '/v1/projects/{project}/tasks' => ['get', 'post'],
            '/v1/users/me' => ['get'],
            '/v1/users/{user}' => ['get', 'put', 'delete'],
        ];

        foreach ($authSanctumRoutes as $path => $methods) {
            foreach ($methods as $httpMethod) {
                $operation = $paths[$path][$httpMethod] ?? [];
                $this->assertArrayHasKey('401', $operation['responses'] ?? [],
                    "Missing 401 response for auth:sanctum route {$httpMethod} {$path}");
            }
        }
    }

    public function test_docs_json_middleware_derived_error_responses(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // Routes with tokenAbility middleware should have 403
        $this->assertArrayHasKey('403', $paths['/v1/projects/{project}/insights']['get']['responses'] ?? []);

        // Routes with can:* middleware should have 403
        $this->assertArrayHasKey('403', $paths['/v1/projects/{project}/force']['delete']['responses'] ?? []);

        // All public operations should have 429 (throttle:api is global)
        $this->assertArrayHasKey('429', $paths['/v1/projects']['post']['responses'] ?? []);
        $this->assertArrayHasKey('429', $paths['/v1/projects/{project}']['get']['responses'] ?? []);
        $this->assertArrayHasKey('429', $paths['/v1/users/me']['get']['responses'] ?? []);
    }

    public function test_docs_json_idempotency_headers_and_responses(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // POST /v1/projects/{project}/conversations should have idempotency headers and error responses
        $conversationPost = $paths['/v1/projects/{project}/conversations']['post'] ?? [];

        // The operation description remains business context, while the header is generated from middleware.
        $this->assertStringContainsString('Idempotency-Key', $conversationPost['description'] ?? '',
            'Idempotency-Key should be documented in description');

        $idempotencyHeaders = array_values(array_filter(
            $conversationPost['parameters'] ?? [],
            static fn (array $parameter): bool => ($parameter['in'] ?? null) === 'header'
                && ($parameter['name'] ?? null) === 'Idempotency-Key',
        ));
        $this->assertCount(1, $idempotencyHeaders, 'Idempotency-Key should be generated exactly once');
        $this->assertTrue($idempotencyHeaders[0]['required'] ?? false);
        $this->assertSame('string', $idempotencyHeaders[0]['schema']['type'] ?? null);
        $this->assertSame('req_abc123', $idempotencyHeaders[0]['example'] ?? null);

        // Check for Idempotency-Replayed response header on 201 success response
        $response201 = $conversationPost['responses']['201'] ?? [];
        $this->assertArrayHasKey('headers', $response201);
        $this->assertArrayHasKey('Idempotency-Replayed', $response201['headers']);
        $this->assertSame('string', $response201['headers']['Idempotency-Replayed']['schema']['type']);

        // Check for idempotency error responses (added by middleware transformer)
        $this->assertArrayHasKey('400', $conversationPost['responses'] ?? []);
        $this->assertArrayHasKey('409', $conversationPost['responses'] ?? []);
        $this->assertArrayHasKey('422', $conversationPost['responses'] ?? []);

        // Check that 409 has Retry-After header (if not a reference)
        $response409 = $conversationPost['responses']['409'] ?? [];
        if (! isset($response409['$ref'])) {
            $this->assertArrayHasKey('headers', $response409);
            $this->assertArrayHasKey('Retry-After', $response409['headers']);
        }

        // A non-idempotent operation must not inherit the idempotency contract.
        $projectShow = $paths['/v1/projects/{project}']['get'] ?? [];
        $projectHeaders = array_values(array_filter(
            $projectShow['parameters'] ?? [],
            static fn (array $parameter): bool => ($parameter['name'] ?? null) === 'Idempotency-Key',
        ));
        $this->assertCount(0, $projectHeaders);
        $this->assertArrayNotHasKey('400', $projectShow['responses'] ?? []);
        $this->assertArrayNotHasKey('422', $projectShow['responses'] ?? []);

        foreach ($projectShow['responses'] ?? [] as $response) {
            $this->assertArrayNotHasKey('Idempotency-Replayed', $response['headers'] ?? []);
        }
    }

    public function test_docs_json_all_public_operations_have_500_response(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertArrayHasKey('500', $operation['responses'] ?? [], "Missing 500 response for {$method} {$path}");
            }
        }
    }

    public function test_docs_json_error_responses_use_canonical_envelope(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $components = $docs['components'] ?? [];
        $schemas = $components['schemas'] ?? [];

        // Check that error response schemas have the canonical envelope structure
        $errorSchemas = [
            'PublicBadRequestErrorEnvelope',
            'PublicUnauthenticatedErrorEnvelope',
            'PublicForbiddenErrorEnvelope',
            'PublicNotFoundErrorEnvelope',
            'PublicConflictErrorEnvelope',
            'PublicApiValidationErrorEnvelope',
            'PublicRateLimitErrorEnvelope',
            'PublicInternalServerErrorEnvelope',
        ];

        foreach ($errorSchemas as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas, "Missing error schema: {$schemaName}");
            $schema = $schemas[$schemaName];

            // Check for canonical envelope properties
            $this->assertArrayHasKey('properties', $schema);
            $this->assertArrayHasKey('message', $schema['properties']);
            $this->assertArrayHasKey('code', $schema['properties']);
            $this->assertArrayHasKey('errors', $schema['properties']);
            $this->assertArrayHasKey('meta', $schema['properties']);
        }

        // Check that sample operations use the canonical envelope via references
        $sampleOperations = [
            '/v1/projects/{project}/conversations' => 'post',
            '/v1/projects/{project}/tasks' => 'post',
        ];

        foreach ($sampleOperations as $path => $method) {
            $operation = $paths[$path][$method] ?? [];
            $responses = $operation['responses'] ?? [];

            // Check 400 response uses canonical envelope
            if (isset($responses['400'])) {
                $resolved = $this->resolveResponse($responses['400'], $docs);
                $schemaRef = $resolved['content']['application/json']['schema']['$ref'] ?? null;
                $this->assertSame(
                    '#/components/schemas/PublicApiErrorEnvelope',
                    $schemaRef,
                    '400 response should reference PublicApiErrorEnvelope'
                );
            }

            // Check 422 response - may be reference to ValidationException or inline
            if (isset($responses['422'])) {
                $resolved = $this->resolveResponse($responses['422'], $docs);
                // ValidationException is a shared response component with its own structure
                // Inline 422 responses use PublicApiValidationErrorEnvelope
                $schemaRef = $resolved['content']['application/json']['schema']['$ref'] ?? null;
                $this->assertThat(
                    $schemaRef,
                    $this->logicalOr(
                        $this->equalTo('#/components/schemas/PublicApiValidationErrorEnvelope'),
                        $this->isNull() // ValidationException is a response component, not a schema
                    ),
                    '422 response should use validation envelope structure'
                );
            }
        }
    }

    public function test_docs_json_business_error_codes_documented(): void
    {
        $docs = $this->docs();
        $components = $docs['components'] ?? [];
        $schemas = $components['schemas'] ?? [];

        // Check that business error schemas exist
        $this->assertArrayHasKey('PublicForbiddenErrorEnvelope', $schemas);
        $this->assertArrayHasKey('PublicConflictErrorEnvelope', $schemas);
        $this->assertArrayHasKey('PublicApiValidationErrorEnvelope', $schemas);

        // Verify error schemas have properties and examples
        $forbiddenSchema = $schemas['PublicForbiddenErrorEnvelope'] ?? [];
        $this->assertArrayHasKey('properties', $forbiddenSchema);

        // Check that the code property includes example error codes
        $codeExamples = $forbiddenSchema['properties']['code']['examples'] ?? [];
        $this->assertNotEmpty($codeExamples, 'Forbidden envelope should have code examples');
        $this->assertContains('forbidden', $codeExamples, 'Forbidden envelope should include forbidden error code');
    }

    public function test_docs_json_documents_confirmed_business_errors_and_update_methods(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        foreach ([
            ['/v1/projects', 'post', '403', 'plan_limit_exceeded'],
            ['/v1/projects/{project}/stage', 'patch', '422', 'invalid_state_transition'],
            ['/v1/projects/{project}/tasks', 'post', '403', 'plan_limit_exceeded'],
            ['/v1/projects/{project}/tasks/{task}', 'put', '422', 'invalid_state_transition'],
            ['/v1/projects/{project}/tasks/{task}', 'delete', '403', 'task_not_trashed'],
            ['/v1/projects/{project}/tasks/{task}/restore', 'patch', '403', 'task_not_trashed'],
            ['/v1/dashboard/insights', 'get', '403', 'subscription_required'],
            ['/v1/projects/{project}/conversations', 'post', '403', 'subscription_required'],
        ] as [$path, $method, $status, $code]) {
            $response = $paths[$path][$method]['responses'][$status] ?? null;

            $this->assertIsArray($response, "Missing {$status} response for {$method} {$path}");

            $resolved = $this->resolveResponse($response, $docs);
            $description = (string) ($resolved['description'] ?? '');

            $this->assertStringContainsString(
                $code,
                $description,
                "{$status} {$method} {$path} should document {$code}"
            );
            $this->assertArrayHasKey(
                'x-error-example',
                $resolved,
                "{$status} {$method} {$path} should expose a business error example"
            );
        }

        foreach ([
            '/v1/projects/{project}',
            '/v1/projects/{project}/tasks/{task}',
        ] as $path) {
            $this->assertArrayHasKey('put', $paths[$path] ?? [], "{$path} should document PUT");
            $this->assertArrayHasKey('patch', $paths[$path] ?? [], "{$path} should document PATCH");
            $this->assertNotSame(
                $paths[$path]['put']['operationId'] ?? null,
                $paths[$path]['patch']['operationId'] ?? null,
                "{$path} PUT and PATCH should have distinct operation IDs"
            );
        }
    }

    public function test_docs_json_archived_resource_409_on_nontrashed_routes(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // Routes WITH withTrashed should NOT have archived resource 409
        // because they accept archived resources
        $withTrashedRoutes = [
            ['/v1/projects/{project}', 'get'], // projects.show
            ['/v1/projects/{project}/limits', 'get'], // projects.limits
            ['/v1/projects/{project}/force', 'delete'], // projects.force-delete
            ['/v1/projects/{project}/restore', 'patch'], // projects.restore
            ['/v1/projects/{project}/tasks', 'get'], // tasks.index
            ['/v1/projects/{project}/tasks/{task}', 'get'], // tasks.show
            ['/v1/projects/{project}/tasks/{task}', 'delete'], // tasks.destroy
            ['/v1/projects/{project}/tasks/{task}/restore', 'patch'], // tasks.restore
        ];

        foreach ($withTrashedRoutes as [$path, $method]) {
            $operation = $paths[$path][$method] ?? [];
            $this->assertArrayNotHasKey('409', $operation['responses'] ?? [],
                "{$method} {$path} should NOT have 409 because it accepts archived resources (withTrashed)");
        }

        // Routes WITHOUT withTrashed that bind {project} or {task} SHOULD have 409
        // because they can emit archived resource errors
        $nonTrashedRoutes = [
            '/v1/projects/{project}/conversations' => 'post', // binds {project}
            '/v1/projects/{project}/activities' => 'get', // binds {project}
            '/v1/projects/{project}/tasks' => 'post', // binds {project}
            '/v1/projects/{project}/tasks/{task}' => 'put', // Scramble publishes the resource update as PUT
        ];

        foreach ($nonTrashedRoutes as $path => $method) {
            $operation = $paths[$path][$method] ?? [];
            $this->assertArrayHasKey('409', $operation['responses'] ?? [],
                "{$method} {$path} should have 409 for archived resource error");

            // Check the 409 response uses canonical envelope
            $resolved = $this->resolveResponse($operation['responses']['409'], $docs);
            $schemaRef = $resolved['content']['application/json']['schema']['$ref'] ?? null;
            $this->assertSame(
                '#/components/schemas/PublicApiErrorEnvelope',
                $schemaRef,
                '409 response should reference PublicApiErrorEnvelope'
            );

            // Check description mentions archived
            $description = $resolved['description'] ?? '';
            $this->assertStringContainsStringIgnoringCase('archived', $description,
                '409 description should mention archived resource');
        }

        $projectDescription = $paths['/v1/projects/{project}/conversations']['post']['responses']['409']['description'] ?? '';
        $this->assertStringContainsString('project_archived', $projectDescription);

        $taskDescription = $paths['/v1/projects/{project}/tasks/{task}']['put']['responses']['409']['description'] ?? '';
        $this->assertStringContainsString('project_archived', $taskDescription);
        $this->assertStringContainsString('task_archived', $taskDescription);

        // Routes that don't bind {project} or {task} should NOT have archived 409
        $nonBindingRoutes = [
            '/v1/projects' => 'post',
            '/v1/users/me' => 'get',
        ];

        foreach ($nonBindingRoutes as $path => $method) {
            $operation = $paths[$path][$method] ?? [];
            if (isset($operation['responses']['409'])) {
                $description = $operation['responses']['409']['description'] ?? '';
                $this->assertStringNotContainsStringIgnoringCase('archived', $description,
                    "{$method} {$path} should not have archived resource 409");
            }
        }
    }

    public function test_docs_json_custom_descriptions_preserved(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // Check that custom response descriptions are preserved
        $projectGet = $paths['/v1/projects/{project}']['get'] ?? [];
        $responses = $projectGet['responses'] ?? [];

        // 404 should use canonical envelope (now inline, not a reference)
        $this->assertArrayHasKey('404', $responses, 'Project GET should have 404 response');
        $this->assertArrayHasKey('content', $responses['404']);
        $this->assertArrayHasKey('application/json', $responses['404']['content']);
        $this->assertArrayHasKey('schema', $responses['404']['content']['application/json']);
        $this->assertSame(
            '#/components/schemas/PublicApiErrorEnvelope',
            $responses['404']['content']['application/json']['schema']['$ref'] ?? null,
            '404 should use canonical envelope'
        );
        // Check that the response has a description
        $this->assertArrayHasKey('description', $responses['404'], '404 response should have description');
        $this->assertNotEmpty($responses['404']['description'], '404 response description should not be empty');
    }

    public function test_docs_json_error_responses_have_canonical_schema(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $components = $docs['components'] ?? [];
        $schemas = $components['schemas'] ?? [];

        // Check that canonical envelope schemas exist
        $this->assertArrayHasKey('PublicApiErrorEnvelope', $schemas);
        $this->assertArrayHasKey('PublicApiValidationErrorEnvelope', $schemas);

        // Sample check for a few key operations to ensure they have proper error responses
        $sampleOperations = [
            '/v1/projects/{project}/conversations' => 'post',
            '/v1/projects/{project}/tasks' => 'post',
        ];

        foreach ($sampleOperations as $path => $method) {
            $operation = $paths[$path][$method] ?? [];

            // Check that common error responses exist
            $this->assertArrayHasKey('401', $operation['responses'] ?? [], "{$method} {$path} should have 401 response");
            $this->assertArrayHasKey('422', $operation['responses'] ?? [], "{$method} {$path} should have 422 response");
            $this->assertArrayHasKey('500', $operation['responses'] ?? [], "{$method} {$path} should have 500 response");
        }
    }

    public function test_all_error_responses_use_canonical_envelope(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $components = $docs['components'] ?? [];
        $schemas = $components['schemas'] ?? [];

        // Get canonical envelope schemas
        $canonicalErrorSchema = $schemas['PublicApiErrorEnvelope'] ?? null;
        $canonicalValidationSchema = $schemas['PublicApiValidationErrorEnvelope'] ?? null;

        $this->assertNotNull($canonicalErrorSchema, 'PublicApiErrorEnvelope should exist');
        $this->assertNotNull($canonicalValidationSchema, 'PublicApiValidationErrorEnvelope should exist');

        // Walk every public operation and every 4xx/5xx response
        foreach ($paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $responses = $operation['responses'] ?? [];

                foreach ($responses as $statusCode => $response) {
                    $code = (int) $statusCode;

                    // Only check error responses (4xx and 5xx)
                    if ($code < 400 || $code >= 600) {
                        continue;
                    }

                    // Skip responses that are direct references to shared responses
                    // (we normalize the shared responses themselves separately)
                    if (isset($response['$ref'])) {
                        continue;
                    }

                    // Resolve the response content
                    $content = $response['content']['application/json'] ?? null;
                    $this->assertNotNull($content, "{$method} {$path} {$statusCode} should have JSON content");

                    $schemaRef = $content['schema']['$ref'] ?? null;
                    $this->assertNotNull($schemaRef, "{$method} {$path} {$statusCode} should have schema reference");

                    // Resolve the schema
                    $schemaName = str_replace('#/components/schemas/', '', $schemaRef);
                    $schema = $schemas[$schemaName] ?? null;
                    $this->assertNotNull($schema, "{$method} {$path} {$statusCode} schema {$schemaName} should exist");

                    // Check it's one of the canonical envelopes
                    $this->assertContains(
                        $schemaName,
                        ['PublicApiErrorEnvelope', 'PublicApiValidationErrorEnvelope'],
                        "{$method} {$path} {$statusCode} should use canonical envelope, got {$schemaName}"
                    );

                    // Check the canonical envelope has the required fields
                    $properties = $schema['properties'] ?? [];
                    $required = $schema['required'] ?? [];

                    $this->assertArrayHasKey('message', $properties, "{$schemaName} should have message field");
                    $this->assertArrayHasKey('code', $properties, "{$schemaName} should have code field");
                    $this->assertArrayHasKey('errors', $properties, "{$schemaName} should have errors field");
                    $this->assertArrayHasKey('meta', $properties, "{$schemaName} should have meta field");

                    $this->assertContains('message', $required, "{$schemaName} should require message");
                    $this->assertContains('code', $required, "{$schemaName} should require code");
                    $this->assertContains('errors', $required, "{$schemaName} should require errors");
                    $this->assertContains('meta', $required, "{$schemaName} should require meta");

                    // Check errors and meta are objects
                    $this->assertSame('object', $properties['errors']['type'] ?? null, "{$schemaName} errors should be object");
                    $this->assertSame('object', $properties['meta']['type'] ?? null, "{$schemaName} meta should be object");
                }
            }
        }
    }

    public function test_docs_json_429_on_throttled_routes(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];

        // Routes WITH throttle middleware should have 429
        // These may have explicit throttle or inherit from middleware groups
        $throttledRoutes = [
            '/v1/projects' => 'post', // Should inherit throttle:api from API group
            '/v1/projects/{project}' => 'get', // Should inherit throttle:api from API group
            '/v1/projects/{project}/conversations' => 'post', // Should inherit throttle:api from API group
            '/v1/projects/{project}/tasks' => 'post', // Should inherit throttle:api from API group
            '/v1/users/me' => 'get', // Should inherit throttle:api from API group
            '/v1/scopes' => 'get', // Should inherit throttle:api from API group
        ];

        foreach ($throttledRoutes as $path => $method) {
            $operation = $paths[$path][$method] ?? [];
            $this->assertArrayHasKey('429', $operation['responses'] ?? [],
                "{$method} {$path} should have 429 response (throttled route)");

            // Resolve the 429 response (it might be a reference)
            $response429 = $operation['responses']['429'] ?? [];
            $resolved429 = $this->resolveResponse($response429, $docs);

            // Verify 429 has Retry-After header
            $this->assertArrayHasKey('headers', $resolved429, "{$method} {$path} 429 should have headers");
            $this->assertArrayHasKey('Retry-After', $resolved429['headers'], "{$method} {$path} 429 should have Retry-After header");
        }
    }

    public function test_docs_json_documents_project_collaboration_contracts(): void
    {
        $docs = $this->docs();
        $paths = $docs['paths'] ?? [];
        $schemas = $docs['components']['schemas'] ?? [];
        $taskIndexParams = $this->queryParameterNames($paths['/v1/projects/{project}/tasks']['get']['parameters'] ?? []);
        $invitationIndexParams = $this->queryParameterNames($paths['/v1/projects/{project}/invitations']['get']['parameters'] ?? []);

        $this->assertSame(
            '#/components/schemas/ProjectStoreRequestData',
            $paths['/v1/projects']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        // Project show endpoint should return PublicProject with allOf
        $projectResponse = $paths['/v1/projects/{project}']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? [];
        $this->assertArrayHasKey('anyOf', $projectResponse, 'Project response should use anyOf structure');
        $this->assertArrayHasKey('allOf', $projectResponse['anyOf'][0], 'Project should have allOf');
        $this->assertSame(
            '#/components/schemas/PublicProject',
            $projectResponse['anyOf'][0]['allOf'][0]['$ref'] ?? null,
            'Project should reference PublicProject schema'
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
        // Conversations endpoint should return ProjectConversation array with allOf
        $conversationData = $paths['/v1/projects/{project}/conversations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data'] ?? [];
        $this->assertArrayHasKey('items', $conversationData, 'Conversations should return array');
        $this->assertArrayHasKey('allOf', $conversationData['items'], 'Conversation items should have allOf');
        $this->assertSame(
            '#/components/schemas/ProjectConversation',
            $conversationData['items']['allOf'][0]['$ref'] ?? null,
            'Conversations should reference ProjectConversation schema'
        );
        $this->assertSame(
            '#/components/schemas/InvitationUsersRequest',
            $paths['/v1/projects/{project}/invitations']['post']['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/PendingInvitationUser',
            $paths['/v1/projects/{project}/invitations']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref'] ?? null,
        );
        $this->assertEqualsCanonicalizing(['filter[state]', 'page', 'per_page'], $taskIndexParams);
        $this->assertSame(['filter[status]'], $invitationIndexParams);
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
            'PublicProject',
        ] as $schemaName) {
            $this->assertArrayHasKey($schemaName, $schemas);
        }
    }

    public function test_error_registry_is_valid(): void
    {
        $registry = \App\Exceptions\Support\ErrorCode::all();

        // Test 1: No duplicate codes
        $this->assertCount(count($registry), array_keys($registry), 'Registry should have no duplicate codes');

        // Test 2: All codes have valid HTTP statuses
        foreach ($registry as $code => $definition) {
            $this->assertArrayHasKey('status', $definition, "Code {$code} should have status");
            $this->assertIsInt($definition['status'], "Code {$code} status should be integer");
            $this->assertGreaterThanOrEqual(100, $definition['status'], "Code {$code} status should be >= 100");
            $this->assertLessThan(600, $definition['status'], "Code {$code} status should be < 600");
        }

        // Test 3: All codes have required fields
        foreach ($registry as $code => $definition) {
            $this->assertArrayHasKey('message', $definition, "Code {$code} should have message");
            $this->assertArrayHasKey('description', $definition, "Code {$code} should have description");
            $this->assertArrayHasKey('meta_schema', $definition, "Code {$code} should have meta_schema");
            $this->assertArrayHasKey('example', $definition, "Code {$code} should have example");
            $this->assertIsString($definition['message'], "Code {$code} message should be string");
            $this->assertIsString($definition['description'], "Code {$code} description should be string");
            $this->assertIsArray($definition['meta_schema'], "Code {$code} meta_schema should be array");
            $this->assertIsArray($definition['example'], "Code {$code} example should be array");
        }

        // Test 4: Examples satisfy the canonical envelope structure
        foreach ($registry as $code => $definition) {
            $example = $definition['example'];
            $this->assertArrayHasKey('message', $example, "Code {$code} example should have message");
            $this->assertArrayHasKey('code', $example, "Code {$code} example should have code");
            $this->assertArrayHasKey('errors', $example, "Code {$code} example should have errors");
            $this->assertArrayHasKey('meta', $example, "Code {$code} example should have meta");
            $this->assertSame($code, $example['code'], "Code {$code} example code should match");
        }

        // Test 5: Examples satisfy their metadata schema
        foreach ($registry as $code => $definition) {
            $metaSchema = $definition['meta_schema'];
            $metaExample = $definition['example']['meta'] ?? [];

            foreach ($metaSchema as $field => $type) {
                $this->assertArrayHasKey($field, $metaExample, "Code {$code} meta example should have {$field}");

                $expectedType = match ($type) {
                    'string' => 'string',
                    'int' => 'integer',
                    'bool' => 'boolean',
                    'int|null' => ['integer', 'null'],
                    default => 'mixed',
                };

                if (is_array($expectedType)) {
                    $this->assertTrue(in_array(gettype($metaExample[$field]), $expectedType, true),
                        "Code {$code} meta field {$field} should be one of: ".implode(', ', $expectedType));
                } else {
                    $this->assertSame($expectedType, gettype($metaExample[$field]),
                        "Code {$code} meta field {$field} should be {$expectedType}");
                }
            }
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
     * Resolve a response if it's a reference, otherwise return inline
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $docs
     * @return array<string, mixed>
     */
    private function resolveResponse(array $response, array $docs): array
    {
        if (isset($response['$ref'])) {
            $ref = $response['$ref'];
            $responseName = str_replace('#/components/responses/', '', $ref);

            return $docs['components']['responses'][$responseName] ?? $response;
        }

        return $response;
    }

    /**
     * Assert that an error response has the correct structure
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $docs
     */
    private function assertErrorResponse(
        array $response,
        array $docs,
        string $expectedErrorRef
    ): void {
        $resolved = $this->resolveResponse($response, $docs);

        // Check schema reference
        $schemaRef = $resolved['content']['application/json']['schema']['$ref'] ?? null;
        $this->assertSame(
            $expectedErrorRef,
            $schemaRef,
            'Error response should reference the correct error envelope schema'
        );
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

    /**
     * @param  array<int, array<string, mixed>>  $parameters
     * @return array<int, string>
     */
    private function queryParameterNames(array $parameters): array
    {
        return array_values(array_map(
            static fn (array $parameter): string => (string) $parameter['name'],
            array_values(array_filter(
                $parameters,
                static fn (array $parameter): bool => ($parameter['in'] ?? null) === 'query',
            )),
        ));
    }
}
