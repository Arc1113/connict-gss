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

    public function viewQrCodes(User $user): bool
    {
        return $user->hasRole(UserRole::DataEncoder);
    }

    public function printQrCodes(User $user): bool
    {
        return $this->viewQrCodes($user);
    }

    public function viewQrCode(User $user, Student $student): bool
    {
        return $this->viewQrCodes($user);
    }

    public function printQrCode(User $user, Student $student): bool
    {
        return $this->printQrCodes($user);
    }

    public function regenerateQrCode(User $user, Student $student): bool
    {
        return false;
    }
}
