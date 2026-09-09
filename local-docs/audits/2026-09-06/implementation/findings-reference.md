# Audit findings reference

Source: [rest-production-audit.md](../rest-production-audit.md). This document separates findings and reproduction evidence from the implementation checklists. Severity and evidence status below belong to the 6 September 2026 audit; this extraction is not a new audit.

## Ranked findings

| ID  | Audit severity                   | Description and consequence                                                                                                      | Audit evidence / reproduction                                                                                                                                                                              | Implementation |
| --- | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------- |
| F1a | Critical                         | Ownership authorization ignores the target account. Account/profile/avatar mutations use that policy.                            | A non-admin real Sanctum token with `team:write` changed an unrelated user's email while the target remained verified. Cross-account mutation was demonstrated; a real account takeover was not performed. | P1.1           |
| F1b | High                             | General profile authority also grants login-email mutation authority.                                                            | A `team:write` token changed its own account's verified login email. This is independent of cross-account ownership.                                                                                       | P1.2           |
| F1c | Part of F1; not separately rated | `withTrashed()` can bind active as well as archived users, despite the controller's archive-first assumption.                    | Code-path finding, conditional on the intended archive-first contract.                                                                                                                                     | P1.3           |
| F2  | High                             | Request identity omits concrete resource identifiers; cached responses can precede current access authorization.                 | Invite the same user to A, then B with the same key/body: both return 201, but only A receives the invitation. Replay authorization ordering was inspected in code.                                        | P1.4           |
| F3  | High                             | Leases can expire while work continues: idempotency 10 seconds, refresh lock 15 seconds, Zoom request timeout up to 30 seconds.  | Advance the test clock 11 seconds inside one request; the same key's callback executes again before the first finishes. This was not a live Redis race test.                                               | P1.5, P2, P4   |
| F4  | High                             | A pre-dispatch webhook reservation survives a downstream failure, causing an unprocessed retry to be acknowledged.               | First dispatch throws queue unavailable; identical retry receives 202 and never reaches dispatch.                                                                                                          | P1.6, P2.1     |
| F5  | High                             | A state transition validates a stale model before an unconditional update.                                                       | Load two Pending task snapshots; cancel one; transition the stale copy to InProgress. A terminal state is reopened. This was a two-snapshot reproduction, not simultaneous database workers.               | P2.5           |
| F6  | High release priority            | Four production dependencies have security advisories, including critical upstream findings.                                     | Historical `composer audit --locked --no-dev` returned seven advisories. Reachable exploitation in DayWright was not established.                                                                          | P1.7           |
| F7a | High                             | Zoom may succeed before the local provider identifier is saved; a timeout can be misclassified as definite failure.              | Code-path review; no live provider crash was induced. No pending/failed creation reconciler was found in that review.                                                                                      | P2.2           |
| F7b | High                             | Paddle remote effects execute inside a locked database transaction configured for five attempts.                                 | Code-path review: external work cannot be rolled back with database work, and a database retry can repeat it.                                                                                              | P2.3           |
| F7c | High                             | A committed message claim can survive a crash before dispatch; batch failure cleanup sits in the successful-completion callback. | Code-path review of claim persistence, `batch_id` eligibility, and then/catch callbacks; live process termination was not induced.                                                                         | P2.4           |
| F8  | Medium                           | PUT uses partial-update validators; unchanged fields and setter states can be rejected.                                          | A notes-only PUT preserves omitted fields; repeating it returns 422. Different response codes alone do not establish non-idempotent effects.                                                               | P3.1           |
| F9  | Medium                           | HTTP exception formatting discards protocol headers.                                                                             | A method-not-allowed response loses `Allow`; an idempotency-style conflict loses `Retry-After`.                                                                                                            | P3.2           |
| F10 | Medium                           | Pagination links lose the selected filters, sorting, and page size.                                                              | `GET /projects?filter[search]=Audit&sort=name&per_page=1` yields a next link containing only `page=2`.                                                                                                     | P3.3           |

## Original defective policy excerpt

Verbatim audit excerpt; retained here as finding evidence, not implementation guidance:

```php
public function owner(User $user): bool
{
    return $user->is(auth()->user());
}
```

## Additional findings

| ID  | Audit severity                    | Description                                                                                                                         | Evidence status                                                                                                                      | Implementation |
| --- | --------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | -------------- |
| A1  | Medium                            | Subscription singleton registers form routes without controller methods.                                                            | Both create/edit GET requests returned 500 in probes.                                                                                | P3.4           |
| A2  | Medium                            | Storage failures and broad password-update exceptions become validation 422 responses.                                              | Code-path review.                                                                                                                    | P3.5           |
| A3  | Medium                            | Authentication-boundary middleware emits empty arrays for errors/meta while the central formatter emits objects.                    | Response construction inspected.                                                                                                     | P3.2           |
| A4  | Medium; privacy decision required | Shared-project membership exposes email, mobile, and address.                                                                       | Membership checks exist; the appropriate product visibility policy remains undecided. No separate access-control bypass was claimed. | P1.8           |
| A5  | Medium                            | Secret scrubbing misses some keys, exception/request context and channels; Paddle audit metadata includes the full webhook payload. | Code-path review. No secret leak was demonstrated.                                                                                   | P1.9           |
| A6  | Low design priority               | assign/unassign, bulk-delete, and fetch-user names remain action-oriented.                                                          | Route inspection; changes are optional and compatibility-sensitive.                                                                  | P3.7           |

## Operational evidence absent from the source audit

The audit did not verify a deployed server, production secrets, restored backups, provider failover, sustained load, or delivered operational alerts. These are verification gaps, not claims that every corresponding capability is absent.

