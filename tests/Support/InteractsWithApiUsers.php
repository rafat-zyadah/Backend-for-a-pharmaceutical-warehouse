<?php

namespace Tests\Support;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

trait InteractsWithApiUsers
{
    protected function installSystem(): void
    {
        $this->artisan('system:install');
    }

    protected function actingAsApiUser(User $user, ?string $platform = null): static
    {
        $platform ??= match ($user->role) {
            UserRole::Supervisor => 'web',
            UserRole::Invoicer => 'desktop',
            UserRole::Rep => 'mobile',
        };

        Sanctum::actingAs($user, [$user->role->value]);

        return $this->withHeader('X-Client-Platform', $platform);
    }

    protected function supervisorUser(): User
    {
        $this->installSystem();

        return User::query()->where('username', 'supervisor')->firstOrFail();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createEmployeeViaApi(UserRole $role, array $attributes = []): User
    {
        $payload = array_merge([
            'role' => $role->value,
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('05########'),
            'password' => 'secret123',
            'residence' => 'Baghdad',
            'province' => 'Baghdad',
            'birth_date' => '1990-01-01',
        ], $attributes);

        $response = $this->actingAsApiUser($this->supervisorUser())
            ->postJson('/api/v1/users', $payload);

        $response->assertCreated();

        return User::query()->findOrFail($response->json('user.id'));
    }
}
