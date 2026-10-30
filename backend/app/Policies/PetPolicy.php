<?php

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;

class PetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('OWNER');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('OWNER');
    }

    public function view(User $user, Pet $pet): bool
    {
        return $user->hasRole('OWNER') && $pet->user_id === $user->id;
    }

    public function update(User $user, Pet $pet): bool
    {
        return $this->view($user, $pet);
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $this->view($user, $pet);
    }
}
