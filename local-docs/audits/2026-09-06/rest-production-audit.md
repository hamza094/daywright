DayWright backend REST and production-readiness audit — 6 September 2026

**Verdict: substantial improvement, but I would not sign off the current working tree for an unrestricted public production release.**

The resource design, Laravel structure, authorization coverage, documentation, and tests are materially stronger than the backend described in the supplied earlier audit. Several protections now exist but have implementation gaps at their boundaries. The release blockers are broken account ownership authorization, scope escalation, incorrect idempotency, webhook acknowledgement, concurrency, and recovery after partial failures.

This review covers the current working tree, including existing uncommitted changes. The historical comparison uses the supplied audit text, not a verified checkout of the original backend. Consequently, no precise percentage of personal improvement is defensible.

**Verification performed**

| Check                                    | Result                                                                                                                                                         |
| ---------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Existing PHPUnit unit and feature suites | 1,007 tests, 9,373 assertions, no failed tests; 522 PHPUnit deprecations. Runtime 4m31s, peak memory 464 MB.                                                   |
| PHPStan/Larastan                         | Passed at configured level 6, using the existing baseline and exclusions in `phpstan.neon`.                                                                    |
| Additional defect-reproduction probes    | 10 probes, 27 assertions; all confirmed the defects they were designed to reproduce. These passing probes demonstrate bugs, not correct behavior.              |
| Runtime routing                          | Inspected 117 registrations under `api/`, including middleware ordering and accidental subscription form routes.                                               |
| Production dependency audit              | `composer audit --locked --no-dev` returned 7 advisories across 4 packages, including 2 advisories rated critical upstream.                                    |
| Execution environment                    | PHP 8.3.29; test database SQLite in memory; array cache/session/mail and disabled broadcasting. External integrations were exercised through the test doubles. |

The additional probes are outside the default test directories and intentionally assert the defective behavior; invert those expectations when writing permanent regression tests. The probes are in `local-docs/audits/2026-09-06/DaywrightRestAuditProbeTest.php`. Run from the repository root with the testing configuration:
`php vendor/bin/phpunit --do-not-cache-result --testdox local-docs/audits/2026-09-06/DaywrightRestAuditProbeTest.php`.

The lock probe advances the test clock, and the stale-state probe uses two independently loaded model instances. These are deterministic reproductions of failure windows; they are not a Redis/MySQL concurrency load test.

This audit did not verify a deployed server, production secrets, backup restoration, real provider failover, sustained load, or operational alert delivery. Existing tests use SQLite and synchronous queues; passing them cannot establish those properties.

**Corrections to the earlier audit**

Several earlier criticisms confused preferences with REST requirements:

- `GET /me` is acceptable. URI spelling is not a critical security finding.
- A successful DELETE can return 200 with a representation or 204 without content. Repeating DELETE can return 404 without violating idempotency: idempotency concerns the intended effect.
- A controller does not have to extend a class named ApiController or use a service for every collection transformation to be RESTful.
- One JSON envelope, snake_case, and Form Requests are useful consistency choices, not universal REST mandates.
- A 204 body is incorrect, but calling every response-format defect “critical” distorts priorities.
- DELETE request bodies have interoperability limitations; changing every bulk action to DELETE with a body is not automatically an improvement.

HTTP method and response semantics are defined by [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.3.5). The old 4.5/10 score should therefore not be treated as a calibrated baseline.

**What actually improved**

