<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = []
    ): AuditLog {
        $context = $this->determineContext();

        $metadata = array_merge([
            'ip_address' => app()->runningInConsole() ? '127.0.0.1' : Request::ip(),
            'user_agent' => app()->runningInConsole() ? 'CLI/Queue' : Request::userAgent(),
        ], $metadata);

        return AuditLog::create([
            'actor_type' => $context['actor_type'],
            'actor_id' => $context['actor_id'],
            'key_id' => $context['key_id'],
            'event' => $event,
            'auditable_type' => $auditable instanceof Model ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array{actor_type: string, actor_id: int|null, key_id: int|null}
     */
    private function determineContext(): array
    {
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            /** @var PersonalAccessToken|null $token */
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                return [
                    'actor_type' => 'api_token',
                    'actor_id' => $user->getAuthIdentifier(),
                    'key_id' => $token->id,
                ];
            }

            return [
                'actor_type' => 'user',
                'actor_id' => $user->getAuthIdentifier(),
                'key_id' => null,
            ];
        }

        if (Auth::guard('web')->check()) {
            return [
                'actor_type' => 'user',
                'actor_id' => Auth::guard('web')->id(),
                'key_id' => null,
            ];
        }

        if (app()->runningInConsole()) {
            return [
                'actor_type' => 'system',
                'actor_id' => null,
                'key_id' => null,
            ];
        }

        return [
            'actor_type' => 'system',
            'actor_id' => null,
            'key_id' => null,
        ];
    }
}
