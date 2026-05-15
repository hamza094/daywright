# Test Suite Consistency Plan

Created: 2026-05-14

This plan standardizes test naming, placement, and boundaries across the backend test suite.
It is intentionally conservative: keep real coverage, remove only dead or accidental files, and move tests into clearer homes before deleting anything that still owns behavior.

Work through the phases in order.
Do not delete a test that still owns unique behavior until that behavior has been moved or absorbed elsewhere.

## Implementation Status

- Phase 1 is implemented by updating the backend guidelines to codify the test boundary, naming, and placement rules.
- Phase 2 is implemented: the accidental `Feature/Feature` stub path and `test_example` placeholder no longer exist in the suite.
- Phase 3 is implemented for the top-level contract and webhook homes used today:
  - `RouteVersionRegistrationTest` now lives under `tests/Feature/Api/Contracts/Routes/`
  - `ScrambleDocsTest` now lives under `tests/Feature/Api/Contracts/Docs/`
  - `ZoomWebhookTest` now lives under `tests/Feature/Api/Webhooks/Zoom/`
- Phase 4 is implemented:
  - Zoom endpoint tests now live under `tests/Feature/Api/V1/Meetings/` and `tests/Feature/Api/V1/Users/`
  - Paddle subscription endpoint tests now live under `tests/Feature/Api/V1/Subscriptions/`
  - Zoom OAuth tests now live under `tests/Feature/Api/Auth/Zoom/`
  - Direct action and service tests now live under `tests/Unit/Actions/**` and `tests/Unit/Services/**`
  - `tests/Feature/Api/Controllers/` and `tests/Feature/Api/Services/` no longer contain permanent tests
- Phase 5 is implemented:
  - `tests/Feature/Api/V1/ProjectDashBoard/` is now `tests/Feature/Api/V1/Dashboard/`
  - Dashboard test file names now match their actual subjects and namespaces
  - Phase-labeled idempotency and database test files now use behavior-based names
  - `ProjectInsightResponseTest` now lives under `tests/Unit/Services/Insights/`
- Phase 6 is implemented:
  - Meeting, notification, message, conversation, project, and task tests now live in their own V1 domain folders
  - User profile, avatar, invitation, token, and Zoom token feature tests now live under `tests/Feature/Api/V1/Users/`
  - `UserProjectsPageTest` now lives as `ProjectIndexTest` under `tests/Feature/Api/V1/Projects/`
  - `MeetingTest` now lives as `MeetingReadTest` under `tests/Feature/Api/V1/Meetings/`
  - Notification inbox and delivery coverage now lives under `tests/Feature/Api/V1/Notifications/`
  - `TaskFeaturesTest` has been split into narrower task member-management and lifecycle files under `tests/Feature/Api/V1/Tasks/`
- Phase 7 is implemented:
  - Meeting endpoint tests remain separate from `MeetingServiceTest` and the Zoom service tests because they cover HTTP behavior, persistence, rollback, and response contracts versus service-level orchestration and Zoom request construction
  - Notification delivery and inbox coverage remain separate because one owns notification side effects and the other owns inbox listing, filtering, status updates, and deletion
  - Feature flag coverage remains split between the API endpoint test and the resource unit test because one covers route access and `users.me` payload behavior while the other covers resource serialization and Pennant state mapping
- Phase 8 is implemented:
  - Shared admin two-factor setup now lives in `tests/Traits/EnablesUserTwoFactor.php`
  - Shared invitation and membership helpers now live in `tests/Traits/ProjectInvitationHelpers.php`
  - Shared Zoom test user creation now lives in `tests/Traits/CreatesZoomUsers.php`
  - Touched files now use the shared helpers instead of repeating local setup methods
  - `IdempotentRoutesRegistrationTest` keeps route wiring and missing-header coverage, while replay semantics remain covered in `IdempotencyContractTest`
  - Final review did not identify any additional permanent test files that were safe to delete without dropping owned behavior

## Scope

In scope:

- `tests/Feature`
- `tests/Unit`
- `tests/TestCase.php`
- `tests/Traits`
- `tests/Support`
- `tests/Helpers`

