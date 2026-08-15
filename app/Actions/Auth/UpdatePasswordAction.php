<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DataTransferObjects\User\PasswordUpdateData;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;

final readonly class UpdatePasswordAction
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function execute(User $user, PasswordUpdateData $data): void
    {
        $oldState = ['password_last_changed' => $user->updated_at?->toIso8601String()];

        DB::transaction(function () use ($user, $data, $oldState): void {
            $user->password = Hash::make($data->password);
            $user->save();

            // Invalidate other web sessions for security
            /** @var \Illuminate\Auth\SessionGuard $guard */
            $guard = Auth::guard('web');
            $guard->logoutOtherDevices($data->password);

            $user->refresh();
            $newState = ['password_last_changed' => $user->updated_at->toIso8601String()];

            $this->auditLogService->log(
                event: 'security.password_changed',
                auditable: $user,
                oldValues: $oldState,
                newValues: $newState,
                metadata: [
                    'via' => $this->determineAuthSource(),
                ]
            );
        });
    }

    /**
     * Determine if the request came from mobile or web.
     */
    private function determineAuthSource(): string
    {
        $userAgent = Request::userAgent() ?? '';

        // Simple heuristic for mobile detection
        $mobilePatterns = ['mobile', 'android', 'iphone', 'ipad', 'ios'];
        foreach ($mobilePatterns as $pattern) {
            if (mb_stripos($userAgent, $pattern) !== false) {
                return 'mobile';
            }
        }

        return 'web';
    }
}
