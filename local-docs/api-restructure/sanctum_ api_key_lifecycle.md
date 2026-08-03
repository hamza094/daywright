Daywright Security & Architecture Audit — Sanctum API Key Lifecycle

Executive Summary
I've completed a deep audit of the Daywright codebase across all 4 pillars. Your foundation is strong — clean separation of session vs. token controllers, proper use of DTOs, actions, and services, and existing Policy-based authorization on resources. However, there are critical gaps in scoping and key lifecycle that would leave you exposed in a production SaaS.

Below is the pillar-by-pillar audit with severity ratings, followed by a complete implementation plan.

# Pillar 1: Login & Signup Separation Layer

Current State
Concern Status Notes
SPA session routes on web middleware ✅ PASS
v1.php
— mapVersionedWebRoutes applies the web middleware stack
API token routes on api middleware ✅ PASS
v1.php
— registered via mapVersionedApiRoutes with api middleware
LoginController::login() returns only JSON + token ✅ PASS
LoginController.php
— calls performApiLogin(), no session generation
SpaAuthController::loginSpa() creates session, no token ✅ PASS
SpaAuthController.php
— calls performSessionLogin(), regenerates session + CSRF
No guard crossover ✅ PASS performApiLogin() creates a Sanctum token; performSessionLogin() calls Auth::guard('web')->login()
Findings
TIP

Pillar 1 is the strongest part of your codebase. The separation between LoginController (stateless token) and SpaAuthController (stateful session) is clean and well-architected. The
RouteServiceProvider
correctly maps session-based routes under the web middleware and API routes under the api middleware.

⚠️ Minor Finding: LoginUserService::createApiToken() hardcodes ['*']
In
LoginUserService.php#L109
, the createApiToken method defaults to ['*'] abilities. While this is appropriate for the login flow (the user logs in and gets a full-access session-equivalent token), it signals that the ['*'] pattern is the systemic default everywhere, which bleeds into Pillar 2.

Pillar 2: Critical Scopes & Two-Tier Authorization
Current State
CAUTION

CRITICAL — No scope middleware exists anywhere in the codebase. Every single Sanctum token is issued with ['*'] abilities. There is zero enforcement of abilities: or ability: middleware on any route. Sanctum's CheckAbilities and CheckForAnyAbility middleware are not registered.

Concern Status Notes
Scope middleware (abilities: / ability:) on routes ❌ FAIL Zero usage across all route files
Scoped token creation ❌ FAIL ['*'] hardcoded in
ApiTokenService.php#L36
and
LoginUserService.php#L109
Policy-based authorization (Layer 2) ✅ PASS can:access,project, can:manage,project, can:manage,task properly used
Two-tier enforcement (scope then policy) ❌ FAIL Only Layer 2 (policies) exists; Layer 1 (scopes) is absent
Proposed Scope Map
Based on a consolidated domain-based model, here is the recommended scope taxonomy for v1:

projects:read → GET /projects, /tasks, /meetings, /messages
projects:write → POST/PATCH/DELETE /projects, /tasks, /meetings, /messages
team:read → GET /users, /invitations
team:write → POST/PATCH/DELETE /users, /invitations
account:read → GET /dashboard, /notifications, /api-tokens
account:write → POST/PATCH/DELETE /notifications, /api-tokens
webhooks:write → POST /webhooks/_ (internal Zoom callbacks)
What Needs to Change
Create an ApiScope enum that is the single source of truth for all valid scopes
Register Sanctum's ability middleware in the app bootstrap
Apply ability: middleware on every route group before policies execute
Pass user-selected scopes through token creation instead of ['_']
Pillar 3: API Key Creation (UI & Backend)
Current State
WARNING

The store action hardcodes ['*'] and does not accept user-selected scopes. The UserTokenRequest only validates name and expires_at — it has no scopes field. The TokenCreateData DTO has no abilities property.

Concern Status Notes
Accepts user-selected scopes array ❌ FAIL
UserTokenRequest
— no scopes field
Validates scopes against allowed list ❌ FAIL No validation exists
User-selectable expiration ✅ PARTIAL expires_at is accepted and validated (max 180 days), but "Never" option not possible.
CreateApiTokenAction
passes $expiresAt through
DTO carries abilities ❌ FAIL
TokenCreateData
— no abilities property
Service uses dynamic abilities ❌ FAIL
ApiTokenService::createForUser()
hardcodes ['*']
What Needs to Change
Add scopes array field to UserTokenRequest with validation against the ApiScope enum
Add abilities property to TokenCreateData
Flow abilities through CreateApiTokenAction → ApiTokenService::createForUser()
Allow expires_at to be null for "Never expires" tokens (currently supported but undocumented)
Pillar 4: Key Lifecycle, Compromise & Edge Cases
Current State
CAUTION

