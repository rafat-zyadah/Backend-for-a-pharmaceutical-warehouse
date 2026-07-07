<?php

namespace App\Support\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = UserRole::from($data['role']);

            $user = User::query()->create([
                'username' => $data['username'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'role' => $role,
                'status' => isset($data['status']) ? UserStatus::from($data['status']) : UserStatus::Active,
                'password' => $data['password'],
                'avatar_url' => $data['avatar_url'] ?? null,
                'residence' => $data['residence'] ?? null,
                'province' => $data['province'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
            ]);

            $this->syncSpatieRole($user);

            return $user->refresh();
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, array $data): User
    {
        $passwordChanged = array_key_exists('password', $data);

        $payload = Arr::only($data, [
            'username',
            'name',
            'phone',
            'email',
            'password',
            'avatar_url',
            'residence',
            'province',
            'birth_date',
        ]);

        $statusChangingToSuspended = false;

        if (isset($data['status']) && request()->user()?->can('users.update')) {
            $status = UserStatus::from($data['status']);
            $payload['status'] = $status;
            $statusChangingToSuspended = $status === UserStatus::Suspended;

            if ($status === UserStatus::Active) {
                $payload['suspended_at'] = null;
            }

            if ($statusChangingToSuspended) {
                $payload['suspended_at'] = now();
            }
        }

        $user->fill($payload);
        $user->save();

        if ($passwordChanged || $statusChangingToSuspended) {
            $this->revokeAllTokens($user);
        }

        return $user->refresh();
    }

    /** @param  array<string, mixed>  $data */
    public function updateProfile(User $user, array $data): User
    {
        $passwordChanged = array_key_exists('password', $data);

        $payload = Arr::only($data, [
            'username',
            'name',
            'phone',
            'email',
            'password',
            'avatar_url',
            'residence',
            'province',
            'birth_date',
        ]);

        $user->fill($payload);
        $user->save();

        if ($passwordChanged) {
            $this->revokeAllTokens($user);
        }

        return $user->refresh();
    }

    public function suspend(User $user): User
    {
        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'status' => ['User is not active.'],
            ]);
        }

        $user->forceFill([
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        $this->revokeAllTokens($user);

        return $user->refresh();
    }

    public function restore(User $user): User
    {
        if ($user->status !== UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'status' => ['User is not suspended.'],
            ]);
        }

        $user->forceFill([
            'status' => UserStatus::Active,
            'suspended_at' => null,
        ])->save();

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        if ($user->role === UserRole::Supervisor && User::query()->role(UserRole::Supervisor)->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => ['The only supervisor in the system cannot be deleted.'],
            ]);
        }

        $user->forceFill(['status' => UserStatus::Deleted])->save();
        $this->revokeAllTokens($user);
        $user->delete();
    }

    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    private function syncSpatieRole(User $user): void
    {
        $guard = config('install.guard', 'web');

        $role = Role::query()->where([
            'name' => $user->role->value,
            'guard_name' => $guard,
        ])->first();

        if ($role !== null) {
            $user->syncRoles([$role]);
        }
    }
}
