<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('version', 'v1');
    }

    public function test_rep_can_login_on_mobile_platform(): void
    {
        User::factory()->create([
            'username' => 'rep1',
            'role' => UserRole::Rep,
            'password' => 'secret',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'rep1',
            'password' => 'secret',
        ], [
            'X-Client-Platform' => 'mobile',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'role']]);
    }

    public function test_rep_cannot_login_on_web_platform(): void
    {
        User::factory()->create([
            'username' => 'rep2',
            'role' => UserRole::Rep,
            'password' => 'secret',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'rep2',
            'password' => 'secret',
        ], [
            'X-Client-Platform' => 'web',
        ])->assertStatus(422);
    }
}
