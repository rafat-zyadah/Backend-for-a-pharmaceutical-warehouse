<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    public function test_supervisor_can_view_permissions_matrix(): void
    {
        $response = $this->actingAsApiUser($this->supervisorUser())
            ->getJson('/api/v1/permissions/matrix')
            ->assertOk()
            ->assertJsonStructure([
                'guard',
                'permissions',
                'roles',
                'matrix' => [
                    'supervisor',
                    'invoicer',
                    'rep',
                ],
            ]);

        $this->assertContains('users.view', $response->json('matrix.supervisor'));
    }

    public function test_rep_cannot_view_permissions_matrix(): void
    {
        $rep = $this->createEmployeeViaApi(\App\Enums\UserRole::Rep, [
            'username' => 'rep_matrix',
            'phone' => '0506060606',
        ]);

        $this->actingAsApiUser($rep)
            ->getJson('/api/v1/permissions/matrix')
            ->assertForbidden();
    }
}
