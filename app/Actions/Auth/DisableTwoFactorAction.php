<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

final readonly class DisableTwoFactorAction
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function execute(User $user): User
    {
        $oldState = ['two_factor_enabled' => $user->hasTwoFactorEnabled()];

        return DB::transaction(function () use ($user, $oldState): User {
            $user->disableTwoFactorAuth();

            $user->refresh();
            $newState = ['two_factor_enabled' => $user->hasTwoFactorEnabled()];

            $this->auditLogService->log(
                event: 'security.2fa_disabled',
                auditable: $user,
                oldValues: $oldState,
                newValues: $newState
            );

            return $user;
        });
    }
}
