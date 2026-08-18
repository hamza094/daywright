<?php

declare(strict_types=1);

return <<<'MARKDOWN'
# DayWright API Overview

DayWright exposes a versioned JSON API for authentication, users, projects, tasks, conversations, notifications, subscriptions, and selected integration workflows. Individual endpoint pages describe exact request fields and schemas; this page documents the shared conventions used across the API.

## Introduction

- The public API is currently implemented under `v1`.
- Requests are routed through Laravel API and web-backed API groups under the same `/api/{version}` prefix.
- Resource serialization is handled primarily through Laravel API resources, so most payloads are JSON-first and predictable.

## Base URL

- Relative base path: `/api/v1`
- Example requests and links below use relative API paths so the published docs stay environment-agnostic.


## Authentication

DayWright supports two authentication modes.

### Bearer token authentication

- API clients authenticate with Laravel Sanctum personal access tokens.
- Send tokens using the `Authorization` header:

```http
Authorization: Bearer YOUR_TOKEN
```

- `POST /login` returns a bearer token for token-based API clients when the account does not require two-factor authentication.
- `GET /api-tokens`, `POST /api-tokens`, and `DELETE /api-tokens/{token}` let authenticated users manage additional personal access tokens.
- Login-issued tokens are created with a one-month expiration.
- User-created API tokens may include an optional `expires_at` value in ISO 8601 format, up to 180 days in the future.
- Accounts with two-factor authentication enabled must use the session login flow. Token login does not expose a public 2FA continuation step.
- `POST /register` creates a user account and returns the user resource. It does not create a session or issue an access token.

Example login response:

```json
{
    "data": {
        "user": {
            "id": 1,
            "uuid": "9c4cc6f1-11e0-4c42-8f29-2d3d6d3d7412",
            "name": "Berry",
            "username": "berry",
            "email": "berry@example.com",
            "timezone": "UTC",
            "two_factor_enabled": false,
            "verified": true
        },
        "access_token": "1|wKfQJc..."
    }
}
```

Example register response:

```json
{
    "data": {
        "id": 1,
        "uuid": "9c4cc6f1-11e0-4c42-8f29-2d3d6d3d7412",
        "name": "Berry",
        "username": "berry",
        "email": "berry@example.com",
        "timezone": "UTC",
        "two_factor_enabled": false,
        "verified": false
    }
}
```

### Stateful session authentication

- First-party browser flows use Sanctum's stateful session mode.
- These routes are mounted under the same versioned API prefix and use `web` middleware rather than the standard `api` middleware group.
- Session-oriented routes include `/session/*`, `/twofactor/*`, and interactive OAuth flows under `/auth/*`.
- Browser clients should send session cookies and satisfy CSRF requirements for these endpoints.
- Two-factor authentication is completed only through this session-backed flow.
- `POST /session/login` may return a 2FA challenge state for accounts with two-factor authentication enabled, and the client completes sign-in with `POST /twofactor/login-confirm`.
- Third-party and server-to-server clients should prefer bearer tokens.

Example session login response for a two-factor-enabled account:

```json
{
    "data": {
        "two_factor_state": "2fa_required",
        "message": "Two-factor authentication is enabled. Please provide the verification code."
    }
}
```

### Integration-specific tokens

- The application includes Zoom utility endpoints that can return ZAK or JWT tokens for Zoom workflows.
- These tokens are integration-specific and are not used to authenticate the DayWright API itself.

## Request Format

- Send JSON request bodies unless an endpoint explicitly accepts files.
- Recommended headers for JSON requests:

```http
Accept: application/json
Content-Type: application/json
```

- File upload endpoints use `multipart/form-data`.
- Request and response fields use `snake_case`.
- Where timestamps are accepted, the API expects ISO 8601 timestamps with a timezone offset, for example `2025-12-31T23:59:59+00:00`.
- Selected filter requests normalize boolean-like query values, but JSON booleans are preferred.

## Query Contract

Released collection and collection-like read endpoints use a strict query contract unless an endpoint description explicitly documents an exception.

### Filtering

- Use `filter[field]=value` for general collection endpoints.
- Only documented filter keys are accepted.
- Unknown filter keys and invalid filter values return `422 Unprocessable Entity`.
- A valid filter that matches no records still returns `200 OK` with an empty `data` payload.

### Sorting

- Use `sort=field` for ascending order.
- Use `sort=-field` for descending order.
- Only documented sort keys are accepted.
- Endpoints that do not document `sort` reject it with `422 Unprocessable Entity`.

### Includes And Extra Parameters

- `include` is not part of the public contract unless an endpoint explicitly documents it.
- `fields` and `append` are also rejected unless an endpoint explicitly opts in.
- Unsupported top-level parameters, including arbitrary extras such as `random=value`, return `422 Unprocessable Entity` instead of being ignored.

### Pagination Categories

