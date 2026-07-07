<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->can('users.update')) {
            return true;
        }

        return $actor->id === $target->id;
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->can('users.delete')) {
            return false;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if ($target->role === UserRole::Supervisor && $this->supervisorCount() <= 1) {
            return false;
        }

        return true;
    }

    public function suspend(User $actor, User $target): bool
    {
        if (! $actor->can('users.suspend')) {
            return false;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if ($target->role === UserRole::Supervisor && $target->status === UserStatus::Active && $this->activeSupervisorCount() <= 1) {
            return false;
        }

        return $target->status === UserStatus::Active;
    }

    public function restore(User $actor, User $target): bool
    {
        if (! $actor->can('users.suspend')) {
            return false;
        }

        return $target->status === UserStatus::Suspended;
    }

    public function viewPassword(User $actor, User $target): bool
    {
        if (! $actor->can('users.reset_password')) {
            return false;
        }

        if ($target->role === UserRole::Supervisor) {
            return false;
        }

        return $target->isEmployee();
    }

    public function updateProfile(User $actor, User $target): bool
    {
        return $actor->id === $target->id;
    }

    private function supervisorCount(): int
    {
        return User::query()->role(UserRole::Supervisor)->count();
    }

    private function activeSupervisorCount(): int
    {
        return User::query()->role(UserRole::Supervisor)->status(UserStatus::Active)->count();
    }
}