| Earlier concern                           | Evidence in current code                                                                                                                                                                                                                                                                                                                                                                                                                               | Assessment                                                                                                                       |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------- |
| State-changing GETs                       | [routes/api/v1/projects/invitations.php:13](/C:/Users/Hamza/daywright/routes/api/v1/projects/invitations.php:13), [routes/api/v1/notifications.php:14](/C:/Users/Hamza/daywright/routes/api/v1/notifications.php:14), [routes/api/v1/projects/tasks.php:35](/C:/Users/Hamza/daywright/routes/api/v1/projects/tasks.php:35), [routes/api/v1/users.php:27](/C:/Users/Hamza/daywright/routes/api/v1/users.php:27)                                         | Invitation decisions, notification updates, archive/restore, and billing mutations now use write methods.                        |
| User enumeration and unprotected profiles | [routes/api/admin/v1.php:21](/C:/Users/Hamza/daywright/routes/api/admin/v1.php:21), [routes/api/v1/users.php:34](/C:/Users/Hamza/daywright/routes/api/v1/users.php:34), [app/Http/Controllers/Api/V1/User/UserController.php:29](/C:/Users/Hamza/daywright/app/Http/Controllers/Api/V1/User/UserController.php:29), [app/Policies/UsersPolicy.php:42](/C:/Users/Hamza/daywright/app/Policies/UsersPolicy.php:42)                                       | Public user index removed; admin listing protected; profile access checks shared membership.                                     |
| Unbounded user/task collections           | [app/Repository/Admin/UserRepository.php:17](/C:/Users/Hamza/daywright/app/Repository/Admin/UserRepository.php:17), [app/Services/Task/TaskService.php:48](/C:/Users/Hamza/daywright/app/Services/Task/TaskService.php:48), [app/Http/Requests/Api/V1/Concerns/InteractsWithApiQueryPagination.php:38](/C:/Users/Hamza/daywright/app/Http/Requests/Api/V1/Concerns/InteractsWithApiQueryPagination.php:38)                                             | Database pagination and bounded page sizes exist, including archived tasks.                                                      |
| Task link typo/N+1                        | [app/Services/Task/TaskService.php:161](/C:/Users/Hamza/daywright/app/Services/Task/TaskService.php:161), [app/Http/Resources/Api/V1/ApiResourceLink.php:19](/C:/Users/Hamza/daywright/app/Http/Resources/Api/V1/ApiResourceLink.php:19)                                                                                                                                                                                                               | Main task listing loads project data and uses named routes. This verifies the old specific issue, not every possible query path. |
| Request-coupled message service           | [app/Services/Project/MessageService.php:26](/C:/Users/Hamza/daywright/app/Services/Project/MessageService.php:26)                                                                                                                                                                                                                                                                                                                                     | Receives ProjectMessageData and delegates creation/scheduling/dispatch to actions.                                               |
| Response inconsistency                    | [app/Http/Controllers/Api/ApiController.php:26](/C:/Users/Hamza/daywright/app/Http/Controllers/Api/ApiController.php:26), [app/Exceptions/Support/ApiErrorFormatter.php:17](/C:/Users/Hamza/daywright/app/Exceptions/Support/ApiErrorFormatter.php:17)                                                                                                                                                                                                 | Resource responses and centralized machine-readable errors are much more predictable.                                            |
| Integration security                      | [app/Models/Meeting.php:24](/C:/Users/Hamza/daywright/app/Models/Meeting.php:24), [app/Http/Middleware/VerifyZoomWebhook.php:98](/C:/Users/Hamza/daywright/app/Http/Middleware/VerifyZoomWebhook.php:98), [app/Http/Integrations/Zoom/ZoomConnector.php:32](/C:/Users/Hamza/daywright/app/Http/Integrations/Zoom/ZoomConnector.php:32)                                                                                                                 | Encrypted/hidden meeting credentials, signature verification, and explicit timeouts.                                             |
| Operational discipline                    | [app/Actions/Auth/CreateApiTokenAction.php:27](/C:/Users/Hamza/daywright/app/Actions/Auth/CreateApiTokenAction.php:27), [app/Actions/Auth/EnableTwoFactorAction.php:22](/C:/Users/Hamza/daywright/app/Actions/Auth/EnableTwoFactorAction.php:22), [app/Http/Middleware/AttachRequestId.php:15](/C:/Users/Hamza/daywright/app/Http/Middleware/AttachRequestId.php:15), [app/Console/Kernel.php:33](/C:/Users/Hamza/daywright/app/Console/Kernel.php:33) | Transactional security audit events, request correlation, queue failure logging, and scheduler locking are implemented.          |
| Contract verification                     | [tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php:24](/C:/Users/Hamza/daywright/tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php:24)                                                                                                                                                                                                                                                                                         | Documentation/runtime checks now exist alongside substantial authorization, lifecycle, and integration tests.                    |

**Ten highest-impact remaining priorities**

These include REST contract defects and production implementation defects. Security or recovery defects matter more than cosmetic endpoint naming.

**1. Critical — account ownership authorization ignores the target user**