- Paginated endpoints use the top-level `data`, `links`, and `meta` structure with `page` and `per_page`.
- Unless an endpoint documents a narrower limit, `per_page` is validated in the range `1..100`.
- Empty paginated results still return `data`, `links`, and `meta`.
- Fixed-size endpoints return a documented server-controlled slice, for example the dashboard projects feed.
- Intentional bounded exceptions return a non-paginated collection only when the dataset is small and server-controlled, for example pending project invitations and dedicated lookup endpoints.

### Documented Query Exceptions

- Dashboard activity reads use top-level `start_date` and `end_date`.
- Dashboard chart data uses top-level `year` and optional `month`.
- Dedicated lookup endpoints may use a top-level `search` parameter instead of the general `filter[...]` grammar.
- Zoom-backed meeting endpoints under `/projects/{project}/meetings` are intentionally excluded from the generated OpenAPI. They remain supported runtime endpoints, including the `request=previous` meeting-index alias, but are treated as an integration-specific documentation exception for now.

## Response Format

Most successful responses use one of the following patterns.

### Resource or object response

```json
{
    "data": {
        "id": 1,
        "name": "The Dimension"
    }
}
```

### Collection response

```json
{
    "data": [
        {
            "id": 1,
            "name": "The Dimension",
            "slug": "the-dimension",
            "links": {
                "self": "/api/v1/projects/the-dimension"
            }
        }
    ]
}
```

### Message-only response

```json
{
    "message": "Webhook accepted."
}
```

### No-content response

- Some mutation endpoints intentionally return `204 No Content` with an empty body.

Notes:

- When a resource exposes navigational links, they are typically returned as relative API paths inside a `links` object.
- Some endpoints include endpoint-specific `meta` data alongside `data`.
- Not every successful response includes a `message` field; for most read and create operations, `data` is the canonical payload.

## Error Handling

API errors are normalized into a consistent envelope:

```json
{
    "message": "Resource not found.",
    "code": "not_found",
    "errors": {},
    "meta": {}
}
```

Notes:

- `message` is safe for client display.
- `code` is the stable machine-readable error identifier.
- `errors` contains field-level validation details when available; otherwise it is an empty object.
- `meta` contains structured context when available; otherwise it is an empty object.
- Application-authored 4xx messages may be preserved when safe to expose.
- Generic 5xx responses intentionally avoid leaking internal details.

DayWright also uses domain-specific error codes when appropriate, including examples such as `subscription_required`, `plan_limit_exceeded`, `project_archived`, `task_archived`, and `zoom_unavailable`.

## Validation Errors

Validation failures return `422 Unprocessable Entity` with the standard error envelope.

```json
{
    "message": "Validation failed.",
    "code": "validation_error",
    "errors": {
        "email": [
            "The provided credentials are incorrect."
        ]
    },
    "meta": {}
}
```

Notes:

- Form request validation is used extensively across the API.
- Validation errors are keyed by input name.
- Query validation errors use the same envelope and surface nested keys in dot notation, for example `filter.state`.
- Authentication failures during credential-based login are surfaced as validation errors on `email`, not as a separate `401` login response.

## Pagination

When an endpoint is paginated, the response uses Laravel's standard top-level `data`, `links`, and `meta` structure:

```json
{
    "data": [],
    "links": {
        "first": "/api/v1/projects?page=1",
        "last": "/api/v1/projects?page=5",
        "prev": null,
        "next": "/api/v1/projects?page=2"
    },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 73
    }
}
```

Notes:

- `page` and `per_page` are the common pagination parameters.
- Many list endpoints validate `per_page` with a maximum of `100`.
- Some list endpoints intentionally return fixed-size or bounded non-paginated collections; rely on the query-contract section above and each endpoint's description for exact behavior.

## Rate Limiting

The API uses Laravel rate limiters with route-specific policies.

| Limiter | Typical usage | Limit |
| --- | --- | --- |
| `api` | Default API middleware (Safety Net) | `300` requests per minute by IP |
| `user-ceiling` | User-level rate ceiling | `200` requests per minute by user |
| `per-token` | Token-specific rate limiting | `30` requests per minute by token |
| `sensitive-destructive` | Destructive operations (force delete, etc.) | `5` requests per minute by user |
| `sensitive-upload` | File upload operations | `10` requests per minute by user |
| `sensitive-token-mgmt` | API token management | `5` requests per minute by user |
| `sensitive-billing` | Subscription and billing operations | `5` requests per minute by user |
| `sensitive-password` | Password-related operations | `5` requests per minute by user |
| `auth-login` | Token login and session login | `5` requests per minute by IP and email |
| `auth-register` | Registration | `5` requests per minute by IP |
| `password-email` | Password reset link requests | `4` requests per minute by IP and email |
| `password-reset` | Password reset submission | `5` requests per minute by IP |
| `verification` | Email verification and resend flows | `6` requests per minute by user or IP |
| `two-factor` | 2FA setup and confirmation | `5` requests per minute by user or IP |
| `oauth2-socialite` | OAuth redirect and callback flows | `8` requests per minute by IP and provider |
| `invite-actions` | Project invitation actions | `10` requests per minute by user or IP |

