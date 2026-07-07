<?php

namespace App\Support\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class SupervisorContactService
{
    public function resolvePrimary(): ?User
    {
        $configuredUsername = config('pharmacy.default_supervisor.username');

        if (is_string($configuredUsername) && $configuredUsername !== '') {
            $configured = User::query()
                ->role(UserRole::Supervisor)
                ->status(UserStatus::Active)
                ->where('username', $configuredUsername)
                ->whereNotNull('phone')
                ->first();

            if ($configured !== null) {
                return $configured;
            }
        }

        return User::query()
            ->role(UserRole::Supervisor)
            ->status(UserStatus::Active)
            ->whereNotNull('phone')
            ->orderBy('created_at')
            ->first();
    }
}