References: [app/Policies/UsersPolicy.php:32](/C:/Users/Hamza/daywright/app/Policies/UsersPolicy.php:32); [app/Http/Controllers/Api/V1/User/UserController.php:46](/C:/Users/Hamza/daywright/app/Http/Controllers/Api/V1/User/UserController.php:46) and `:63`; [app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php:24](/C:/Users/Hamza/daywright/app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php:24); [app/Http/Controllers/Api/V1/User/AvatarController.php:32](/C:/Users/Hamza/daywright/app/Http/Controllers/Api/V1/User/AvatarController.php:32); [routes/api/v1/users.php:34](/C:/Users/Hamza/daywright/routes/api/v1/users.php:34).

The policy is:

```php
public function owner(User $user): bool
{
    return $user->is(auth()->user());
}
```

Laravel supplies the authenticated actor as the first argument to a policy method. The target account supplied by the controller should be the second argument, but this method does not accept it. The comparison therefore compares the actor with itself.

Reproduced: a real non-admin Sanctum token with only `team:write` successfully changed a different user's email, with no shared-project relationship. The target's `email_verified_at` also remained populated. The same policy guards profile mutation, soft deletion, force deletion, and avatar changes. This is broken object-level authorization; a known target UUID is sufficient to reach the demonstrated mutation. Combined with email-based password recovery, it creates an account-takeover path for accounts without an additional login factor. The test demonstrated the cross-account email mutation, not a real-world takeover.

Exact first fix:

```php
public function owner(User $actor, User $target): bool
{
    return $actor->is($target);
}
```

Review the existing admin `before()` override as an explicit privilege decision. Add negative tests for a different user across update, delete, force delete, and avatar endpoints, using real bearer tokens as well as sessions.

There is a second, independent **High** scope boundary defect. Even after fixing ownership, the profile route's `team:write` scope can change its own account's login email through [app/Http/Requests/Api/V1/User/UserRequest.php:50](/C:/Users/Hamza/daywright/app/Http/Requests/Api/V1/User/UserRequest.php:50) and [app/Services/User/UserService.php:36](/C:/Users/Hamza/daywright/app/Services/User/UserService.php:36). This also reproduced successfully. A team-management integration should not acquire account-recovery authority.

Remove email from the general profile request and DTO allowlist at [app/DataTransferObjects/User/UpdateUserData.php:25](/C:/Users/Hamza/daywright/app/DataTransferObjects/User/UpdateUserData.php:25). Implement a dedicated `POST /users/me/email-change-requests` action restricted to first-party authentication with recent password/2FA confirmation. Persist `pending_email`, verify the new address before switching, notify the old address, and explicitly invalidate the old verification state. Put account deletion behind an explicit account-security boundary too.

Finally, `ForceDeleteUserController.php:26` assumes `withTrashed()` means only trashed models are bound. It actually allows both active and trashed models. If the documented archive-first rule is intended, enforce it explicitly with a 409 state-conflict response for active accounts.

**2. High — idempotency identity omits the concrete resource**

References: [vendor/wendelladriel/laravel-idempotency/src/Support/RequestFingerprint.php:12](/C:/Users/Hamza/daywright/vendor/wendelladriel/laravel-idempotency/src/Support/RequestFingerprint.php:12) and `:34`; [routes/api/v1/projects/invitations.php:13](/C:/Users/Hamza/daywright/routes/api/v1/projects/invitations.php:13); [routes/api/v1/projects/meetings.php:16](/C:/Users/Hamza/daywright/routes/api/v1/projects/meetings.php:16).

The installed package uses the route name for both cache identity and fingerprint. Bound project/task/meeting identifiers are absent. For example, both projects' invitation requests identify as `api.v1.send.invitation`.

Reproduced: invite the same user to project A, then project B using the same idempotency key and JSON body. Both return 201, but only A receives the invitation. This silently reports success for an operation that never happened.

Exact fix: patch/fork or replace the middleware dependency through Composer, rather than editing vendor files as the permanent solution. Include method, authenticated principal, concrete path/bound resource identifiers, normalized query, and canonical payload in request identity. Either scope keys per concrete URI, or reject cross-resource reuse explicitly; never replay a different resource's response. Normalize uploaded form fields/file digests as well, because raw multipart bodies are not a reliable canonical payload.