Out of scope:

- Rewriting assertions only for style
- Adding broad new coverage unrelated to this cleanup
- Frontend or browser testing strategy
- Renaming production code just to match test names

## Goal

- Make the test suite easy to skim.
- Use one clear boundary model for feature, unit, contract, middleware, webhook, database, and support tests.
- Make test file names follow the backend convention.
- Move test files and test classes into their related boundaries.
- Remove obvious AI-generated stubs and phase-labeled leftovers without losing useful coverage.

## Target Conventions

### Naming

- Default file name pattern: `{Subject}Test.php`.
- Keep `{Feature}FeatureTest.php` only for canonical broad acceptance files that intentionally own one feature boundary, such as `ProjectFeatureTest.php`.
- Class names must match file names.
- Do not introduce `*Tests.php`, `Phase*Test.php`, placeholder names like `test_example`, or misspelled names.
- When a file covers one workflow inside a larger subject, include the subject in the file name. Examples: `MeetingCreateTest.php`, `DashboardChartDataTest.php`, `ZoomAuthorizationTest.php`.

### Placement

- Feature tests are organized by external behavior first, not by implementation type.
- Versioned endpoint tests live under `tests/Feature/Api/V1/{Domain}/`.
- Auth, session, and OAuth flows live under `tests/Feature/Api/Auth/`.
- API contract tests live under `tests/Feature/Api/Contracts/{Concern}/`.
- Middleware tests live under `tests/Feature/Api/Middleware/{Concern}/`.
- Webhook tests live under `tests/Feature/Api/Webhooks/{Provider}/`.
- Database constraint tests live under `tests/Feature/Database/`.
- Unit tests live under `tests/Unit/{Layer or Domain}/`.
- Do not keep permanent `tests/Feature/Api/Controllers/` or `tests/Feature/Api/Services/` buckets.

### Boundary Rules

- Feature test: makes HTTP requests, asserts responses, auth, authorization, middleware, route contracts, webhook behavior, or persistence side effects through the public API.
- Unit test: instantiates an action, service, repository, resource, model, enum, DTO, or helper directly.
- Shared setup: put reused setup helpers in `tests/TestCase.php`, `tests/Traits/`, or `tests/Support/`.

### Keep, Rename, Move, Delete Rules

- Move before splitting when a file is useful but misplaced.
- Rename before deleting when the problem is naming only.
- Delete immediately only when a file is obviously dead, accidental, or placeholder.
- Split broad files only after the destination domain folders exist.
- If two files cover different boundaries, keep both even if the subject looks similar.

## Phase 1 - Freeze The Test Rules

Goal:

- Stop new test drift before moving existing files.

Tasks:

- [x] Treat the target conventions in this file as the default for all new backend tests.
- [x] Stop creating new tests under `tests/Feature/Api/Controllers`.
- [x] Stop creating new tests under `tests/Feature/Api/Services`.
- [x] Stop creating `Phase*Test.php`, `*Tests.php`, and misspelled test file names.
- [x] Move repeated setup helpers into shared traits or `tests/TestCase.php` instead of copying them across files.

Review first:

- `tests/TestCase.php`
- `tests/Traits/ProjectSetup.php`
- `tests/Traits/AuthenticatedProjectHelpers.php`

Exit criteria:

- New test files no longer add structural drift while the old tree is being cleaned up.

## Phase 2 - Remove Dead Files And Accidental Structure

Goal:

- Delete the obviously unnecessary files and duplicate folder structure without touching real coverage.

Tasks:

- [x] Delete `tests/Feature/Feature/Api/Middleware/Idempotency/IdempotentMiddlewareTest.php`.
- [x] Confirm there are no other default framework stubs or duplicate root folders.
- [x] Keep real contract tests even if they will move later.

Safe delete list:

- `tests/Feature/Feature/Api/Middleware/Idempotency/IdempotentMiddlewareTest.php`

Exit criteria:

- No accidental `Feature/Feature` path remains.
- No placeholder `test_example` file remains in the suite.

