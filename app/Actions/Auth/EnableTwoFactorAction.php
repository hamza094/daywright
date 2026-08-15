<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class EnableTwoFactorAction
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function execute(User $user, string $code): User
    {
        $oldState = ['two_factor_enabled' => $user->hasTwoFactorEnabled()];

        return DB::transaction(function () use ($user, $code, $oldState): User {
            if (! $user->confirmTwoFactorAuth($code)) {
                throw ValidationException::withMessages(['code' => 'Invalid code provided.']);
            }

            $user->refresh();
            $newState = ['two_factor_enabled' => $user->hasTwoFactorEnabled()];

            $this->auditLogService->log(
                event: 'security.2fa_enabled',
                auditable: $user,
                oldValues: $oldState,
                newValues: $newState
            );

            return $user;
        });
    }
}