The middleware also returns stored responses before route policies/controllers execute. Preserve current access authorization before replaying sensitive representations; separate that authorization from one-time state-transition checks needed for invitation acceptance.

**3. High — lock leases expire before protected work can finish**

References: [vendor/wendelladriel/laravel-idempotency/src/Http/Middleware/Idempotent.php:93](/C:/Users/Hamza/daywright/vendor/wendelladriel/laravel-idempotency/src/Http/Middleware/Idempotent.php:93); [app/Services/Zoom/ZoomConnectorManager.php:31](/C:/Users/Hamza/daywright/app/Services/Zoom/ZoomConnectorManager.php:31); [app/Http/Integrations/Zoom/ZoomConnector.php:32](/C:/Users/Hamza/daywright/app/Http/Integrations/Zoom/ZoomConnector.php:32).

The idempotency lock lasts 10 seconds. OAuth refresh locking lasts 15 seconds, while a single Zoom request may last 30 seconds. Protected work can therefore continue after another request acquires the same lock.

Reproduced: advancing the clock 11 seconds inside the first idempotent request allows the same key's callback to execute a second time before the first finishes.

Exact fix: establish an end-to-end operation deadline, make each lease exceed its bounded duration with a margin, and use renewal/fencing when that bound cannot be guaranteed. An idempotency lock must also recheck stored completion after acquiring the lock. Add durable unique operation records for externally visible writes: increasing a Redis TTL alone does not cover a crash after provider success.

**4. High — failed webhook delivery can be acknowledged without enqueueing**

Reference: [app/Http/Middleware/VerifyZoomWebhook.php:59](/C:/Users/Hamza/daywright/app/Http/Middleware/VerifyZoomWebhook.php:59) and `:175`; [app/Services/Webhooks/ZoomWebhookDispatcher.php:18](/C:/Users/Hamza/daywright/app/Services/Webhooks/ZoomWebhookDispatcher.php:18).

The replay key is reserved before downstream validation and job dispatch. It remains reserved if downstream processing throws or returns a server error. A matching retry receives 202 immediately.

Reproduced: first dispatch throws “queue unavailable”; identical retry receives 202 and never reaches dispatch. A temporary queue failure can become a lost event.

Exact fix: use a durable webhook inbox with a unique provider event identifier and received/processing/completed state. Acknowledge only after durable acceptance, and let a worker/reconciler process pending inbox rows. As an interim fix, release reservations after downstream failure and distinguish an in-flight reservation from accepted work; do not report accepted solely because a cache key exists.

**5. High — state transitions validate stale model state**

References: [app/Traits/HasStateMachine.php:26](/C:/Users/Hamza/daywright/app/Traits/HasStateMachine.php:26); [app/Services/Task/TaskService.php:85](/C:/Users/Hamza/daywright/app/Services/Task/TaskService.php:85); [app/Services/Project/ProjectService.php:114](/C:/Users/Hamza/daywright/app/Services/Project/ProjectService.php:114).

A database transaction is present, but TaskService checks a route-bound model loaded before that transaction. The transition trait validates that object's old status and then issues an unconditional update.

Reproduced: load two Pending task snapshots, cancel through the first, then transition the stale second snapshot to InProgress. The cancelled task reopens despite Cancelled being terminal.

Exact fix: re-fetch and lock the row inside the transaction before both validation and mutation:

```php
return DB::transaction(function () use ($task, $data) {
    $lockedTask = Task::query()->whereKey($task->getKey())
        ->lockForUpdate()->firstOrFail();
    // Validate transition and apply every update to $lockedTask.
});
```

Apply the same pattern to project transitions. For collaborative editing, add an explicit version and conditional update/If-Match contract to detect lost updates rather than silently overwrite another client's work.

**6. High release priority — production dependencies fail the security audit**

References: [composer.lock:5389](/C:/Users/Hamza/daywright/composer.lock:5389), `:6182`, `:6352`, `:6535`; [.github/workflows/tests.yml:49](/C:/Users/Hamza/daywright/.github/workflows/tests.yml:49).

