<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFurniture;

class UserFurniturePolicy
{
    public function view(User $user, UserFurniture $furniture): bool
    {
        return $user->id === $furniture->user_id;
    }

    public function update(User $user, UserFurniture $furniture): bool
    {
        return $user->id === $furniture->user_id;
    }

    public function delete(User $user, UserFurniture $furniture): bool
    {
        return $user->id === $furniture->user_id;
    }
}