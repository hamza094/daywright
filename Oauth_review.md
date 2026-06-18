# Zoom OAuth Refactor Plan

This plan turns the current Zoom OAuth review into a practical refactor path.
The goal is to improve maintainability and future reuse without introducing a large generic integration framework.

## Core Decision

Keep provider API clients provider-specific.
Extract only the OAuth lifecycle pieces that are actually shared.

That means:

- Zoom requests, connector configuration, and Zoom business operations stay in the Zoom area.
- OAuth state handling, token persistence, and refresh orchestration become reusable.
- Webhooks stay separate from OAuth.

## What To Optimize For

- Reduce coupling between Zoom and [app/Models/User.php](app/Models/User.php).
- Split OAuth mechanics from Zoom meeting business logic.
- Preserve the current `Zoom` contract so feature tests stay stable.
- Make the next provider easier to add without building abstractions that only Zoom uses today.

## What Not To Build

- Do not build one universal third-party service.
- Do not try to make one Saloon connector serve multiple providers.
- Do not merge OAuth and webhook infrastructure into one system.
- Do not introduce an abstract factory layer unless a second provider actually needs it.

## Recommended Target Architecture

### Reusable OAuth Infrastructure

These parts are worth making provider-agnostic now:

- `AuthorizationStateStore`
- `OAuthConnectionRepository`
- `OAuthTokens` DTO
- `AuthorizationRedirect` DTO
- `AuthorizationCallback` DTO

These parts should stay simple for now and only become shared if a second provider needs them:

- Token refresh orchestration
- Authenticated connector construction

The current Zoom refresh logic is strong enough to keep, but it should depend on a provider-neutral connection store rather than on user columns.

### Zoom-Specific Layer

Keep these Zoom-owned:

- `ZoomConnector`
- Zoom Saloon requests
- Zoom auth URL construction
- Zoom code exchange
- Zoom token refresh call
- Zoom meeting operations
- Zoom ZAK token retrieval

## Exact Target Folder Structure

This is the recommended structure with the least extra abstraction:

```text
app/
├── DataTransferObjects/
│   ├── OAuth/
│   │   ├── AuthorizationCallback.php
│   │   ├── AuthorizationRedirect.php
│   │   └── OAuthTokens.php
│   └── Zoom/
│       ├── Meeting.php
│       └── MeetingOperationResult.php
├── Models/
│   └── OAuthConnection.php
├── Repository/
│   └── OAuthConnectionRepository.php
├── Services/
│   ├── OAuth/
│   │   └── AuthorizationStateStore.php
│   └── Zoom/
│       ├── ZoomOAuthService.php
│       ├── ZoomConnectionManager.php
│       ├── ZoomService.php
│       └── ZoomServiceFake.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── OAuth/
│   │           └── ZoomAuthController.php
│   └── Integrations/
│       └── Zoom/
│           ├── ZoomConnector.php
│           └── Requests/
└── Interfaces/
    └── Zoom.php
```

## Responsibility Split

### `ZoomOAuthService`

Own only OAuth provider behavior for Zoom:

- build authorization redirect details
- exchange authorization code for tokens
- refresh tokens
- create an authenticated Zoom connector from stored tokens

### `ZoomConnectionManager`

Own only runtime token lifecycle and concurrency handling:

- load the user connection
- detect expiry
- acquire refresh lock
- re-check inside lock
- refresh when needed
- persist rotated tokens
- clear invalid credentials when refresh is rejected

### `ZoomService`

Own only Zoom business capabilities:

- create meeting
- update meeting
- delete meeting
- get ZAK token

`ZoomService` should stop knowing how authorization URLs are built or how auth codes are exchanged.

### `AuthorizationStateStore`

Own only short-lived OAuth browser flow state:

- store PKCE verifier by state
- atomically consume verifier on callback
- prevent replay

This is a clean reusable unit already.

### `OAuthConnectionRepository`

Own only persisted OAuth credentials:

- find connection for a user and provider
- save tokens for a user and provider
- clear tokens for a user and provider

Do not put refresh logic here.
Do not put provider HTTP calls here.

## Map Current Files To Future Responsibilities