| Installed production package | Locked version | Audit result                                       |
| ---------------------------- | -------------- | -------------------------------------------------- |
| mtdowling/jmespath.php       | 2.8.0          | Critical compiler code-injection advisory          |
| paragonie/sodium_compat      | 2.5.0          | Ed25519 public-key validation advisory             |
| phpoffice/phpspreadsheet     | 1.30.4         | Four advisories, including a critical patch bypass |
| phpseclib/phpseclib          | 3.0.52         | Certificate-validation SSRF advisory               |

These are dependency findings, not proof of remotely exploitable DayWright endpoints. In particular, the [JMESPath maintainer advisory](https://github.com/jmespath/jmespath.php/security/advisories/GHSA-pcw8-m77r-2528) requires attacker-controlled expressions reaching its compiler. The [PhpSpreadsheet maintainer advisory](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-87m4-826x-3crx) documents the separate critical issue.

Exact fix: update the four packages with their required transitive dependencies and rerun the suite plus audit. JMESPath must reach at least 2.9.1 and sodium_compat at least 2.5.1; choose patched compatible releases for the entire advisory set, not just one CVE. Add `composer audit --locked --no-dev` as a required CI check. Investigate reachability while updating; do not label the whole application as confirmed RCE based on package presence.

**7. High — external operations and message dispatch lack complete recovery**

References: [app/Actions/Meetings/CreateProjectMeeting.php:40](/C:/Users/Hamza/daywright/app/Actions/Meetings/CreateProjectMeeting.php:40) and `:61`; [app/Services/Paddle/SubscriptionService.php:175](/C:/Users/Hamza/daywright/app/Services/Paddle/SubscriptionService.php:175); [app/Actions/Project/DispatchProjectMessageAction.php:24](/C:/Users/Hamza/daywright/app/Actions/Project/DispatchProjectMessageAction.php:24), `:84`, and `:111`; [app/Models/Message.php:51](/C:/Users/Hamza/daywright/app/Models/Message.php:51).

There are three concrete recovery gaps:

- Meeting creation commits a Pending row, calls Zoom, then saves the provider identifier. A crash after provider success but before local persistence can leave an orphaned meeting. A timeout is classified as Failed even when the remote outcome is unknown. I found no scheduled reconciliation for pending/failed meeting creation.
- Paddle calls, including `swapAndInvoice()`, execute while a user row is locked inside a transaction configured for five attempts. External effects cannot be rolled back with that database transaction; a retry after a relevant database failure can repeat external work.
- Message dispatch commits a `claim:...` marker before its after-commit callback. A process crash can leave that marker permanently, and both dispatch and the scheduler require a null batch_id. Also, failure cleanup is placed in `then()`, while Laravel's normal terminal-failure path invokes `catch/finally`, not the successful-completion callback. The catch only logs.

These are code-path findings; live provider crashes were not induced.

Exact fix: persist an operation/outbox record in the same transaction as the business change; perform network work outside retryable database transactions; record provider IDs and explicit unknown outcomes; reconcile pending operations. Give message claims timestamps/leases and recover abandoned claims. Use `finally()` or explicit per-recipient delivery state for batch completion/failure, and retry only failed recipients. Preserve a provider-supported idempotency reference wherever available. Laravel distinguishes successful and terminal batch callbacks in its [queue documentation](https://laravel.com/docs/12.x/queues#dispatching-batches).

**8. Medium — PUT means partial update, and unchanged updates fail**

References: [routes/api/v1.php:26](/C:/Users/Hamza/daywright/routes/api/v1.php:26); [routes/api/v1/projects/tasks.php:15](/C:/Users/Hamza/daywright/routes/api/v1/projects/tasks.php:15); [app/Http/Requests/Api/V1/Project/ProjectUpdateRequest.php:44](/C:/Users/Hamza/daywright/app/Http/Requests/Api/V1/Project/ProjectUpdateRequest.php:44); [app/Http/Requests/Api/V1/Task/TaskUpdateRequest.php:51](/C:/Users/Hamza/daywright/app/Http/Requests/Api/V1/Task/TaskUpdateRequest.php:51); [app/Traits/HasStateMachine.php:31](/C:/Users/Hamza/daywright/app/Traits/HasStateMachine.php:31).

apiResource registers PUT and PATCH against validators using `sometimes`. Omitted fields survive PUT. Project validation additionally rejects unchanged names/about/notes, and the state machine rejects setting the current status.

Reproduced: PUT with only notes succeeds and preserves omitted fields; the same request then returns 422.

Exact fix: register only PATCH for the existing partial-update contract, or implement separate replacement semantics for PUT with all required writable fields. Remove “must differ” validators. For a setter-style update, accept an already-satisfied state as a no-op; only notify when business attributes actually change. Update documentation and tests accordingly. The retry's different status alone is not an idempotency violation; misleading PUT replacement semantics and unnecessary no-op failures are the defects.

**9. Medium — error formatting strips protocol headers**

References: [app/Exceptions/Traits/HandlesApiExceptions.php:70](/C:/Users/Hamza/daywright/app/Exceptions/Traits/HandlesApiExceptions.php:70) and `:92`.

The MethodNotAllowed and generic HttpException renderers rebuild JSON responses without copying exception headers. The throttle-specific renderer preserves them, but that does not cover the other handlers.

Reproduced: a MethodNotAllowedHttpException loses `Allow`; an idempotency-style 409 loses `Retry-After`. The OpenAPI parity test explicitly expects the latter header in documented responses, so documentation correctness does not imply runtime correctness.

Exact fix:

```php
return ApiErrorFormatter::response($message, $status, $code)
    ->withHeaders($e->getHeaders());
```

Apply this to both HTTP exception rendering branches and test actual responses. Preserve the existing 429 handling.

**10. Medium — pagination links do not preserve the selected collection**

References: [app/Services/Dashboard/UserProjectListingService.php:51](/C:/Users/Hamza/daywright/app/Services/Dashboard/UserProjectListingService.php:51); also inspect [app/Services/Project/MeetingService.php:40](/C:/Users/Hamza/daywright/app/Services/Project/MeetingService.php:40) and [app/Repository/Admin/UserRepository.php:33](/C:/Users/Hamza/daywright/app/Repository/Admin/UserRepository.php:33).

The project paginator sets the path but does not append active filters, sort, or page size.

Reproduced: `GET /projects?filter[search]=Audit&sort=name&per_page=1` produces a next link containing only `page=2`. Following the server-provided link changes the collection and page size.

Exact fix: pass the validated canonical query parameters to the listing service and apply `$paginator->appends($queryParameters)` after excluding page. `withQueryString()` is a smaller fix where request coupling is acceptable. Add an integration test that follows links.next and checks filtering/order/page size on the returned page.

**Additional specific gaps**

| Severity                          | Reference                                                                                                                                                                                                                                                                                                                                                                              | Issue and exact fix                                                                                                                                                                                                                                                                                                                                                              |
| --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Medium                            | [routes/api/v1/users.php:27](/C:/Users/Hamza/daywright/routes/api/v1/users.php:27)                                                                                                                                                                                                                                                                                                     | `Route::singleton()->creatable()` registers GET create/edit routes, but SubscriptionController has neither method. Both returned 500 in probes. Use `Route::apiSingleton('subscription', SubscriptionController::class)->creatable()`, retaining the appropriate middleware, or explicitly register the four API operations.                                                     |
| Medium                            | [app/Services/FileService.php:117](/C:/Users/Hamza/daywright/app/Services/FileService.php:117); [app/Services/User/UserService.php:54](/C:/Users/Hamza/daywright/app/Services/User/UserService.php:54)                                                                                                                                                                                 | Storage failures and broad password-update exceptions are converted into 422 validation errors. Rethrow domain validation separately; report/map infrastructure exceptions to an appropriate 5xx API error. A client cannot fix a storage outage by changing valid input.                                                                                                        |
| Medium                            | [app/Http/Middleware/RequireSessionAuth.php:45](/C:/Users/Hamza/daywright/app/Http/Middleware/RequireSessionAuth.php:45); [app/Http/Middleware/RequireFirstPartyAuth.php:44](/C:/Users/Hamza/daywright/app/Http/Middleware/RequireFirstPartyAuth.php:44); [app/Exceptions/Support/ApiErrorFormatter.php:24](/C:/Users/Hamza/daywright/app/Exceptions/Support/ApiErrorFormatter.php:24) | These middleware return empty arrays for errors/meta, while the formatter emits objects. Use ApiErrorFormatter from middleware too and assert raw JSON types.                                                                                                                                                                                                                    |
| Medium, privacy decision required | [app/Http/Resources/Api/V1/User/PublicUserProfileResource.php:78](/C:/Users/Hamza/daywright/app/Http/Resources/Api/V1/User/PublicUserProfileResource.php:78); [app/Http/Resources/Api/V1/User/UserInfoResource.php:32](/C:/Users/Hamza/daywright/app/Http/Resources/Api/V1/User/UserInfoResource.php:32)                                                                               | Shared-project membership exposes email, mobile, and address. Shared membership is now checked, but it does not establish that every collaborator should see personal contact data. Create a collaborator resource with an explicit field allowlist; gate private contact fields through a documented permission/visibility setting.                                             |
| Medium                            | [app/Logging/ScrubSensitiveData.php:12](/C:/Users/Hamza/daywright/app/Logging/ScrubSensitiveData.php:12); [config/logging.php:126](/C:/Users/Hamza/daywright/config/logging.php:126); [app/Listeners/PaddleEventListener.php:47](/C:/Users/Hamza/daywright/app/Listeners/PaddleEventListener.php:47)                                                                                   | Log scrubbing covers four exact keys; access_token, refresh_token, Authorization, nested exceptions, and several channels are outside that coverage. Paddle audit metadata retains the complete webhook payload. Apply one sanitizer to relevant channels, sanitize exception/request context, and allowlist audit metadata. No secret leak was demonstrated during this review. |
| Low design priority               | [routes/api/v1/projects/tasks.php:25](/C:/Users/Hamza/daywright/routes/api/v1/projects/tasks.php:25); [routes/api/admin/v1.php:51](/C:/Users/Hamza/daywright/routes/api/admin/v1.php:51); [routes/web/v1.php:49](/C:/Users/Hamza/daywright/routes/web/v1.php:49)                                                                                                                       | assign/unassign, bulk-delete, and fetch-user remain action-oriented naming. If changing the public contract, use task assignee resources, a bulk-deletion job resource for substantial work, and a two-factor status resource. Do not delay security fixes for these names.                                                                                                      |

**REST/Laravel assessment**

| Dimension                        | Current assessment                                                                                                                                                                                                                                                                                                                                                                |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Resource orientation and nesting | Mostly sound; projects/tasks/meetings/conversations are recognizable resources. Two resource levels of nesting are reasonable. Remaining action paths are a low-priority consistency matter.                                                                                                                                                                                      |
| Methods                          | The old unsafe business GETs are corrected. PUT/PATCH mapping and accidental subscription form routes remain concrete defects. OAuth callbacks need to be assessed as OAuth flows, not renamed simply because they use GET.                                                                                                                                                       |
| Statelessness                    | Third-party bearer requests can carry their credentials on each request. First-party session endpoints and Sanctum's stateful SPA support remain intentionally stateful. This hybrid is conventional Laravel architecture; the whole surface is not strictly stateless REST. Sanctum documents both modes in its [official documentation](https://laravel.com/docs/12.x/sanctum). |
| Status codes/errors              | Major improvement. Message-bearing DELETE responses are valid. Remaining defects are missing HTTP headers, 422 for infrastructure failures, and invalid form routes. Checkout returning a paylink is not proof a subscription has been created, so its 200 is defensible.                                                                                                         |
| Controllers/validation/resources | Mostly thin controllers, DTOs, Form Requests, services/actions, and named JsonResources. Some duplication and request coupling persist, but extra abstraction alone would not improve release safety.                                                                                                                                                                             |
| Pagination/query access          | Real database pagination, allowed query parameters, bounded page sizes, and eager loading exist. Fix link propagation and measure query counts/load on the production database.                                                                                                                                                                                                   |
| Authentication/authorization     | Sanctum, policies, scoped bindings, first-party/session restrictions, and rate limits are meaningful. The broken owner policy and email/scope escalation must be fixed.                                                                                                                                                                                                           |
| Naming/contracts/versioning      | /api/v1 registration is explicit in RouteServiceProvider. Snake_case and data envelopes are much more consistent. No tested public deprecation/migration policy was demonstrated; do not silently remove already-consumed v1 contracts.                                                                                                                                           |
| Richardson maturity              | Broadly Level 2, with self/pagination links. These links do not constitute a Level 3 hypermedia-driven state machine. Lack of Level 3 is not a launch blocker for this product.                                                                                                                                                                                                   |

**Examples worth keeping**

- `GET /api/v1/projects`: owner/member filtering, validation, pagination, and resources. Fix its links rather than replace the design.
- `GET /api/v1/projects/{project}/tasks`: nested scope, token ability, project policy, pagination, eager loading, and generated self links.
- `GET /api/v1/admin/users`: admin/verified/session restrictions plus paginated filtered data.
- `POST /api/v1/api-tokens`: session-only management, scoped issuance, transactionally recorded audit event, notification, and replay protection intent.
- `DELETE /api/v1/projects/{project}/members/{user}`: clear resource deletion, management authorization, throttling, and an audited action.

References: [routes/api/v1.php:26](/C:/Users/Hamza/daywright/routes/api/v1.php:26), [routes/api/v1/projects/tasks.php:14](/C:/Users/Hamza/daywright/routes/api/v1/projects/tasks.php:14), [routes/api/admin/v1.php:21](/C:/Users/Hamza/daywright/routes/api/admin/v1.php:21), [routes/api/v1/tokens.php:9](/C:/Users/Hamza/daywright/routes/api/v1/tokens.php:9), [routes/api/v1/projects/invitations.php:38](/C:/Users/Hamza/daywright/routes/api/v1/projects/invitations.php:38).

**Scores and professional assessment**

My judgment is approximately **7/10 for practical REST design** and **5/10 for demonstrated production readiness**. These are reviewer judgments, not a certification or percentages of completion. The production score is limited by a critical authorization defect and demonstrated reliability defects, despite good structure and a green suite.

The improvement is visible in the kinds of concerns the code now handles: least-privilege intent, explicit resource contracts, transaction boundaries, external-service state, audit events, and automated verification. That is a substantial step beyond basic CRUD implementation. What remains is proving those mechanisms under adversarial combinations and partial failure.

This repository supports applying for **Laravel backend developer roles at strong junior or early mid-level**, and mid-level interviews where you can explain and modify this code independently. Suitable client work includes internal workflow tools, business portals, project/team APIs, existing Laravel maintenance, and bounded integrations with staging and review.

The repository alone does not demonstrate senior backend architect readiness. Senior evidence would include production ownership, incident diagnosis, safe releases, database/queue concurrency decisions, threat modeling, performance measurements, and recovery exercises. Those capabilities may exist outside this repository, but I did not verify them.

For a portfolio case study, explain one scope bug, one duplicate-operation bug, and one lost-webhook bug; show the failing regression test, repair, and measurable verification. That would make stronger evidence than listing the number of packages or patterns used.

**A release roadmap tied to this code**

1. **Before exposing developer-token workflows:** fix the owner policy and isolate email/account mutations; repair resource-aware idempotency and lease handling; preserve webhook delivery on queue failure; patch vulnerable dependencies.
2. **Before relying on integrations for customer work:** add durable inbox/outbox/operation records, reconcile ambiguous Zoom/Paddle outcomes, fix message claim/batch recovery, and lock state transitions correctly.
3. **Before stabilizing the public API contract:** make PUT/PATCH behavior explicit, accept legitimate no-op setters, preserve HTTP headers and pagination queries, remove subscription create/edit routes, and unify error JSON types.
4. **Before declaring production ready:** execute the failure/concurrency suite against the actual production database engine and shared Redis with real asynchronous workers. The current CI in [.github/workflows/tests.yml:49](/C:/Users/Hamza/daywright/.github/workflows/tests.yml:49) runs SQLite, and [phpunit.xml:37](/C:/Users/Hamza/daywright/phpunit.xml:37) configures synchronous queues. Measure p95 latency and query counts on realistic data; restart a worker mid-operation and demonstrate recovery.
5. **Make deployment repeatable:** align [docs/DEPLOYMENT.md:22](/C:/Users/Hamza/daywright/docs/DEPLOYMENT.md:22) with Composer's PHP >=8.3 requirement, implement the dependency health checks currently only requested in [docs/DEPLOYMENT.md:408](/C:/Users/Hamza/daywright/docs/DEPLOYMENT.md:408), test backup restore and rollback, verify alerts reach an operator, and migrate the deprecated PHPUnit metadata.

Start applying for appropriate backend work now. Treat these remaining gaps as the next engineering milestone; they are specific and testable, not a reason to postpone all professional work.
