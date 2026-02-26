<?php

declare(strict_types=1);

namespace App\Traits;

trait HasAdminAccess
{
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
