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
     * Wildcard tokens require 2FA to be enabled.
     * Session-only access is enforced at the middleware level.
     *
     * @param  array<int, string>  $requestedScopes
     */
    private function assertScopesAllowed(User $user, array $requestedScopes): void
    {
        // Require 2FA for wildcard token creation
        if (in_array('*', $requestedScopes, true) && ! $user->hasTwoFactorEnabled()) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'Creating wildcard tokens requires two-factor authentication to be enabled. Please enable 2FA in your profile settings.'
            );
        }
    }
}
