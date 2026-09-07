# Phase 1: Critical security fixes

Release boundary: **before developer-token workflows**.

Source: `local-docs/audits/2026-09-06/rest-production-audit.md`, priorities 1-4 and 6, additional gaps on privacy/logging, and release-roadmap step 1. Findings and reproductions are separated into [findings-reference.md](findings-reference.md).

## SWE 1.6 handoff

Implement this phase in the current Laravel repository. Preserve unrelated working-tree changes. Read the referenced symbols before editing because audit line numbers can shift. Each task below states the audit requirement; proposed file placement, expanded test scenarios, and verification procedures are implementation elaborations. New proposed paths do not currently exist and can be replaced by appropriate existing equivalents.

Prerequisites: a working PHP runtime satisfying Composer, installed dependencies, and an isolated test database. Do not run mutation tests against customer data. This phase does not require earlier implementation phases.

Port scenarios from `local-docs/audits/2026-09-06/DaywrightRestAuditProbeTest.php` into permanent tests with corrected expectations. The forensic probes assert the defects; their passing result is not repair evidence.

## P1.1 — Make account ownership target-aware

**Files to modify/review**

- `app/Policies/UsersPolicy.php:32` — ownership method and existing admin `before()` override.
- `app/Http/Controllers/Api/V1/User/UserController.php:46` and `:63` — update/delete policy use.
- `app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php:24` — force-delete authorization.
- `app/Http/Controllers/Api/V1/User/AvatarController.php:32` — avatar mutation authorization; inspect both mutation methods.
- `routes/api/v1/users.php:34` — route boundaries and target binding.

**Implementation checklist — audit requirement**

- [ ] Make the policy accept the authenticated actor and target model.
- [ ] Verify every account/avatar mutation passes the actual target to authorization.
- [ ] Record the intended admin override and preserve only the explicitly intended administrative authority.
- [ ] Retain current profile-read membership restrictions.

Verbatim audit snippet:

```php
public function owner(User $actor, User $target): bool
{
    return $actor->is($target);
}
```

**Tests to add/update**

- `tests/Feature/Api/V1/Users/UserTest.php` — self versus unrelated target for profile update, deletion, and force deletion.
- `tests/Feature/Api/V1/Users/UserAvatarTest.php` — avatar creation/update/removal against another account.
- `tests/Feature/Api/V1/Users/UserTokenTest.php` — credential boundary integration where relevant.
- [ ] Use actual issued Sanctum bearer tokens as well as authenticated sessions; keep auth/policy middleware enabled.
- [ ] Cover unrelated and shared-project users: shared membership must not grant profile mutation.
- [ ] Assert forbidden responses and unchanged target data/files, alongside legitimate owner/admin success cases.
- [ ] Port the cross-account mutation probe at line 146.

**Verification**

- [ ] Inspect all `owner` policy call sites and confirm target-aware behavior.
- [ ] Run the user and avatar test groups with authentication middleware active.

## P1.2 — Separate account recovery changes from team/profile authority

**Files to modify/review**

- `routes/api/v1/users.php:34`.
- `app/Http/Requests/Api/V1/User/UserRequest.php:50`.
- `app/DataTransferObjects/User/UpdateUserData.php:25`.
- `app/Services/User/UserService.php:36`.
- Supporting existing files: `app/Models/User.php`, `app/Http/Middleware/RequireFirstPartyAuth.php`, `app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php`.

**New (proposed) implementation placement**

- `app/Http/Controllers/Api/V1/User/EmailChangeRequestController.php`.
- `app/Http/Requests/Api/V1/User/EmailChangeRequest.php`.
- `app/Actions/Auth/RequestEmailChangeAction.php`.
- `app/Actions/Auth/ConfirmEmailChangeAction.php`.
- A timestamped migration under `database/migrations/` for pending-email/request state; choose its exact generated name during implementation.
- Notification classes under `app/Notifications/` for new-address verification and old-address notification.

**Implementation checklist — audit requirement**

- [ ] Remove email from general profile validation and the DTO's writable allowlist; ensure downstream service assignment cannot bypass that restriction.
- [ ] Implement `POST /users/me/email-change-requests` within the current API prefix, yielding `POST /api/v1/users/me/email-change-requests`.
- [ ] Require first-party authentication and recent password/2FA confirmation appropriate to the account's authentication configuration.
- [ ] Persist `pending_email`, verify the new address before switching, notify the old address, and invalidate verification belonging to the old address.
- [ ] Set the new address's verification state only from successful verification of that address.
- [ ] Put account deletion, including force deletion, behind an explicit account-security boundary. A team-management scope alone must not grant account-security authority.

