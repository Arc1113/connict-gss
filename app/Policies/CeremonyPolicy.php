<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ceremony;
use App\Models\User;

class CeremonyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::DataEncoder);
    }

    public function view(User $user, Ceremony $ceremony): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Ceremony $ceremony): bool
    {
        return false;
    }

    public function delete(User $user, Ceremony $ceremony): bool
    {
        return false;
    }
}
