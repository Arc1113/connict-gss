<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StudentImport;
use App\Models\User;

class StudentImportPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Administrator)
            || $user->hasRole(UserRole::DataEncoder);
    }

    public function view(User $user, StudentImport $studentImport): bool
    {
        return $this->create($user)
            && $studentImport->user_id === $user->id;
    }

    public function updateMapping(
        User $user,
        StudentImport $studentImport,
    ): bool {
        return $this->view($user, $studentImport)
            && ! $studentImport->isConsumed();
    }

    public function confirm(User $user, StudentImport $studentImport): bool
    {
        return $this->updateMapping($user, $studentImport);
    }
}