## Phase 3 - Normalize Top-Level Test Boundaries

Goal:

- Make the top-level tree reflect product behavior instead of implementation buckets.

Tasks:

- [x] Create and use these permanent homes:
- [x] `tests/Feature/Api/Contracts/Routes/`
- [x] `tests/Feature/Api/Contracts/Docs/`
- [x] `tests/Feature/Api/Webhooks/{Provider}/`
- [x] `tests/Feature/Api/Middleware/{Concern}/`
- [x] `tests/Feature/Api/V1/{Domain}/`
- [x] `tests/Unit/{Layer or Domain}/`
- [x] Move `RouteVersionRegistrationTest` into `tests/Feature/Api/Contracts/Routes/`.
- [x] Move `ScrambleDocsTest` into `tests/Feature/Api/Contracts/Docs/`.
- [x] Keep database constraint tests under `tests/Feature/Database/`.
- [x] Keep exception handler reporting tests under `tests/Feature/Exceptions/`.

Review first:

- `tests/Feature/Api/RouteVersionRegistrationTest.php`
- `tests/Feature/Api/ScrambleDocsTest.php`
- `tests/Feature/Database/PhaseThreeUniquenessTest.php`
- `tests/Feature/Exceptions/HandlerReportingTest.php`

Exit criteria:

- The top-level test tree tells you whether a test is an HTTP feature, contract, middleware, webhook, database, or unit test at a glance.

## Phase 4 - Move Implementation Tests Into Real Boundaries

Goal:

- Remove implementation-oriented `Controllers` and `Services` folders from feature tests.

Tasks:

- [x] Move Zoom endpoint tests from `tests/Feature/Api/Controllers/Zoom/` into `tests/Feature/Api/V1/Meetings/` or `tests/Feature/Api/V1/Users/` based on the actual route.
- [x] Move Paddle subscription endpoint tests from `tests/Feature/Api/Controllers/Paddle/` into `tests/Feature/Api/V1/Subscriptions/`.
- [x] Move Zoom OAuth controller tests from `tests/Feature/Api/Controllers/OAuth/ZoomController/` into `tests/Feature/Api/Auth/Zoom/`.
- [x] Move webhook controller tests from `tests/Feature/Api/Controllers/Webhooks/` into `tests/Feature/Api/Webhooks/`.
- [x] Move direct service tests from `tests/Feature/Api/Services/**` into `tests/Unit/Services/**`.

Suggested destination moves:

- `tests/Feature/Api/Controllers/Zoom/GetZakTokenTest.php` -> `tests/Feature/Api/V1/Users/UserZoomTokenTest.php`
- `tests/Feature/Api/Controllers/Zoom/StoreMeetingTest.php` -> `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`
- `tests/Feature/Api/Controllers/Zoom/UpdateMeetingTest.php` -> `tests/Feature/Api/V1/Meetings/MeetingUpdateTest.php`
- `tests/Feature/Api/Controllers/Zoom/DeleteMeetingTest.php` -> `tests/Feature/Api/V1/Meetings/MeetingDeleteTest.php`
- `tests/Feature/Api/Controllers/Paddle/SubscriptionControllerTest.php` -> `tests/Feature/Api/V1/Subscriptions/SubscriptionManagementTest.php`
- `tests/Feature/Api/Controllers/OAuth/ZoomController/RedirectTest.php` -> `tests/Feature/Api/Auth/Zoom/ZoomOAuthRedirectTest.php`
- `tests/Feature/Api/Controllers/OAuth/ZoomController/CallbackTest.php` -> `tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php`
- `tests/Feature/Api/Services/Paddle/SubscriptionServiceTest.php` -> `tests/Unit/Services/Paddle/SubscriptionServiceTest.php`
- `tests/Feature/Api/Services/Zoom/ZoomService/*.php` -> `tests/Unit/Services/Zoom/*.php`

Review first:

- `tests/Feature/Api/Controllers/Zoom/*.php`
- `tests/Feature/Api/Controllers/Paddle/SubscriptionControllerTest.php`
- `tests/Feature/Api/Controllers/OAuth/ZoomController/*.php`
- `tests/Feature/Api/Controllers/Webhooks/*.php`
- `tests/Feature/Api/Services/**/*.php`

