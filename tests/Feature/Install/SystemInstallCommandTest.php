<?php

namespace Tests\Feature\Install;

use App\Enums\UserRole;
use App\Models\ApplicationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_command_fails_when_database_not_migrated(): void
    {
        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->with('users')
            ->andReturn(false);

        $this->artisan('system:install')
            ->expectsOutputToContain('Database is not migrated')
            ->assertFailed();
    }

    public function test_install_creates_mandatory_system_data(): void
    {
        $this->artisan('system:install')->assertSuccessful();

        $this->assertGreaterThan(0, Permission::query()->count());
        $this->assertDatabaseHas('roles', ['name' => UserRole::Supervisor->value]);
        $this->assertDatabaseHas('roles', ['name' => UserRole::Invoicer->value]);
        $this->assertDatabaseHas('roles', ['name' => UserRole::Rep->value]);
        $this->assertDatabaseHas('users', [
            'username' => 'supervisor',
            'role' => UserRole::Supervisor->value,
        ]);
        $this->assertDatabaseHas('application_settings', ['key' => 'low_stock_threshold']);
        $this->assertDatabaseHas('application_settings', ['key' => 'app_locale']);
    }

    public function test_install_is_idempotent(): void
    {
        $this->artisan('system:install')->assertSuccessful();
        $this->artisan('system:install')->assertSuccessful();

        $this->assertSame(1, User::query()->where('role', UserRole::Supervisor)->count());
        $this->assertSame(
            count(config('install.permissions', [])),
            Permission::query()->where('guard_name', config('install.guard'))->count(),
        );
        $this->assertSame(
            count(config('install.settings', [])),
            ApplicationSetting::query()->count(),
        );
    }

    public function test_supervisor_can_login_after_install(): void
    {
        $this->artisan('system:install')->assertSuccessful();

        $supervisor = User::query()->where('username', 'supervisor')->first();

        $this->assertNotNull($supervisor);
        $this->assertTrue($supervisor->hasRole(UserRole::Supervisor->value));

        $this->postJson('/api/v1/auth/login', [
            'login' => 'supervisor',
            'password' => 'password',
        ], [
            'X-Client-Platform' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::Supervisor->value);
    }

    public function test_default_supervisor_has_spatie_role_assigned(): void
    {
        $this->artisan('system:install')->assertSuccessful();

        $supervisor = User::query()->where('username', 'supervisor')->firstOrFail();

        $this->assertInstanceOf(Role::class, $supervisor->roles()->first());
        $this->assertSame(UserRole::Supervisor->value, $supervisor->roles()->first()->name);
    }
}
