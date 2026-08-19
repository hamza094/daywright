Scramble OpenAPI — Resource Audit Implementation Plan
Background
The API Resources return correct JSON at runtime (Vue frontend is fully aligned), but Scramble's static analyzer generates incorrect or unusable OpenAPI schemas for several fields. This plan fixes schema correctness first, then polishes documentation.

Phase 1: Critical Schema Fixes (Broken SDK Generation)
These produce wrong types in the generated OpenAPI spec. Any third-party client or SDK generator will produce broken code.

ProjectResource.php — Fix 3 fields that Scramble cannot infer:
is_trashed: Add /** @var bool \*/ above the field. Scramble sees $this->trashed() and generates a self-reference to PublicProject instead of boolean.
ownerNotAuthorized: Add /** @var bool _/. The complex conditional inside whenLoaded generates string.
days_limit: Add /\*\* @var int _/. config() calls are opaque to Scramble, generating string.

ActivityResource.php — Fix dynamic dispatch and affected users:
description: Add /** @var string \*/. The method_exists() + dynamic $this->{$descriptionKey}() pattern is completely opaque to static analysis.
subject: Add /** @var array{type: string, id: int|null} _/ above the field.
affected_users: Add /\*\* @var array<int, array{id: int, uuid: string|null, name: string}> _/. Currently generates empty items schema.

FeatureFlagsResource.php — The dynamic collect(FeatureFlag::cases())->mapWithKeys(...) generates additionalProperties: {}. Decide:
Option A: If feature flag keys are a stable public contract, add explicit @var annotations for each known key.
Option B (Recommended): Keep the dynamic @return array<string, bool> on toArray() and accept that the keys are dynamic. The return type PHPDoc on toArray should be enough for Scramble to generate additionalProperties: {type: boolean}.
ProjectInsightsResource.php — Fix 2 fields:
project_id: Add /** @var int \*/. Currently generated as string.
sections_requested: Add /** @var array<int, string> \*/. Currently generated as string.

InsightResource.php — Fix data field:
Add /** @var array<string, mixed> \*/ above 'data' => is_array($data) ? $data : []. The ternary creates an incorrect string | empty array union.
SubscriptionResource.php — Fix 3 schema issues:
available_plans: Verify the generated spec. If it's a fixed-length tuple instead of an array, remove the inline @example and let the constructor @param PHPDoc (array<int, array{...}>) drive the schema.
limits: Same approach — verify and fix if items schema is empty.
grace_period.active: Add /** @var bool \*/ if Scramble generates string.
Verification
bash

php artisan scramble:export

# Check the generated api.json for:

# - PublicProject.is_trashed should be {type: "boolean"}

# - ActivityResource.affected_users.items should have properties

# - FeatureFlagsResource additionalProperties should be {type: "boolean"}

# - SubscriptionDetails.available_plans should be an array, not a tuple

Phase 2: Medium Priority — Format Annotations & Missing Descriptions
These don't break SDK generation but degrade Developer Experience. Add @format hints and missing descriptions.

ProjectResource.php — Add @format date-time (via description text) to:
created_at, updated_at, deleted_at, stage_updated_at, health_score_calculated_at
Add description for postponed_reason, links, health_status (document allowed values: hot, warm, cold), and health_score.
TaskResource.php — Add @format date-time to due_at, created_at, updated_at. Document allowed values for notified field.
ReceiptResource.php — Add descriptions and examples for all fields:
currency: ISO 4217 code
quantity: integer
receipt_url: absolute URL
amount and tax: decimal strings
created_at and updated_at: date-time
ProjectSummaryResource.php — Add descriptions and examples for id, slug, name, links.self.
TaskStatusResource.php — Add descriptions for id, label, color (document as hex color string).
TaskStatusIndexResource.php — Fix due_notifies.items empty schema. Document it as a list of the four supported notification strings.
StageResource.php — Add description for name.
UserProfileResource.php — Explicitly document timezone as a non-null IANA timezone string. Document verified as string|null with @format date-time.
UserActivitiesResource.php — Document allowed values for color field (green, purple, yellow, red).
ProjectCollectionResource.php — Improve health_status description and document allowed values.
Verification
bash

php artisan scramble:export

# Visually inspect /docs/api for improved descriptions and format badges

Phase 3: Minor — Deferred Items
These are valid long-term improvements but would break your Vue frontend or require feature work. Do not implement now.

UserInfoResource.mobile — Codex suggests changing from integer to string to preserve leading zeros. Do not change — your DB stores it as integer and your Vue frontend consumes it as integer. Changing this is a breaking change. Revisit when you add international phone number support.

SubscriptionResource.next_payment — Currently exposes raw Paddle Payment object. Creating a dedicated PaymentResource wrapper is a feature, not a bug fix. Defer to a future API versioning effort.

InsightResource.data — Define explicit object shapes per insight type (e.g., HealthInsightData, RiskInsightData). This requires designing a stable contract for each insight type. Defer until insights API is finalized.

UUID/email/URI format annotations — Add @format uuid, @format email, @format uri to AuthenticatedUserResource, InvitedUserResource, UserSummaryResource, TaskMemberResource. Pure polish.
