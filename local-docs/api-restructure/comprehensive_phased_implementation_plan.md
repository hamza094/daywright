# Daywright API Security: Comprehensive Phased Implementation Plan

This document combines the deep architectural audit with the phased implementation strategy. It breaks down the API Key Lifecycle and Security implementation into 4 chronological phases. Each phase contains the objective, checklist, exact implementation code, and automated tests to ensure strict enforcement and verification at each step.

---

## Critical Decisions & Clarifications

### 1. Login-Issued Tokens (POST /login)

**Decision:** Keep login tokens as `['*']` (full access).

**Rationale:** POST /login is used by first-party apps (mobile apps, etc.) that need full access to act on behalf of the user. Only tokens generated manually via the dashboard (3rd-party integrations) should be restricted by the ApiScope enum.

### 2. "Never Expires" Tokens

**Decision:** The Phase 3 code already handles this correctly.

**Rationale:** The validation rule includes `'nullable'` for `expires_at`, and the DTO sets `expires_at = null` by default. If the frontend sends `expires_at: null`, the token will be created as "never expires."

### 3. Strict Mode Enforcement

**Decision:** Enforce strict mode - `scopes` is required for all API key creation requests.

**Rationale:** Since the application is in local development and not in production, there are no legacy API consumers to support. All API keys must explicitly declare their scopes. This ensures security by preventing accidental full-access token creation.

---

## Phase 1: Foundation (Scopes & Sanctum Config)

**Objective:** Establish the foundational configuration. Define the application's valid API scopes as a single source of truth and configure Laravel Sanctum to prefix all newly generated tokens for security scanning.

### Checklist

- [ ] Create `app/Enums/ApiScope.php` with the 7 consolidated scopes.
- [ ] Modify `config/sanctum.php` to add `'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'dw_live_'),`.

### Implementation Details

#### [NEW] `ApiScope` Enum — `app/Enums/ApiScope.php`

Single source of truth for all valid scopes in the Daywright ecosystem.

```php
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
```

#### [MODIFY] `config/sanctum.php`

Add the `token_prefix` key (supported since Sanctum 4.x / Laravel 11+). This prefix is prepended to the plain-text token returned to the user (e.g., `dw_live_1|abc123...`).

```php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'dw_live_'),

    'expiration' => null,

    // ... rest unchanged
];
```

### Tests (`tests/Unit/Enums/ApiScopeTest.php`)

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\ApiScope;
use PHPUnit\Framework\TestCase;

class ApiScopeTest extends TestCase
{
    public function test_it_returns_all_valid_values(): void
    {
        $values = ApiScope::values();

        $this->assertContains('projects:read', $values);
        $this->assertContains('account:write', $values);
        $this->assertCount(7, $values);
    }

    public function test_it_validates_scopes_correctly(): void
    {
        $this->assertTrue(ApiScope::allValid(['projects:read', 'team:write']));
        $this->assertFalse(ApiScope::allValid(['projects:read', 'invalid:scope']));
    }
}
```

---

## Phase 2: Route-Level Authorization Middleware

**Objective:** Register Sanctum's scope middleware and apply it across all your API routes. This ensures that Layer 1 (Scopes) is enforced _before_ Layer 2 (Policies) executes.

### Checklist

- [ ] Modify `bootstrap/app.php` (or `App\Http\Kernel.php`) to register the `CheckAbilities` and `CheckForAnyAbility` middleware aliases.
- [ ] Modify all route files in `routes/api/v1/` to apply the `ability:` middleware to the routes.

### Implementation Details

#### [MODIFY] Bootstrap / Kernel

In your `bootstrap/app.php` or `App\Http\Kernel`, register Sanctum ability middleware aliases:

```php
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'abilities' => CheckAbilities::class,
        'ability'   => CheckForAnyAbility::class,
    ]);
})
```

#### [MODIFY] `routes/api/v1/tokens.php`

```php
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
```

#### [MODIFY] `routes/api/v1/projects/core.php`

Add `ability:` middleware before policy checks. Example pattern:

```php
Route::get('/', [ProjectController::class, 'show'])
    ->name('projects.show')
    ->middleware('ability:projects:read')
    ->withTrashed();

