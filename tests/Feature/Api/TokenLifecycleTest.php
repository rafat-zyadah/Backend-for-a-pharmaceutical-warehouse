<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class TokenLifecycleTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    public function test_logout_deletes_current_token_only(): void
    {
        $supervisor = $this->supervisorUser();
        $token = $supervisor->createToken('device-a')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Client-Platform', 'web')
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $supervisor->fresh()->tokens()->count());

        $this->resetAuthGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Client-Platform', 'web')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_password_change_revokes_all_tokens_for_profile_update(): void
    {
        $supervisor = $this->supervisorUser();
        $token = $supervisor->createToken('device-a')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Client-Platform', 'web')
            ->patchJson('/api/v1/me/profile', [
                'password' => 'changed-password',
            ])
            ->assertOk()
            ->assertJsonPath('requires_relogin', true);

        $this->assertSame(0, $supervisor->fresh()->tokens()->count());

        $this->resetAuthGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Client-Platform', 'web')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_supervisor_password_reset_revokes_employee_tokens(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_token',
            'phone' => '0508080808',
        ]);

        $token = $rep->createToken('mobile-device')->plainTextToken;

        $this->actingAsApiUser($this->supervisorUser())
            ->patchJson("/api/v1/users/{$rep->id}", [
                'password' => 'new-employee-password',
            ])
            ->assertOk()
            ->assertJsonPath('requires_relogin', true);

        $this->assertSame(0, $rep->fresh()->tokens()->count());

        $this->resetAuthGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Client-Platform', 'mobile')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_suspend_revokes_all_tokens_immediately(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_suspend_token',
            'phone' => '0509090909',
        ]);

        $token = $rep->createToken('mobile-device')->plainTextToken;

        $this->actingAsApiUser($this->supervisorUser())
            ->postJson("/api/v1/users/{$rep->id}/suspend")
            ->assertOk();

        $this->assertSame(0, $rep->fresh()->tokens()->count());

        $this->resetAuthGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Client-Platform', 'mobile')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_login_block',
            'phone' => '0509191919',
        ]);

        $this->actingAsApiUser($this->supervisorUser())
            ->postJson("/api/v1/users/{$rep->id}/suspend")
            ->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'login' => 'rep_login_block',
            'password' => 'secret123',
        ], [
            'X-Client-Platform' => 'mobile',
        ])->assertStatus(422);
    }
}