Key lifecycle tracking is missing. There is no token prefix, and no notifications on key events.

Concern Status Notes
Token prefixing (dw*live*) ❌ MISSING
sanctum.php
— no token_prefix key
Notification on create/revoke ❌ MISSING No API key notifications in
Notifications/
Proposed Changes
Component 1: Scope Infrastructure
[NEW] ApiScope Enum — app/Enums/ApiScope.php
Single source of truth for all valid scopes in the Daywright ecosystem.

php

<?php
declare(strict_types=1);
namespace App\Enums;
enum ApiScope: string
{
    case ProjectsRead = 'projects:read';
    case ProjectsWrite = 'projects:write';
    case TeamRead = 'team:read';
    case TeamWrite = 'team:write';
    case AccountRead = 'account:read';
    case AccountWrite = 'account:write';
    case WebhooksWrite = 'webhooks:write';
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    /**
     * Validate that all provided scope strings are valid.
     *
     * @param  array<int, string>  $scopes
     */
    public static function allValid(array $scopes): bool
    {
        $valid = self::values();
        return collect($scopes)->every(fn (string $scope) => in_array($scope, $valid, true));
    }
}
Component 2: Middleware Registration
[MODIFY] Bootstrap / Kernel — Register Sanctum ability middleware aliases
In your bootstrap/app.php or App\Http\Kernel, register:

php

use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'abilities' => CheckAbilities::class,
        'ability'   => CheckForAnyAbility::class,
    ]);
})
Component 3: Route-Level Scope Enforcement
[MODIFY] 
tokens.php
php

Route::controller(TokenController::class)
    ->prefix('api-tokens')
    ->name('api-tokens.')
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('ability:account:read')
            ->name('index');
        Route::post('/', 'store')
            ->middleware([Idempotent::using(scope: IdempotencyScope::User), 'ability:account:write'])
            ->name('store');
        Route::delete('/{token}', 'destroy')
            ->middleware('ability:account:write')
            ->name('destroy');
    });
[MODIFY] 
projects/core.php
Add ability: middleware before policy checks. Example pattern:

php

Route::get('/', [ProjectController::class, 'show'])
    ->name('projects.show')
    ->middleware('ability:projects:read')
    ->withTrashed();
Route::get('/limits', ProjectLimitsController::class)
    ->name('projects.limits')
    ->middleware('ability:projects:write')
    ->withTrashed()
    ->can('manage', 'project');
The same pattern applies to all other route files — ability: middleware runs before can: policy checks.

Component 4: Token Creation Pipeline Refactor
[MODIFY] 
UserTokenRequest.php
Add scopes validation:

php

public function rules(): array
{
    return [
        'name' => 'required|string|max:255',
        'scopes' => ['required', 'array', 'min:1'],
        'scopes.*' => ['required', 'string', Rule::in(ApiScope::values())],
        'expires_at' => [
            'bail',
            'nullable',
            new Iso8601Timestamp,
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value) {
                    $maxDate = now()->addDays(365);
                    if (\Carbon\Carbon::parse($value)->gt($maxDate)) {
                        $fail('The '.$attribute.' may not be more than 1 year from now.');
                    }
                }
            },
        ],
    ];
}
[MODIFY] 
TokenCreateData.php
php

final readonly class TokenCreateData
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public string $name,
        public array $abilities,
        public ?\Carbon\Carbon $expires_at = null,
    ) {}
    public static function fromArray(array $payload): self
    {
        $data = Arr::only($payload, ['name', 'scopes', 'expires_at']);
        $data['expires_at'] = empty($data['expires_at']) ? null : \Carbon\Carbon::parse($data['expires_at']);
        if (! isset($data['name']) || $data['name'] === '') {
            throw new InvalidArgumentException('Name is required');
        }
        return new self(
            name: $data['name'],
            abilities: $data['scopes'] ?? ['*'],
            expires_at: $data['expires_at'] ?? null,
        );
    }
}
[MODIFY] 
ApiTokenService.php
php

/**
 * @param  array<int, string>  $abilities
 */
public function createForUser(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt): NewAccessToken
{
    return $this->planLimitService->executeWithinAccountLimit(
        PlanLimitType::ApiTokens,
        $user,
        fn (User $lockedUser): NewAccessToken => $lockedUser->createToken(
            $name,
            $abilities,
            $expiresAt,
        )
    );
}
[MODIFY] 
CreateApiTokenAction.php
php

/**
 * @param  array<int, string>  $abilities
 */