Route::get('/limits', ProjectLimitsController::class)
    ->name('projects.limits')
    ->middleware('ability:projects:write')
    ->withTrashed()
    ->can('manage', 'project');
```

#### Complete Route Middleware Mapping

Apply the following middleware to all remaining route files in `routes/api/v1/`:

**Tasks, Meetings, Messages:**

- GET routes: `->middleware('ability:projects:read')`
- POST/PATCH/DELETE routes: `->middleware('ability:projects:write')`

**Users, Invitations:**

- GET routes: `->middleware('ability:team:read')`
- POST/PATCH/DELETE routes: `->middleware('ability:team:write')`

**Notifications, Dashboard:**

- GET routes: `->middleware('ability:account:read')`
- PATCH/DELETE routes: `->middleware('ability:account:write')`

**Webhooks:**

- POST routes: `->middleware('ability:webhooks:write')`

### Tests (`tests/Feature/Api/ScopeMiddlewareTest.php`)

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_requests_without_required_scope(): void
    {
        $user = User::factory()->create();
        // Create token with team:read only
        $token = $user->createToken('Test Token', ['team:read']);

        // Attempt to access projects which requires projects:read
        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/projects');

        // Should be forbidden due to missing scope
        $response->assertStatus(403);
        $response->assertJson(['message' => 'Invalid ability provided.']);
    }

    public function test_it_allows_requests_with_required_scope(): void
    {
        $user = User::factory()->create();
        // Create token with projects:read
        $token = $user->createToken('Test Token', ['projects:read']);

        $response = $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/projects');

        // Ensure it doesn't fail with a 403 from the scope middleware
        $this->assertNotEquals(403, $response->status());
    }
}
```

---

## Phase 3: Token Creation Pipeline (UI & Backend)

**Objective:** Update the token creation flow to accept user-selected scopes from the frontend, validate them against the `ApiScope` enum, and persist them to the database instead of hardcoding `['*']`.

### Checklist

- [ ] Modify `app/Http/Requests/Api/V1/User/UserTokenRequest.php` to validate the `scopes` array.
- [ ] Modify `app/DataTransferObjects/Auth/TokenCreateData.php` to include `abilities`.
- [ ] Modify `app/Services/Auth/ApiTokenService.php` to accept and pass `abilities`.
- [ ] Modify `app/Actions/Auth/CreateApiTokenAction.php` to pass `abilities` and log them in the audit trail.
- [ ] Modify `app/Http/Controllers/Api/V1/TokenController.php` to pull abilities from the DTO.

### Implementation Details

#### [MODIFY] `app/Http/Requests/Api/V1/User/UserTokenRequest.php`

Add scopes validation:

```php
use App\Enums\ApiScope;
use Illuminate\Validation\Rule;

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
```

#### [MODIFY] `app/DataTransferObjects/Auth/TokenCreateData.php`

Add `abilities` property:

```php
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
            abilities: $data['scopes'],
            expires_at: $data['expires_at'] ?? null,
        );
    }
}
```

#### [MODIFY] `app/Services/Auth/ApiTokenService.php`

```php
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
```

#### [MODIFY] `app/Actions/Auth/CreateApiTokenAction.php`

```php
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
```

#### [MODIFY] `app/Http/Controllers/Api/V1/TokenController.php`

```php
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
```

### Tests (`tests/Feature/Api/Tokens/TokenControllerTest.php`)

```php
<?php

namespace Tests\Feature\Api\Tokens;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_required_scopes_when_creating_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('session', ['*']);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/api-tokens', [
            'name' => 'New Key',
            // Missing scopes array entirely
        ]);

        // Strict mode: missing scopes should return 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scopes']);
    }

    public function test_it_validates_allowed_scopes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('session', ['*']);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/api-tokens', [
            'name' => 'New Key',
            'scopes' => ['projects:read', 'invalid:scope'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scopes.1']);
    }

    public function test_it_creates_token_with_valid_scopes_and_prefix(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('session', ['*']);

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/api-tokens', [
            'name' => 'My Scoped Key',
            'scopes' => ['projects:read', 'team:write'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.token_resource.name', 'My Scoped Key')
            ->assertJsonPath('data.token_resource.abilities', ['projects:read', 'team:write']);

        // Check that Sanctum prefixing from Phase 1 is applied correctly
        $plainTextToken = $response->json('data.token');
        $this->assertStringStartsWith('dw_live_', $plainTextToken);
    }
}
```

