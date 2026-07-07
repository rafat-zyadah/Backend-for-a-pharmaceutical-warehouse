<?php

namespace App\Support\Install\Steps;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Install\Contracts\InstallStep;
use App\Support\Install\InstallStepResult;
use Spatie\Permission\Models\Role;

class DefaultSupervisorInstallStep implements InstallStep
{
    public function name(): string
    {
        return 'Default Supervisor';
    }

    public function run(): InstallStepResult
    {
        if (User::query()->where('role', UserRole::Supervisor)->exists()) {
            return new InstallStepResult(
                name: $this->name(),
                skipped: 1,
                messages: ['Supervisor account already exists.'],
            );
        }

        $config = config('pharmacy.default_supervisor');
        $guard = config('install.guard', 'web');

        $user = User::query()->create([
            'username' => $config['username'],
            'name' => $config['name'],
            'phone' => $config['phone'],
            'email' => $config['email'] ?? null,
            'role' => UserRole::Supervisor,
            'status' => UserStatus::Active,
            'password' => $config['password'],
        ]);

        $role = Role::query()->where([
            'name' => UserRole::Supervisor->value,
            'guard_name' => $guard,
        ])->first();

        if ($role !== null) {
            $user->assignRole($role);
        }

        return new InstallStepResult(
            name: $this->name(),
            created: 1,
            messages: [
                sprintf('Supervisor account "%s" created.', $user->username),
            ],
        );
    }
}
