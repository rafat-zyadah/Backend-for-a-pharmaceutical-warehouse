<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    public function test_user_can_update_own_profile(): void
    {
        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->patchJson('/api/v1/me/profile', [
                'name' => 'Updated Supervisor',
                'phone' => '0501111111',
                'password' => 'new-password',
                'province' => 'Basra',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Supervisor')
            ->assertJsonPath('user.province', 'Basra')
            ->assertJsonPath('requires_relogin', true);

        $supervisor->refresh();

        $this->assertSame('Updated Supervisor', $supervisor->name);
        $this->assertSame('new-password', $supervisor->password);
    }

    public function test_profile_update_does_not_change_role_or_status(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep);

        $this->actingAsApiUser($rep)
            ->patchJson('/api/v1/me/profile', [
                'name' => 'Rep Updated',
            ])
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::Rep->value)
            ->assertJsonPath('user.status', 'active');
    }

    public function test_profile_show_returns_current_user(): void
    {
        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->getJson('/api/v1/me/profile')
            ->assertOk()
            ->assertJsonPath('username', 'supervisor')
            ->assertJsonMissingPath('password');
    }
}
