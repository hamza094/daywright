<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\ApiKeyRevokedNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\ApiTokenService;
use Illuminate\Support\Facades\DB;

final readonly class RevokeApiTokenAction
{
    public function __construct(
        private ApiTokenService $apiTokenService,
        private AuditLogService $auditLogService
    ) {}

    public function execute(User $user, int $tokenId): void
    {
        $token = $user->tokens()->where('id', $tokenId)->first();

        DB::transaction(function () use ($user, $tokenId, $token): void {
            $this->apiTokenService->deleteForUser($user, $tokenId);

            if ($token) {
                $this->auditLogService->log(
                    event: 'security.api_token_revoked',
                    auditable: $user,
                    oldValues: [
                        'token_name' => $token->name,
                        'token_id' => $token->id,
                        'expires_at' => $token->expires_at?->toIso8601String(),
                    ],
                    newValues: null,
                    metadata: [
                        'abilities' => $token->abilities,
                    ]
                );

                $user->notify(new ApiKeyRevokedNotification($token->name));
            }
        });
    }
}