Rate-limited responses return `429 Too Many Requests`. Integration-specific rate-limit failures may also include `meta.retry_after_seconds` when the upstream exception provides that detail.

## Idempotency

- Selected mutation routes are protected with idempotency middleware.
- When an endpoint requires idempotency, send a unique `Idempotency-Key` header.
- This is used on retriable write operations such as API token creation, invitation actions, selected subscription changes, selected task assignment workflows, selected meeting mutations, and Zoom webhooks.
- Idempotency is not enabled globally for every write endpoint, so rely on the endpoint documentation where the header is required.

## Versioning

- The API is path-versioned.
- Current published routes are mounted under `/api/v1`.
 

## Authorization

- Protected routes generally use `auth:sanctum`.
- Authorization is enforced with Laravel policies via route middleware such as `can:access,...` and controller-level `$this->authorize(...)` checks.
- Common policy abilities include `access`, `manage`, `owner`, `delete`, and invitation-specific checks.
- Additional route constraints are enforced through middleware such as `verified`, `subscription`, `2fa.enabled`, and Pennant feature checks.

## File Uploads

DayWright currently exposes two public multipart upload patterns.

### User avatars

- Endpoint: `POST /users/{user}/avatar`
- Request type: `multipart/form-data`
- Field: `avatar`
- Accepted types: `jpeg`, `jpg`, `png`
- Maximum size: `700 KB`
- Successful responses return the stored avatar URL inside `data.avatar`.

### Conversation attachments

- Endpoint: `POST /projects/{project}/conversations`
- Request type: `multipart/form-data`
- Fields:
    - `message` optional when `file` is present
    - `file` optional when `message` is present
- Accepted file types: `jpg`, `png`, `pdf`, `docx`
- Maximum size: `700 KB`
- Conversation attachments are stored privately and exposed through a temporary file URL when available.
- Generated conversation file URLs are short-lived and currently expire after 5 minutes.

## Webhooks

DayWright exposes Zoom meeting webhooks under `/api/v1/webhooks/zoom/meetings/*`.

- Webhook endpoints do not use Sanctum authentication.
- Each request is verified using Zoom signature headers:
    - `x-zm-request-id`
    - `x-zm-signature`
    - `x-zm-request-timestamp`
- Invalid webhook signatures return `403 Forbidden`.
- Missing required webhook headers return `400 Bad Request`.
- The Zoom request ID is reused as the idempotency key so duplicate deliveries can be safely deduplicated.
- Accepted webhook deliveries return:

```json
{
    "message": "Webhook accepted."
}
```

Webhook processing is asynchronous after acceptance.

## Caching

- The API does not currently expose a public HTTP caching contract such as `ETag`, `Last-Modified`, or route-level cache headers.
- Any server-side caching used by the application is an internal implementation detail and should not be relied on by clients.

## Status Codes

| Status | Meaning in DayWright |
| --- | --- |
| `200` | Successful read, update, action, or webhook acceptance response |
| `201` | Resource created successfully |
| `204` | Successful request with no response body |
| `400` | Invalid request state, invalid signature, or malformed callback data |
| `401` | Authentication is required |
| `403` | Authenticated but not authorized, not subscribed, or feature access is restricted |
| `404` | Resource or route not found |
| `405` | Method not allowed |
| `409` | Resource state conflict, including archived resources |
| `422` | Validation failed |
| `429` | Too many requests |
| `500` | Unexpected server or storage error |
| `503` | Upstream integration unavailable |

## Conventions

- JSON keys use `snake_case`.
- Path segments use lowercase plural nouns; compound segments often use hyphens, for example `forgot-password`, `task-statuses`, and `api-tokens`.
- Projects are addressed by slug.
- Users are addressed by UUID.
- Nested child resources such as tasks and conversations are typically route-scoped by their parent resource.
- Timestamps are generally serialized as ISO 8601 strings.

## Important Notes

- Unmatched routes under an API version resolve to a JSON `404` response, not an HTML fallback page.

- Subscription-gated endpoints use the same error envelope as the rest of the API and may include structured `meta` such as upgrade requirements or plan limit details. Premium endpoints return `403 Forbidden` if the user's subscription is inactive or plan limits are exceeded.

- Resources in an "abandoned" state use soft-deletes (`deleted_at`) and can be returned via endpoints using `withTrashed()`.
- OAuth and session endpoints live under the same `/api/v1` prefix as the token-based API, but they are intended for browser-driven workflows rather than generic third-party clients.
- Use each endpoint page as the source of truth for request fields, accepted query parameters, and resource schemas.
MARKDOWN;
