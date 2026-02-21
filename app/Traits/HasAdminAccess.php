<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use App\Services\Admin\AdminAccessService;

trait HasAdminAccess
{
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function markAsAdmin(?User $grantedBy = null): void
    {
        (new AdminAccessService)->grant($this, $grantedBy);
    }

    public function revokeAdminAccess(?User $revokedBy = null): void
    {
        (new AdminAccessService)->revoke($this);
    }
}
