<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
