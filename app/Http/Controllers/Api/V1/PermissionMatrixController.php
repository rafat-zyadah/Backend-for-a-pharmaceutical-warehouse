<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionMatrixController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.view') ?? false, 403);

        $guard = config('install.guard', 'web');

        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->get();

        $matrix = [];

        foreach ($roles as $role) {
            $matrix[$role->name] = $role->permissions->pluck('name')->sort()->values()->all();
        }

        return response()->json([
            'guard' => $guard,
            'permissions' => $permissions,
            'roles' => $roles->pluck('name')->values()->all(),
            'matrix' => $matrix,
        ]);
    }
}
