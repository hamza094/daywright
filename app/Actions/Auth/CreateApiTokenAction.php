<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\ApiKeyCreatedNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\ApiTokenService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\TransientToken;

final readonly class CreateApiTokenAction
{
    public function __construct(
        private ApiTokenService $apiTokenService,
        private AuditLogService $auditLogService
    ) {}

    /**
     * @param  array<int, string>  $scopes
     */
    public function execute(User $user, string $name, array $scopes, ?CarbonInterface $expiresAt): NewAccessToken
    {
        $this->assertScopesAllowed($user, $scopes);

        return DB::transaction(function () use ($user, $name, $scopes, $expiresAt): NewAccessToken {
            $token = $this->apiTokenService->createForUser($user, $name, $scopes, $expiresAt);

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
                    'abilities' => $scopes,
                ]
            );

            $user->notify(new ApiKeyCreatedNotification($name));

            return $token;
        });
    }

    /**
     * When a PAT creates another PAT, the new token's scopes must be
     * a subset of the calling token's abilities. SPA sessions (TransientToken)
     * can choose any scopes freely.
     *
     * @param  array<int, string>  $requestedScopes
     */
    private function assertScopesAllowed(User $user, array $requestedScopes): void
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|TransientToken|null $currentToken */
        $currentToken = $user->currentAccessToken();

        // If no token or it's a TransientToken (SPA session), no scope restriction
        if ($currentToken === null) {
            return;
        }

        if ($currentToken instanceof TransientToken) {
            return;
        }

        $callerAbilities = $currentToken->abilities ?? [];

        // If caller has wildcard (*) or no specific abilities, they can create tokens with any scopes
        if (empty($callerAbilities) || in_array('*', $callerAbilities, true)) {
            return;
        }

        $escalated = array_diff($requestedScopes, $callerAbilities);

        if ($escalated !== []) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Cannot create a token with scopes exceeding your current token\'s abilities: '.implode(', ', $escalated)
            );
        }
    }
}