public function execute(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt): NewAccessToken
{
    return DB::transaction(function () use ($user, $name, $abilities, $expiresAt): NewAccessToken {
        $token = $this->apiTokenService->createForUser($user, $name, $abilities, $expiresAt);
        $this->auditLogService->log(
            event: 'security.api_token_created',
            auditable: $user,
            oldValues: null,
            newValues: [
                'token_name' => $name,
                'token_id' => $token->accessToken->id,
                'expires_at' => $expiresAt?->toIso8601String(),
            ],
            metadata: [
                'abilities' => $abilities,
            ]
        );
        return $token;
    });
}
[MODIFY] 
TokenController.php
php

public function store(UserTokenRequest $request): JsonResponse
{
    $data = $request->toDto();
    $token = $this->createApiTokenAction->execute(
        $this->authenticatedUser(),
        $data->name,
        $data->abilities,
        $data->expires_at
    );
    return $this->respondWithData(
        new TokenStoreResource($token->plainTextToken, $token->accessToken),
        Response::HTTP_CREATED,
    );
}
Component 5: Token Prefix
[MODIFY] 
sanctum.php
Add the token_prefix key (supported since Sanctum 4.x / Laravel 11+):

php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'dw_live_'),
    'expiration' => null,
    // ... rest unchanged
];
IMPORTANT

This prefix is prepended to the plain-text token returned to the user (e.g., dw_live_1|abc123...). GitHub's secret scanning and tools like TruffleHog can be configured to pattern-match on dw_live_ to detect leaked Daywright API keys.

Component 6: Notifications
[NEW] ApiKeyCreatedNotification — app/Notifications/ApiKeyCreatedNotification.php
php

<?php
declare(strict_types=1);
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class ApiKeyCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(
        private readonly string $tokenName,
    ) {}
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New API Key Created — Daywright')
            ->greeting('Security Notice')
            ->line("A new API key **\"{$this->tokenName}\"** was just created on your Daywright account.")
            ->line('If you did not create this key, please revoke it immediately from your dashboard.')
            ->action('Manage API Keys', url('/settings/api-tokens'))
            ->line('Thank you for using Daywright.');
    }
    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'api_key_created',
            'token_name' => $this->tokenName,
        ];
    }
}
[NEW] ApiKeyRevokedNotification — app/Notifications/ApiKeyRevokedNotification.php
php

<?php
declare(strict_types=1);
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class ApiKeyRevokedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public function __construct(
        private readonly string $tokenName,
    ) {}
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('API Key Revoked — Daywright')
            ->greeting('Security Notice')
            ->line("The API key **\"{$this->tokenName}\"** has been revoked on your Daywright account.")
            ->line('Any integrations using this key will stop working immediately.')
            ->action('Manage API Keys', url('/settings/api-tokens'))
            ->line('If this was unexpected, please review your account security settings.');
    }
    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'api_key_revoked',
            'token_name' => $this->tokenName,
        ];
    }
}
Component 7: Wire Notifications Into Existing Actions
[MODIFY] 
CreateApiTokenAction.php
After the audit log, dispatch notification:

php

use App\Notifications\ApiKeyCreatedNotification;
// Inside the DB::transaction closure, after auditLogService->log():
$user->notify(new ApiKeyCreatedNotification($name));
[MODIFY] 
RevokeApiTokenAction.php
php

use App\Notifications\ApiKeyRevokedNotification;
// Inside the DB::transaction closure, after auditLogService->log():
if ($token) {
    $user->notify(new ApiKeyRevokedNotification($token->name));
}
Open Questions
IMPORTANT

Login-issued tokens: When a user logs in via POST /login (the API token flow), should the token still receive ['*'] (full access), or should login tokens also be scoped? Typically, login tokens are ['*'] and only dashboard-created API keys get scoped.

IMPORTANT

"Never expires" tokens: Your current validation caps at 180 days. Do you want to raise this to 365 days and/or allow null for tokens that never expire? The implementation above assumes 365 days max with null = never.

Verification Plan
Automated Tests
bash

# Unit tests for ApiScope enum
php artisan test --filter=ApiScopeTest
# Feature tests for scoped token creation
php artisan test --filter=TokenControllerTest
# Test that routes reject tokens lacking required scopes
php artisan test --filter=ScopeMiddlewareTest
# Notification assertions
php artisan test --filter=ApiKeyNotificationTest
Manual Verification
Create a token via API with scopes: ["projects:read"], then verify that POST /projects returns 403
Verify the plain-text token starts with dw_live_
Confirm notification emails are sent on create/revoke
Check TokenResource includes the actual abilities instead of ['*']
Summary Scorecard
Pillar	Before	After
1. Login/Signup Separation	✅ Pass	✅ Pass (no changes needed)
2. Scopes & Two-Tier Auth	❌ Critical	✅ Scope enum + middleware + policies
3. API Key Creation	❌ Hardcoded ['*']	✅ User-selected scopes, validated
4. Key Lifecycle	❌ Missing entirely	✅ Prefix, notifications
Agent
daywright


AI may make mistakes. Double-check all generated code.