**Implementation elaboration**

- [ ] Reuse suitable account-security confirmation mechanisms already present; first-party/session authentication alone is not evidence of recent confirmation.
- [ ] Define expiring, single-use confirmation and atomic email uniqueness checks. Replace/supersede pending requests deliberately.
- [ ] Return existing safe validation/conflict formats for invalid or stale confirmation; avoid allowing a stale link to confirm a newer pending address.
- [ ] Update the affected endpoint documentation and security audit-event metadata with an explicit allowlist.

**Tests to add/update**

- `tests/Feature/Api/V1/Users/UserTest.php`.
- `tests/Feature/DataTransferObjects/User/UpdateUserDataTest.php`.
- `tests/Feature/Api/ScopeMiddlewareTest.php`.
- **New (proposed):** `tests/Feature/Api/V1/Users/EmailChangeRequestTest.php`.
- [ ] Port the own-email token probe at line 25 with expectations that a general team token cannot change login email.
- [ ] Test missing/expired recent confirmation, token-only callers, successful verified change, old-address notification, uniqueness conflict, expired/reused confirmation, and superseded pending requests.
- [ ] Verify both old and new account states around confirmation, rather than checking only HTTP success.
- [ ] Test the chosen deletion boundary for sessions, permitted first-party credentials, and third-party tokens.

**Verification**

- [ ] Search all assignments to `email` and confirm no generic profile path bypasses the dedicated flow.
- [ ] Verify authentication/authorization ordering and account-security documentation.

## P1.3 — Enforce the chosen force-deletion state contract

**Files**

- `app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php:24` and `:26`.
- `routes/api/v1/users.php`.
- `tests/Feature/Api/V1/Users/UserTest.php`.

**Implementation checklist — audit requirement**

- [ ] Resolve and document whether archive-first deletion is the intended API contract.
- [ ] If retaining archive-first behavior, explicitly reject an active target with a 409 state-conflict response; `withTrashed()` alone is insufficient.
- [ ] Apply P1.1 ownership and P1.2 account-security checks before destructive execution.

**Tests and verification**

- [ ] Active owner target: 409 and no deletion when archive-first applies.
- [ ] Archived owner target: authorized deletion succeeds.
- [ ] Another user's target: denied regardless of archive state.
- [ ] Missing target: preserve the documented not-found behavior.

## P1.4 — Correct idempotency identity and replay authorization

**Files to inspect; vendor paths are not permanent edit targets**

- `vendor/wendelladriel/laravel-idempotency/src/Support/RequestFingerprint.php:12` and `:34`.
- `vendor/wendelladriel/laravel-idempotency/src/Http/Middleware/Idempotent.php`.
- `composer.json` and `composer.lock` — managed patch/fork/replacement.
- `routes/api/v1/projects/invitations.php:13` and `routes/api/v1/projects/meetings.php:16`.
- Supporting call sites: `routes/api/v1/tokens.php`, `routes/api/v1/users.php`, `app/Http/Kernel.php`. Search every use of the package middleware.

**Implementation checklist — audit requirement**

- [ ] Patch/fork or replace the dependency through Composer so a clean installation reproduces the fix.
- [ ] Include HTTP method, authenticated principal, concrete path/bound resource identifiers, normalized query, and canonical payload in request identity.
- [ ] Choose and document either keys scoped per concrete URI or explicit conflict on cross-resource reuse. Never replay another resource's success response.
- [ ] Normalize structured form fields and uploaded-file digests; avoid raw multipart-body identity.
- [ ] Perform current access authorization before replay of sensitive representations.
- [ ] Separate access authorization from one-time state-transition checks so a legitimate retry of an already-completed invitation action remains possible.

**Tests to add/update**

- `tests/Feature/Api/Middleware/Idempotency/IdempotencyContractTest.php`.
- `tests/Feature/Api/Middleware/Idempotency/IdempotentRoutesRegistrationTest.php`.
- `tests/Feature/Api/V1/InvitationTest.php`.
- `tests/Feature/Api/V1/Meetings/MeetingTokenTest.php`.
- [ ] Same actor/key/body, project A then project B: separate execution or documented conflict, never a false successful replay.
- [ ] Same operation retried: one business effect and the correct saved response.
- [ ] Different actors, changed payload/query/resource IDs, and equivalent reordered canonical payloads follow the documented identity rules.
- [ ] Multipart boundary changes alone preserve identity; changing a file's content changes its digest/identity.
- [ ] Revoked current access denies replay; legitimate invitation retries survive already-completed transition checks.
- [ ] Port the cross-project probe at line 56.