Exit criteria:

- No permanent test remains under `tests/Feature/Api/Controllers`.
- No direct service test remains under `tests/Feature/Api/Services`.

## Phase 5 - Normalize Domain Folders And File Names

Goal:

- Make names match the convention and the actual subject under test.

Tasks:

- [x] Rename `ProjectDashBoard` directory to `Dashboard`.
- [x] Rename files whose names do not describe the actual subject.
- [x] Rename phase-labeled files to behavior-labeled names.
- [x] Rename misleading unit files that no longer match the class or subject they exercise.

Primary rename candidates:

- `tests/Feature/Api/V1/ProjectDashBoard/UserKpiMetricesTest.php` -> `tests/Feature/Api/V1/Dashboard/DashboardInsightsTest.php`
- `tests/Feature/Api/V1/ProjectDashBoard/ProjectChartTests.php` -> `tests/Feature/Api/V1/Dashboard/DashboardChartDataTest.php`
- `tests/Feature/Database/PhaseThreeUniquenessTest.php` -> `tests/Feature/Database/DatabaseUniquenessConstraintsTest.php`
- `tests/Feature/Api/Middleware/Idempotency/PhaseOneIdempotentRoutesTest.php` -> `tests/Feature/Api/Middleware/Idempotency/IdempotentRoutesRegistrationTest.php`
- `tests/Feature/Api/Middleware/Idempotency/PhaseSevenIdempotencyContractTest.php` -> `tests/Feature/Api/Middleware/Idempotency/IdempotencyContractTest.php`
- `tests/Unit/ProjectInsightResponseServiceTest.php` -> `tests/Unit/Services/Insights/ProjectInsightResponseTest.php`

Exit criteria:

- No phase names, plural test names, placeholder names, or misspellings remain in permanent file names.
- Directory names and namespaces match each other.

## Phase 6 - Split Mixed Catch-All Files By Domain Behavior

Goal:

- Make each file easy to skim by giving it one clear subject.

Tasks:

- [x] Split broad V1 root files into domain folders once destination folders exist.
- [x] Keep only intentional broad acceptance files like `ProjectFeatureTest.php` where the file still represents one clear feature boundary.
- [x] Move files into the domain they actually exercise instead of the folder they happened to be created in.
- [x] Put notifications, meetings, messages, conversations, projects, tasks, and dashboard tests into their own domain folders.

Recommended moves and splits:

- `tests/Feature/Api/V1/Dashboard/UserProjectsPageTest.php` -> `tests/Feature/Api/V1/Projects/ProjectIndexTest.php`
- `tests/Feature/Api/V1/MeetingTest.php` -> `tests/Feature/Api/V1/Meetings/MeetingReadTest.php` or split into `MeetingIndexTest.php` and `MeetingShowTest.php`
- `tests/Feature/Api/V1/NotificationsTest.php` -> `tests/Feature/Api/V1/Notifications/NotificationDeliveryTest.php`
- `tests/Feature/Api/V1/UserNotificationsTest.php` -> `tests/Feature/Api/V1/Notifications/UserNotificationInboxTest.php`
- `tests/Feature/Api/V1/MessageTest.php` and `tests/Feature/Api/V1/MessageValidationTest.php` -> `tests/Feature/Api/V1/Messages/`
- `tests/Feature/Api/V1/ConversationTest.php` -> `tests/Feature/Api/V1/Conversations/ConversationTest.php`
- `tests/Feature/Api/V1/TaskFeaturesTest.php` -> `tests/Feature/Api/V1/Tasks/TaskMemberManagementTest.php` and `tests/Feature/Api/V1/Tasks/TaskLifecycleTest.php`
- Keep `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php` as the canonical broad project acceptance file

Review first:

