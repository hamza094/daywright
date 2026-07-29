<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Admin\AdminAccessService;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

final readonly class RevokeAdminAccessAction
{
    public function __construct(
        private AdminAccessService $adminAccessService,
        private AuditLogService $auditLogService
    ) {}

    public function execute(User $targetUser, User $revokedBy): User
    {
        $oldState = ['is_admin' => $targetUser->isAdmin()];

        return DB::transaction(function () use ($targetUser, $revokedBy, $oldState) {
            $this->adminAccessService->revokeAdminAccess($targetUser, $revokedBy);

            $targetUser->refresh();
            $newState = ['is_admin' => $targetUser->isAdmin()];

            $this->auditLogService->log(
                event: 'security.role_updated',
                auditable: $targetUser,
                oldValues: $oldState,
                newValues: $newState
            );

            return $targetUser;
        });
    }
}