**Verification**

- [ ] Inspect runtime middleware ordering for every protected route group.
- [ ] Confirm the change survives a clean Composer installation in CI or an isolated checkout.

## P1.5 — Align deadlines, leases, and completion checks

**Files**

- `vendor/wendelladriel/laravel-idempotency/src/Http/Middleware/Idempotent.php:93` — address via the P1.4 managed dependency solution.
- `app/Services/Zoom/ZoomConnectorManager.php:31`.
- `app/Http/Integrations/Zoom/ZoomConnector.php:32`.
- Supporting configuration: `config/queue.php`; introduce a configuration file only if needed to centralize the selected bounds.

**Implementation checklist — audit requirement**

- [ ] Calculate an end-to-end bounded duration including provider retries, backoff, and local persistence.
- [ ] Set lock leases above that duration with a margin; use renewal/fencing where duration cannot be bounded.
- [ ] Recheck stored completion after acquiring the idempotency lock.
- [ ] Preserve lock ownership when releasing/renewing so an old holder cannot release a new owner's lock.
- [ ] Record the need for durable unique operation records for externally visible writes; implement them in P2.1-P2.3 before treating those integrations as crash-safe.

**Tests to add/update**

- `tests/Feature/Api/Middleware/Idempotency/IdempotencyContractTest.php`.
- `tests/Unit/Http/Integrations/Zoom/ZoomConnectorTest.php`.
- **New (proposed):** `tests/Unit/Services/Zoom/ZoomConnectorManagerTest.php`.
- [ ] Port the expiry probe at line 83 with the selected deadline/lease contract.
- [ ] A waiting request sees completion after acquiring the lock and does not repeat the callback.
- [ ] OAuth refresh contention and slow responses stay within the documented bounds.
- [ ] An old lock owner cannot clear a successor's lease.

**Verification**

- [ ] Write a small timeout/lease table covering the HTTP operation, refresh, retry policy, and worker bounds.
- [ ] Run deterministic tests now; P4.2 requires the actual multiple-process/shared-Redis check.

## P1.6 — Preserve webhook retries when downstream handling fails

**Files**

- `app/Http/Middleware/VerifyZoomWebhook.php:59` and `:175`.
- `app/Services/Webhooks/ZoomWebhookDispatcher.php:18`.
- Supporting entry point: `app/Http/Controllers/Api/V1/Webhooks/ZoomWebhookController.php`.

**Implementation checklist — audit requirement**

- [ ] Implement the full durable-inbox approach in P2.1 now if practical, or make the audit's interim reservation repair.
- [ ] For the interim repair, release the owned reservation when downstream processing throws or returns a server error.
- [ ] Distinguish in-flight reservation from accepted work; do not return acceptance solely because a cache key exists.
- [ ] Keep signature/timestamp validation and the existing endpoint-validation challenge behavior.
- [ ] Explicitly record that cache cleanup does not solve process crashes between reservation and dispatch. Full durable acceptance remains a phase 2 release gate.

**Tests to add/update**

- `tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php`.
- `tests/Feature/Api/Webhooks/Zoom/ZoomWebhookTest.php`.
- [ ] Port the downstream-failure probe at line 34.
- [ ] Exercise both a thrown dispatch failure and a downstream 5xx response followed by a provider retry.
- [ ] Verify successful duplicates and in-flight duplicates have distinct, documented handling.
- [ ] Retain signature, timestamp, and endpoint-validation coverage.

**Verification**

- [ ] A failed acceptance attempt remains eligible for retry.
- [ ] Mark the implementation explicitly as interim or durable; do not close the phase 2 inbox task on cache behavior alone.

## P1.7 — Update affected dependencies and require the audit in CI

**Files**

- `composer.json`.
- `composer.lock:5389`, `:6182`, `:6352`, `:6535`.
- `.github/workflows/tests.yml:49`.

**Implementation checklist — audit requirement**

- [ ] Refresh the dependency audit and update `mtdowling/jmespath.php`, `paragonie/sodium_compat`, `phpoffice/phpspreadsheet`, and `phpseclib/phpseclib` with necessary transitive dependencies.
- [ ] Treat JMESPath 2.9.1 and sodium_compat 2.5.1 as the source audit's minimum floors. Select compatible releases covering the full current advisory set.
- [ ] Add a required CI step that fails on the production dependency audit command.
- [ ] Record reachability investigation separately from dependency presence; do not claim application RCE solely from an installed version.

