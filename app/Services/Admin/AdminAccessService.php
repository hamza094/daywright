<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminAccessService
{
    public function grant(User $target, ?User $grantedBy = null): void
    {
        $target->is_admin = true;
        $target->admin_granted_at = Carbon::now();
        $target->admin_granted_by = $grantedBy?->id;
        $target->save();
    }

    public function revoke(User $target): void
    {
        $target->is_admin = false;
        $target->admin_granted_at = null;
        $target->admin_granted_by = null;
        $target->save();

        $target->tokens()->delete();

        $target->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', (string) $target->getAuthIdentifier())
                ->delete();
        }
    }
}