Its existing suites passed 1,007 tests and 9,373 assertions using PHP 8.3.29, SQLite in memory, array-backed test facilities, synchronous queues, and integration doubles. There were 522 PHPUnit deprecations. PHPStan passed configured level 6 with the existing baseline/exclusions. None of those historical results substitutes for phase 4 or phase 5 acceptance.

## Historical dependency evidence

| Package                    | Audit lock version | Audit characterization                                                                                            |
| -------------------------- | ------------------ | ----------------------------------------------------------------------------------------------------------------- |
| `mtdowling/jmespath.php`   | 2.8.0              | Critical compiler code-injection advisory; attacker-controlled expressions must reach the relevant compiler path. |
| `paragonie/sodium_compat`  | 2.5.0              | Ed25519 public-key validation advisory.                                                                           |
| `phpoffice/phpspreadsheet` | 1.30.4             | Four advisories, including a critical patch bypass.                                                               |
| `phpseclib/phpseclib`      | 3.0.52             | Certificate-validation SSRF advisory.                                                                             |

Consult the original report for its maintainer advisory links. Implementation must use a fresh dependency audit.

## Probe mapping

All references below point into [DaywrightRestAuditProbeTest.php](../DaywrightRestAuditProbeTest.php). They assert the old defect, so a passing forensic probe must not be accepted as proof of a repair.

| Probe method                                                                 | Audit line | Finding |
| ---------------------------------------------------------------------------- | ---------: | ------- |
| `test_team_write_token_can_replace_verified_login_email`                     |         25 | F1b     |
| `test_replay_cache_suppresses_retry_after_downstream_failure`                |         34 | F4      |
| `test_same_key_on_different_projects_replays_first_invitation`               |         56 | F2      |
| `test_stale_task_snapshot_overwrites_terminal_state`                         |         72 | F5      |
| `test_idempotency_lock_expires_while_first_request_still_running`            |         83 | F3      |
| `test_put_preserves_omitted_fields_and_rejects_identical_retry`              |        100 | F8      |
| `test_project_next_link_drops_active_query_parameters`                       |        113 | F10     |
| `test_http_exception_formatting_drops_protocol_headers`                      |        126 | F9      |
| `test_subscription_form_routes_call_missing_methods`                         |        138 | A1      |
| `test_owner_policy_allows_team_write_token_to_change_another_accounts_email` |        146 | F1a     |

## Source references by finding

These are the original audit's code references, not newly measured positions.

- F1: `app/Policies/UsersPolicy.php:32`; `app/Http/Controllers/Api/V1/User/UserController.php:46` and `:63`; `app/Http/Controllers/Api/V1/User/ForceDeleteUserController.php:24` and `:26`; `app/Http/Controllers/Api/V1/User/AvatarController.php:32`; `routes/api/v1/users.php:34`; `app/Http/Requests/Api/V1/User/UserRequest.php:50`; `app/Services/User/UserService.php:36`; `app/DataTransferObjects/User/UpdateUserData.php:25`.
- F2: `vendor/wendelladriel/laravel-idempotency/src/Support/RequestFingerprint.php:12` and `:34`; `routes/api/v1/projects/invitations.php:13`; `routes/api/v1/projects/meetings.php:16`.
- F3: `vendor/wendelladriel/laravel-idempotency/src/Http/Middleware/Idempotent.php:93`; `app/Services/Zoom/ZoomConnectorManager.php:31`; `app/Http/Integrations/Zoom/ZoomConnector.php:32`.
- F4: `app/Http/Middleware/VerifyZoomWebhook.php:59` and `:175`; `app/Services/Webhooks/ZoomWebhookDispatcher.php:18`.
- F5: `app/Traits/HasStateMachine.php:26`; `app/Services/Task/TaskService.php:85`; `app/Services/Project/ProjectService.php:114`.
- F6: `composer.lock:5389`, `:6182`, `:6352`, and `:6535`; `.github/workflows/tests.yml:49`.
- F7: `app/Actions/Meetings/CreateProjectMeeting.php:40` and `:61`; `app/Services/Paddle/SubscriptionService.php:175`; `app/Actions/Project/DispatchProjectMessageAction.php:24`, `:84`, and `:111`; `app/Models/Message.php:51`.
- F8: `routes/api/v1.php:26`; `routes/api/v1/projects/tasks.php:15`; `app/Http/Requests/Api/V1/Project/ProjectUpdateRequest.php:44`; `app/Http/Requests/Api/V1/Task/TaskUpdateRequest.php:51`; `app/Traits/HasStateMachine.php:31`.
- F9: `app/Exceptions/Traits/HandlesApiExceptions.php:70` and `:92`.
- F10: `app/Services/Dashboard/UserProjectListingService.php:51`; `app/Services/Project/MeetingService.php:40`; `app/Repository/Admin/UserRepository.php:33`.
- A1: `routes/api/v1/users.php:27`.
- A2: `app/Services/FileService.php:117`; `app/Services/User/UserService.php:54`.
- A3: `app/Http/Middleware/RequireSessionAuth.php:45`; `app/Http/Middleware/RequireFirstPartyAuth.php:44`; `app/Exceptions/Support/ApiErrorFormatter.php:24`.
- A4: `app/Http/Resources/Api/V1/User/PublicUserProfileResource.php:78`; `app/Http/Resources/Api/V1/User/UserInfoResource.php:32`.
- A5: `app/Logging/ScrubSensitiveData.php:12`; `config/logging.php:126`; `app/Listeners/PaddleEventListener.php:47`.
- A6: `routes/api/v1/projects/tasks.php:25`; `routes/api/admin/v1.php:51`; `routes/web/v1.php:49`.
- Roadmap: `phpunit.xml:37`; `.github/workflows/tests.yml:49`; `docs/DEPLOYMENT.md:22` and `:408`.