Audit command:

```sh
composer audit --locked --no-dev
```

Implementation elaboration: a targeted update command to evaluate after checking constraints:

```sh
composer update mtdowling/jmespath.php paragonie/sodium_compat phpoffice/phpspreadsheet phpseclib/phpseclib --with-all-dependencies
```

**Tests and verification**

- [ ] Run the full existing PHPUnit suite and configured static analysis after dependency changes.
- [ ] Exercise existing spreadsheet/export and integration paths affected by resolved transitive changes.
- [ ] Confirm CI treats an audit failure as a failed required check; preserve the Composer lockfile.

## P1.8 — Define collaborator field visibility

Placement in phase 1 is an implementation-priority choice for the audit's additional privacy item; the original roadmap did not assign it a phase.

**Files**

- `app/Http/Resources/Api/V1/User/PublicUserProfileResource.php:78`.
- `app/Http/Resources/Api/V1/User/UserInfoResource.php:32`.
- Supporting policy: `app/Policies/UsersPolicy.php`.
- **New (proposed):** `app/Http/Resources/Api/V1/User/CollaboratorResource.php`.

**Implementation checklist — audit requirement**

- [ ] Decide which fields a collaborator may see and document the permission/visibility rule.
- [ ] Use an explicit collaborator allowlist.
- [ ] Gate email, mobile, and address through the selected permission/visibility setting.
- [ ] Keep owner/admin representations intentional and shared-membership checks in place.
- [ ] Inventory consumers and document compatibility changes under P3.6.

**Tests and verification**

- Update `tests/Feature/Api/V1/Users/UserTest.php` and resource assertions in `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php`.
- [ ] Verify owner, admin, collaborator, and unrelated-user responses by exact allowed fields.
- [ ] Check nested user representations as well as the direct profile endpoint.

## P1.9 — Sanitize log context and audit metadata

Placement in phase 1 is an implementation-priority choice for the audit's additional logging item.

**Files**

- `app/Logging/ScrubSensitiveData.php:12`.
- `config/logging.php:126`.
- `app/Listeners/PaddleEventListener.php:47`.
- Supporting call sites: `app/Services/Audit/AuditLogService.php` and `app/Providers/AppServiceProvider.php`.

**Implementation checklist — audit requirement**

- [ ] Apply one sanitizer consistently to relevant configured logging channels.
- [ ] Cover `access_token`, `refresh_token`, `Authorization`, and nested request/exception context alongside existing sensitive keys.
- [ ] Avoid retaining the full Paddle webhook payload in audit metadata; define an explicit business/event metadata allowlist.
- [ ] Preserve useful correlation identifiers and non-sensitive diagnostic context.

**Tests to add/update**

- **New (proposed):** `tests/Unit/Logging/ScrubSensitiveDataTest.php`.
- `tests/Feature/Api/Webhooks/Paddle/PaddleWebhookTest.php`.
- `tests/Feature/Exceptions/HandlerReportingTest.php`.
- `tests/Feature/Jobs/GlobalQueueFailingListenerTest.php`.
- [ ] Use recognizable fake secret markers in nested arrays, request headers, and exception context.
- [ ] Assert markers are absent from final formatted log output and persisted audit metadata, while request/event IDs remain usable.
- [ ] Exercise each relevant output channel, including its actual formatter/processor path.

## Phase verification and acceptance

Run from the repository root with the isolated testing environment:

```sh
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/V1/Users
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Middleware/Idempotency
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Middleware/Zoom/VerifyWebhookTest.php
php vendor/bin/phpunit --do-not-cache-result --testdox tests/Feature/Api/Webhooks
composer test
composer stan
composer audit --locked --no-dev
php artisan route:list --path=api -vv
```

Run all new targeted tests too; the commands above are anchors, not a substitute for them.

- [ ] Account mutation boundaries are verified across real token and session paths.
- [ ] Idempotency cannot replay another resource's success or bypass current access checks.
- [ ] Bounded lease/completion behavior is verified deterministically.
- [ ] Downstream webhook failure can be retried; interim limitations are recorded.
- [ ] The dependency gate passes and privacy/logging decisions are implemented.
- [ ] New account-security contract changes are documented.
- [ ] Report changed files, selected alternatives, test results, and open phase 2 dependencies.

Phase 1 completion alone does not authorize reliance on external integrations. Durable operation/inbox recovery and real concurrency evidence remain required in phases 2 and 4.