---

## Phase 4: Key Lifecycle Notifications

**Objective:** Implement security notifications to alert users whenever a new API key is created or an existing key is revoked on their account.

### Checklist

- [ ] Create `app/Notifications/ApiKeyCreatedNotification.php`.
- [ ] Create `app/Notifications/ApiKeyRevokedNotification.php`.
- [ ] Modify `app/Actions/Auth/CreateApiTokenAction.php` to dispatch the created notification.
- [ ] Modify `app/Actions/Auth/RevokeApiTokenAction.php` to dispatch the revoked notification.

### Implementation Details

#### [NEW] `app/Notifications/ApiKeyCreatedNotification.php`

```php
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
```

#### [NEW] `app/Notifications/ApiKeyRevokedNotification.php`

```php
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
```

#### [MODIFY] `app/Actions/Auth/CreateApiTokenAction.php`

After the audit log inside the `execute` method, dispatch notification:

```php
use App\Notifications\ApiKeyCreatedNotification;

// Inside the DB::transaction closure, after auditLogService->log():
$user->notify(new ApiKeyCreatedNotification($name));
```

#### [MODIFY] `app/Actions/Auth/RevokeApiTokenAction.php`

```php
use App\Notifications\ApiKeyRevokedNotification;

// Inside the DB::transaction closure, after auditLogService->log():
if ($token) {
    $user->notify(new ApiKeyRevokedNotification($token->name));
}
```

### Tests (`tests/Feature/Api/Tokens/ApiKeyNotificationTest.php`)

```php
<?php

namespace Tests\Feature\Api\Tokens;

use App\Models\User;
use App\Notifications\ApiKeyCreatedNotification;
use App\Notifications\ApiKeyRevokedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiKeyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_notification_on_token_creation(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $user->createToken('session', ['*']);

        $this->withToken($token->plainTextToken)->postJson('/api/v1/api-tokens', [
            'name' => 'Production Key',
            'scopes' => ['account:read'],
        ]);

        Notification::assertSentTo(
            $user,
            ApiKeyCreatedNotification::class,
            function ($notification) {
                return $notification->toArray(null)['token_name'] === 'Production Key';
            }
        );
    }

    public function test_it_sends_notification_on_token_revocation(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $sessionToken = $user->createToken('session', ['*']);
        $apiKey = $user->createToken('To Be Deleted', ['account:read']);

        $this->withToken($sessionToken->plainTextToken)
            ->deleteJson('/api/v1/api-tokens/' . $apiKey->id);

        Notification::assertSentTo(
            $user,
            ApiKeyRevokedNotification::class,
            function ($notification) {
                return $notification->toArray(null)['token_name'] === 'To Be Deleted';
            }
        );
    }
}
```

### Integration Test (`tests/Feature/Api/Tokens/TokenIntegrationTest.php`)

```php
<?php

namespace Tests\Feature\Api\Tokens;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_token_lifecycle_and_scope_enforcement(): void
    {
        $user = User::factory()->create();
        $sessionToken = $user->createToken('session', ['*']);

        // 1. Create a scoped token via the API (simulating the Vue Dashboard)
        $createResponse = $this->withToken($sessionToken->plainTextToken)
            ->postJson('/api/v1/api-tokens', [
                'name' => 'Limited CRM Key',
                'scopes' => ['projects:read'], // ONLY read access
                'expires_at' => null // Never expires
            ]);

        $createResponse->assertStatus(201);
        $plainTextKey = $createResponse->json('data.token');

        // 2. Use the new key to perform an ALLOWED action
        $this->withToken($plainTextKey)
            ->getJson('/api/v1/projects')
            ->assertStatus(200); // Or 404 if empty, but NOT 403 Forbidden

        // 3. Use the new key to perform a BLOCKED action
        $this->withToken($plainTextKey)
            ->postJson('/api/v1/projects', ['name' => 'New Project'])
            ->assertStatus(403) // Forbidden! It lacks projects:write
            ->assertJson(['message' => 'Invalid ability provided.']);
    }
}
```
