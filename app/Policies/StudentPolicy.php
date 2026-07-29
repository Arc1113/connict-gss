<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::DataEncoder);
    }

    public function view(User $user, Student $student): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::DataEncoder);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasRole(UserRole::DataEncoder);
    }

    public function delete(User $user, Student $student): bool
    {
        return false;
    }
}