### [app/Services/Zoom/ZoomService.php](app/Services/Zoom/ZoomService.php)

Future role:

- keep meeting operations
- keep ZAK token retrieval
- remove `getAuthRedirectDetails()`
- remove `authorize()`

### [app/Services/Zoom/ZoomConnectorManager.php](app/Services/Zoom/ZoomConnectorManager.php)

Future role:

- keep refresh locking behavior
- keep stale reload before refresh
- stop reading and writing Zoom tokens directly on `User`
- depend on `OAuthConnectionRepository`

Recommended rename later:

- `ZoomConnectionManager`

That name better reflects what it does.

### [app/Services/Zoom/ZoomAuthorizationStateStore.php](app/Services/Zoom/ZoomAuthorizationStateStore.php)

Future role:

- move to shared OAuth namespace
- keep behavior almost unchanged

Recommended rename later:

- `AuthorizationStateStore`

### [app/Http/Controllers/Api/OAuth/ZoomAuthController.php](app/Http/Controllers/Api/OAuth/ZoomAuthController.php)

Future role:

- call `ZoomOAuthService` for redirect and callback exchange
- call `OAuthConnectionRepository` to persist credentials
- stop writing Zoom token columns on the user directly

### [app/Http/Integrations/Zoom/ZoomConnector.php](app/Http/Integrations/Zoom/ZoomConnector.php)

Future role:

- no major change
- remains Zoom-specific
- continues to own Zoom transport behavior and exception mapping

### [app/Models/User.php](app/Models/User.php)

Future role:

- remove Zoom token columns from the long-term design
- remove `updateZoomOAuthDetails()`
- remove `clearZoomOAuthDetails()`
- remove `isConnectedToZoom()`
- optionally add a relationship to `OAuthConnection`

This is the main coupling point to unwind.

### [app/Interfaces/Zoom.php](app/Interfaces/Zoom.php)

Future role:

- keep as the business-facing Zoom contract for now
- do not force OAuth methods and meeting methods to split immediately if that creates churn

Pragmatic rule:

- keep the existing contract stable first
- split contract surface only when the implementation split is complete

### [app/Services/Zoom/ZoomServiceFake.php](app/Services/Zoom/ZoomServiceFake.php)

Future role:

- stay aligned with the existing `Zoom` contract
- keep feature tests stable while internals move behind it

## Recommended Persistence Model

Create a dedicated `oauth_connections` table.

Suggested fields:

- `id`
- `user_id`
- `provider`
- `access_token`
- `refresh_token`
- `expires_at`
- `external_user_id` nullable
- `scopes` nullable
- `metadata` nullable
- timestamps

Recommended constraints:

- unique index on `user_id + provider`
- encrypted casts for token columns
- `expires_at` as datetime

Recommended model shape:

- `OAuthConnection` belongs to `User`
- `User` has many `OAuthConnection`

## Migration Path From User Columns To `oauth_connections`

Do this in small phases.
Do not move schema and runtime behavior in one step.

### Phase 1: Extract The Persistence Boundary Without Schema Change

Goal:

- stop controllers and services from calling Zoom-specific user helpers directly

Changes:

- add a small `OAuthConnectionRepository` that still reads and writes the current Zoom columns on `User`
- update `ZoomAuthController` and `ZoomConnectorManager` to use that repository
- keep `User` columns unchanged
- keep response contracts unchanged

Why first:

- this gives you a seam immediately
- it is low-risk
- current tests should mostly stay intact

### Phase 2: Split OAuth Logic Out Of `ZoomService`

Goal:

- make `ZoomService` business-only

Changes:

- create `ZoomOAuthService`
- move redirect URL generation there
- move authorization code exchange there
- keep `ZoomService` focused on meetings and tokens used by business flows
- keep the `Zoom` interface stable until the move is complete

Why second:

- it separates concerns without changing storage yet
- it makes later provider reuse more obvious

### Phase 3: Introduce `oauth_connections` Table

Goal:

- remove Zoom token persistence from `User`

Changes:

- add `OAuthConnection` model and migration
- update `OAuthConnectionRepository` to read and write the new table
- backfill existing Zoom user token rows into `oauth_connections`
- keep a temporary compatibility fallback during rollout if needed