- `tests/Feature/Api/V1/Projects/ProjectFeatureTest.php`
- `tests/Feature/Api/V1/Tasks/TaskMemberManagementTest.php`
- `tests/Feature/Api/V1/Tasks/TaskLifecycleTest.php`
- `tests/Feature/Api/V1/Meetings/MeetingReadTest.php`
- `tests/Feature/Api/V1/Notifications/NotificationDeliveryTest.php`
- `tests/Feature/Api/V1/Notifications/UserNotificationInboxTest.php`
- `tests/Feature/Api/V1/Messages/MessageTest.php`
- `tests/Feature/Api/V1/Conversations/ConversationTest.php`

Exit criteria:

- Each domain folder contains the tests for that domain’s externally visible behavior.
- Large root-level V1 files are reduced or intentionally kept.

## Phase 7 - Merge Or Delete Redundant Coverage Safely

Goal:

- Remove only the files that became redundant after moves and splits.

Tasks:

- [x] Split `PhaseFourActionSafetyTest` into its owning domains, then delete the original file.
- [x] Reassess meeting coverage after all Zoom and meeting moves; keep both unit service coverage and endpoint coverage only when they test different boundaries.
- [x] Reassess notification coverage after moving both notification files into the same folder; keep both only if one owns delivery side effects and the other owns inbox CRUD and filtering.
- [x] Reassess feature flag coverage after moving and renaming; keep the unit resource test and the feature endpoint test because they cover different boundaries.

Keep as separate coverage unless a true duplicate is proven:

- `tests/Feature/Api/V1/Meetings/MeetingCreateTest.php`, `MeetingUpdateTest.php`, `MeetingDeleteTest.php`, and `MeetingReadTest.php` versus `tests/Unit/Services/Project/MeetingServiceTest.php` and `tests/Unit/Services/Zoom/ZoomMeeting*.php`
- `tests/Feature/Api/V1/Notifications/NotificationDeliveryTest.php` and `tests/Feature/Api/V1/Notifications/UserNotificationInboxTest.php`
- `tests/Feature/Api/V1/FeatureFlagsTest.php` and `tests/Unit/FeatureFlagsResourceTest.php`
- `tests/Feature/Api/Contracts/Routes/RouteVersionRegistrationTest.php` and `tests/Feature/Api/Contracts/Docs/ScrambleDocsTest.php`

Exit criteria:

- No real behavior loses coverage.
- Any deleted file is either dead, absorbed, or duplicated by a clearer surviving file.

## Phase 8 - Extract Shared Test Support And Final Sweep

Goal:

- Remove repeated setup noise and lock the new structure in place.

Tasks:

- [x] Extract shared admin two-factor setup into a trait or shared helper used by the admin test files.
- [x] Extract shared invitation and membership helpers from notification-related tests.
- [x] Extract shared Zoom test user creation helpers from Zoom service tests.
- [x] Update namespaces to match moved folders.
- [x] Run focused tests after each move batch and keep one final sweep for changed directories.

Shared helper duplication to remove:

- Repeated `enableTwoFactorForUser()` across the admin test files
- Repeated `sendInvitationToUser()` and `addMember()` in notification-related tests
- Repeated `userCreate()` in Zoom service tests

Exit criteria:

- Common setup lives in one obvious place.
- The tree is skimmable without repeated boilerplate in every file.

## Recommended Execution Order

1. Phase 2
2. Phase 3
3. Phase 4
4. Phase 5
5. Phase 6
6. Phase 7
7. Phase 8

## Validation Guidance

- Run the smallest relevant PHPUnit slice after each move, rename, split, or deletion.
- Prefer file-scoped runs while the tree is in motion.
- After each phase completes, run one focused suite for the touched directories.
- Use `php artisan test --compact` with specific paths or filters.

## High-Confidence Immediate Actions

- Delete the accidental stub in `tests/Feature/Feature/Api/Middleware/Idempotency/IdempotentMiddlewareTest.php`.
- Move contract tests out of the top-level `tests/Feature/Api/` root.
- Move all direct service tests out of `tests/Feature/Api/Services/`.
- Rename `ProjectDashBoard` and fix its misspelled file names.
- Split and delete `PhaseFourActionSafetyTest.php` only after its behavior has been absorbed into the right homes.
