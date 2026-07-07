<?php

namespace App\Support\Install\Steps;

use App\Support\Install\Contracts\InstallStep;
use App\Support\Install\InstallStepResult;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsInstallStep implements InstallStep
{
    public function name(): string
    {
        return 'Roles & Permissions';
    }

    public function run(): InstallStepResult
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('install.guard', 'web');
        $created = 0;
        $skipped = 0;
        $messages = [];

        foreach (config('install.permissions', []) as $permissionName) {
            $permission = Permission::query()->where([
                'name' => $permissionName,
                'guard_name' => $guard,
            ])->first();

            if ($permission !== null) {
                $skipped++;

                continue;
            }

            Permission::query()->create([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
            $created++;
        }

        foreach (array_keys(config('install.role_permissions', [])) as $roleName) {
            $role = Role::query()->where([
                'name' => $roleName,
                'guard_name' => $guard,
            ])->first();

            if ($role === null) {
                $role = Role::query()->create([
                    'name' => $roleName,
                    'guard_name' => $guard,
                ]);
                $created++;
            } else {
                $skipped++;
            }

            $permissionNames = config("install.role_permissions.{$roleName}", []);
            $permissions = Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $permissionNames)
                ->get();

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $messages[] = sprintf(
            '%d permission(s), %d role(s) ensured.',
            Permission::query()->where('guard_name', $guard)->count(),
            Role::query()->where('guard_name', $guard)->count(),
        );

        return new InstallStepResult(
            name: $this->name(),
            created: $created,
            skipped: $skipped,
            messages: $messages,
        );
    }
}
