# Webhook Provider Onboarding

Use this guide when adding a new third-party webhook provider. It keeps provider-specific behavior separate from the shared inbox reliability mechanism.

## Design Rule

Share the reliability mechanism, not the provider payload logic.

The shared inbox handles:

- durable persistence
- database deduplication
- state transitions
- atomic claims
- claim leases
- retry scheduling
- recovery of undispatched or expired rows

The provider integration handles:

- request authentication
- timestamp and replay validation
- payload validation
- event identity and event-key construction
- event-to-handler mapping
- provider-specific DTOs
- provider API calls
- provider acknowledgement requirements

Do not introduce a provider registry, generic strategy hierarchy, or shared interface until a second implementation demonstrates that the abstraction is needed.

## Provider Discovery

Before writing code, document:

- signature algorithm and required headers
- timestamp tolerance and replay rules
- provider event ID and whether it is globally unique
- event type field and schema versioning
- delivery retry behavior
- ordering guarantees
- acknowledgement status and response body requirements
- tenant, account, merchant, or workspace identity
- payload sensitivity and retention requirements
- provider API rate limits and timeout behavior

If the provider does not provide a stable event ID, use a deterministic fingerprint of authenticated request data and document its limitations.

## Implementation Steps

### 1. Add the provider boundary

Create a provider-specific middleware, request, or verifier that:

- authenticates the request
- rejects malformed or stale requests
- does not persist or log raw secrets
- computes the provider event key
- exposes safe correlation data

Do not use the generic idempotency middleware as a replacement for webhook inbox deduplication.

### 2. Add typed payload objects

Create provider-specific request validation and immutable DTOs for supported event types.

DTOs should:

- contain only validated provider data
- support `toArray()` and `fromArray()` when persisted in the inbox
- avoid HTTP concerns
- avoid business side effects

### 3. Persist through the inbox

Pass normalized metadata to the inbox workflow:

- provider
- event key
- event type
- encrypted payload
- provider event ID or safe request ID
- provider occurrence time when available

The database uniqueness constraint is authoritative. A duplicate must not create a second inbox row.

Persist and commit before acknowledging the provider. If persistence fails, return a failure so the provider can retry.

### 4. Add processing handlers

Map each supported event type to a focused business action. Handlers must be safe to execute again because the inbox guarantees at-least-once delivery, not exactly-once execution.

Handlers must not depend on the original HTTP request, middleware state, or an unencrypted raw payload.

### 5. Configure processing and recovery

Reuse the inbox claim, lease, retry, and recovery behavior unless the provider has a documented reason for a different policy.

Choose one retry owner. If the inbox owns retries, the queue job performs one attempt and the database stores the attempt count and next-available time.

Register a scheduled recovery command and ensure it is safe to run concurrently by using scheduler overlap protection and atomic claims.

### 6. Add operational documentation

Document:

- endpoint and authentication configuration
- event-key rules
- supported events
- retry and lease policy
- recovery command
- monitoring fields and alerts
- failed-event investigation and manual retry procedure
- payload retention policy

## Required Test Matrix

Every provider must have tests for:

- valid authentication
- invalid signature or credentials
- stale timestamp or replay rejection
- malformed and unsupported payloads
- deterministic event-key behavior
- duplicate delivery
- database uniqueness enforcement
- database acceptance failure
- queue-dispatch failure
- concurrent worker claims
- expired claim recovery
- retry backoff
- terminal failure
- handler replay safety
- provider acknowledgement behavior

## Common Versus Provider-Specific Decisions

| Concern         | Shared rule                               | Provider-specific decision           |
| --------------- | ----------------------------------------- | ------------------------------------ |
| Persistence     | Store an encrypted, recoverable inbox row | Payload fields and retention needs   |
| Deduplication   | Enforce a database uniqueness constraint  | Event ID or fingerprint formula      |
| Processing      | At-least-once with atomic claims          | Event mapping and handler behavior   |
| Recovery        | Recover undispatched and expired work     | Provider-specific retry expectations |
| Authentication  | Verify before acceptance                  | Signature algorithm and headers      |
| Acknowledgement | Acknowledge after durable acceptance      | HTTP status and response body        |
| Ordering        | Never assume exactly-once execution       | Whether ordering is guaranteed       |
| Logging         | Use safe identifiers and sanitized errors | Provider correlation fields          |

## When to Extract Shared Code

Keep the first additional provider explicit and provider-specific. Extract shared contracts or services only when both integrations have the same behavior and the abstraction reduces duplication without hiding provider differences.

Good extraction candidates include:

- common inbox persistence
- common claim and lease handling
- common recovery command behavior
- a small normalized webhook envelope

Avoid extracting:

- one universal signature verifier
- one DTO hierarchy for unrelated payloads
- one handler class containing provider conditionals
- provider-specific retry assumptions into the shared model

## Completion Gate

A provider is ready for production only when its endpoint, inbox acceptance, processing, recovery, handler replay safety, operational documentation, and required tests are complete. Passing unit tests alone is not sufficient; verify the database constraint, queue failure path, and expired-claim recovery path.
