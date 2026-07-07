<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    public function test_supervisor_can_create_rep(): void
    {
        $this->actingAsApiUser($this->supervisorUser())
            ->postJson('/api/v1/users', [
                'role' => UserRole::Rep->value,
                'username' => 'rep_demo',
                'name' => 'Demo Rep',
                'phone' => '0502222222',
                'password' => 'secret123',
                'residence' => 'Baghdad',
                'province' => 'Baghdad',
                'birth_date' => '1995-05-05',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::Rep->value);

        $this->assertDatabaseHas('users', ['username' => 'rep_demo']);
    }

    public function test_supervisor_can_create_invoicer(): void
    {
        $this->actingAsApiUser($this->supervisorUser())
            ->postJson('/api/v1/users', [
                'role' => UserRole::Invoicer->value,
                'username' => 'invoicer_demo',
                'name' => 'Demo Invoicer',
                'phone' => '0503333333',
                'password' => 'secret123',
                'residence' => 'Baghdad',
                'province' => 'Baghdad',
                'birth_date' => '1992-02-02',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::Invoicer->value);
    }

    public function test_supervisor_can_create_supervisor(): void
    {
        $this->actingAsApiUser($this->supervisorUser())
            ->postJson('/api/v1/users', [
                'role' => UserRole::Supervisor->value,
                'username' => 'supervisor2',
                'name' => 'Second Supervisor',
                'phone' => '0504444444',
                'password' => 'secret123',
            ])
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::Supervisor->value);
    }

    public function test_create_user_rejects_duplicate_username(): void
    {
        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->postJson('/api/v1/users', [
                'role' => UserRole::Rep->value,
                'username' => 'duplicate_user',
                'name' => 'Rep One',
                'phone' => '0505555551',
                'password' => 'secret123',
                'residence' => 'Baghdad',
                'province' => 'Baghdad',
                'birth_date' => '1990-01-01',
            ])
            ->assertCreated();

        $this->actingAsApiUser($supervisor)
            ->postJson('/api/v1/users', [
                'role' => UserRole::Rep->value,
                'username' => 'duplicate_user',
                'name' => 'Rep Two',
                'phone' => '0505555552',
                'password' => 'secret123',
                'residence' => 'Baghdad',
                'province' => 'Baghdad',
                'birth_date' => '1990-01-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }

    public function test_supervisor_can_list_users_by_role(): void
    {
        $this->createEmployeeViaApi(UserRole::Rep, ['username' => 'rep_list', 'phone' => '0506666661']);
        $this->createEmployeeViaApi(UserRole::Invoicer, ['username' => 'invoicer_list', 'phone' => '0506666666']);

        $this->actingAsApiUser($this->supervisorUser())
            ->getJson('/api/v1/users?role=rep')
            ->assertOk()
            ->assertJsonPath('data.0.role', UserRole::Rep->value);
    }

    public function test_supervisor_dashboard_returns_summary(): void
    {
        $this->createEmployeeViaApi(UserRole::Rep, ['username' => 'rep_dash', 'phone' => '0507777771']);
        $this->createEmployeeViaApi(UserRole::Invoicer, ['username' => 'inv_dash', 'phone' => '0507777772']);

        $this->actingAsApiUser($this->supervisorUser())
            ->getJson('/api/v1/users/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'summary' => [
                    'reps_total',
                    'reps_active',
                    'reps_suspended',
                    'invoicers_total',
                    'supervisors_total',
                    'users_total',
                    'monthly_target',
                ],
            ]);
    }

    public function test_supervisor_can_view_rep_password_and_audit_is_logged(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_pw',
            'phone' => '0508888881',
            'password' => 'visible-secret',
        ]);

        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->getJson("/api/v1/users/{$rep->id}")
            ->assertOk()
            ->assertJsonPath('password', 'visible-secret');

        $this->assertDatabaseHas('state_transition_logs', [
            'entity_type' => 'user',
            'entity_id' => $rep->id,
            'event' => 'view_password',
            'actor_id' => $supervisor->id,
        ]);
    }

    public function test_supervisor_cannot_view_another_supervisor_password(): void
    {
        $primary = $this->supervisorUser();

        $response = $this->actingAsApiUser($primary)
            ->postJson('/api/v1/users', [
                'role' => UserRole::Supervisor->value,
                'username' => 'supervisor2',
                'name' => 'Second Supervisor',
                'phone' => '0509999991',
                'password' => 'secret123',
            ])
            ->assertCreated();

        $secondSupervisor = User::query()->findOrFail($response->json('user.id'));

        $this->actingAsApiUser($primary)
            ->getJson("/api/v1/users/{$secondSupervisor->id}")
            ->assertOk()
            ->assertJsonMissingPath('password');
    }

    public function test_supervisor_can_update_rep(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_edit',
            'phone' => '0501010101',
        ]);

        $this->actingAsApiUser($this->supervisorUser())
            ->patchJson("/api/v1/users/{$rep->id}", [
                'name' => 'Edited Rep',
                'province' => 'Erbil',
                'password' => 'updated-secret',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Edited Rep')
            ->assertJsonPath('user.password', 'updated-secret');
    }

    public function test_supervisor_can_suspend_and_restore_user(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_suspend',
            'phone' => '0502020202',
        ]);

        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->postJson("/api/v1/users/{$rep->id}/suspend")
            ->assertOk()
            ->assertJsonPath('user.status', UserStatus::Suspended->value);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'rep_suspend',
            'password' => 'secret123',
        ], ['X-Client-Platform' => 'mobile'])->assertStatus(422);

        $this->actingAsApiUser($supervisor)
            ->postJson("/api/v1/users/{$rep->id}/restore")
            ->assertOk()
            ->assertJsonPath('user.status', UserStatus::Active->value);
    }

    public function test_supervisor_can_soft_delete_user(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_delete',
            'phone' => '0503030303',
        ]);

        $this->actingAsApiUser($this->supervisorUser())
            ->deleteJson("/api/v1/users/{$rep->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $rep->id]);
    }

    public function test_cannot_delete_only_supervisor(): void
    {
        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->deleteJson("/api/v1/users/{$supervisor->id}")
            ->assertForbidden();
    }

    public function test_can_delete_supervisor_when_another_supervisor_exists(): void
    {
        $primary = $this->supervisorUser();

        $response = $this->actingAsApiUser($primary)
            ->postJson('/api/v1/users', [
                'role' => UserRole::Supervisor->value,
                'username' => 'supervisor_backup',
                'name' => 'Backup Supervisor',
                'phone' => '0504040404',
                'password' => 'secret123',
            ])
            ->assertCreated();

        $backup = User::query()->findOrFail($response->json('user.id'));

        $this->actingAsApiUser($primary)
            ->deleteJson("/api/v1/users/{$backup->id}")
            ->assertOk();
    }

    public function test_rep_cannot_access_user_management(): void
    {
        $rep = $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => 'rep_forbidden',
            'phone' => '0505050505',
        ]);

        $this->actingAsApiUser($rep)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    }
}
