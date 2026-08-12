<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\Auth\TwoFactorDisableData;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final readonly class DisableTwoFactorAction
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function execute(User $user, TwoFactorDisableData $data): User
    {
        $this->validateCredentials($user, $data);

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

    private function validateCredentials(User $user, TwoFactorDisableData $data): void
    {
        if (! Hash::check($data->currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The provided password is incorrect.']);
        }

        if (! $user->verifyTwoFactorCode($data->code)) {
            throw ValidationException::withMessages(['code' => 'The provided two-factor code is invalid.']);
        }
    }
}