Recommended rollout shape:

- first deploy schema
- then deploy repository dual-read logic if zero-downtime matters
- then backfill
- then switch writes fully to the new table
- then remove old reads

### Phase 4: Remove Zoom-Specific User Persistence API

Goal:

- finish the decoupling

Changes:

- remove Zoom token casts from `User`
- remove hidden Zoom token attributes from `User`
- remove `updateZoomOAuthDetails()`
- remove `clearZoomOAuthDetails()`
- remove `isConnectedToZoom()`
- drop obsolete columns in a final cleanup migration

This should happen only after repository-backed reads and writes are proven.

## Smallest Safe First Refactor

If you want the first change with the best maintenance payoff and the least breakage risk, do this:

1. Introduce `OAuthConnectionRepository`.
2. Keep it backed by the current Zoom columns on `User`.
3. Update [app/Http/Controllers/Api/OAuth/ZoomAuthController.php](app/Http/Controllers/Api/OAuth/ZoomAuthController.php) to save tokens through the repository.
4. Update [app/Services/Zoom/ZoomConnectorManager.php](app/Services/Zoom/ZoomConnectorManager.php) to load, save, and clear tokens through the repository.
5. Leave [app/Models/User.php](app/Models/User.php) unchanged for that step.

Why this is the best first move:

- it removes the direct dependency on Zoom token columns from the main runtime paths
- it does not require a schema migration yet
- it creates the seam needed for the later table move
- it should preserve almost all current tests

## Naming Guidance

There is already an [app/Enums/OAuthProvider.php](app/Enums/OAuthProvider.php) used for social login providers.

Do not reuse that enum for Zoom API account connections.

Reason:

- social login providers and connected external service accounts are different concepts in this codebase

Practical recommendation:

- keep `provider` as a string in `oauth_connections` initially
- introduce a dedicated enum later only when a second service integration actually exists

## Test Strategy By Phase

Keep the existing test seam through [app/Interfaces/Zoom.php](app/Interfaces/Zoom.php) and [app/Services/Zoom/ZoomServiceFake.php](app/Services/Zoom/ZoomServiceFake.php).

Tests that should protect the refactor first:

- [tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php](tests/Feature/Api/Auth/Zoom/ZoomOAuthCallbackTest.php)
- [tests/Feature/Api/Auth/Zoom/ZoomOAuthRedirectTest.php](tests/Feature/Api/Auth/Zoom/ZoomOAuthRedirectTest.php)
- [tests/Unit/Services/Zoom/ZoomAuthorizationTest.php](tests/Unit/Services/Zoom/ZoomAuthorizationTest.php)
- [tests/Unit/Services/Zoom/ZoomAuthRedirectDetailsTest.php](tests/Unit/Services/Zoom/ZoomAuthRedirectDetailsTest.php)
- [tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php](tests/Unit/Services/Zoom/ZoomMeetingCreateTest.php)
- [tests/Unit/Services/Zoom/ZoomZakTokenTest.php](tests/Unit/Services/Zoom/ZoomZakTokenTest.php)

When Phase 1 starts, the primary success condition is simple:

- the public API behavior does not change
- the OAuth tests still pass
- the Zoom business tests still pass

## Final End State

At the end of this refactor:

- `User` no longer owns Zoom credential storage
- OAuth browser state is shared infrastructure
- OAuth token persistence is shared infrastructure
- Zoom auth behavior is isolated in `ZoomOAuthService`
- Zoom business behavior is isolated in `ZoomService`
- the current fake-based test boundary still works
- adding a second OAuth provider means adding a new provider service, not rewriting token lifecycle code

## Recommended Execution Order

1. Extract `OAuthConnectionRepository` while still using `User` columns.
2. Move redirect and authorization exchange from `ZoomService` into `ZoomOAuthService`.
3. Introduce `oauth_connections` table and switch repository storage.
4. Remove Zoom-specific persistence from `User`.
5. Only then decide whether any additional shared token-refresh abstraction is justified.

This keeps the plan practical, keeps the current integration stable, and avoids building abstractions before the codebase has earned them.
