<?php

namespace Tests\Feature\Api;

use App\Enums\AssignmentMode;
use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\Region;
use App\Models\RepPharmacyAssignment;
use App\Models\RepRegionAssignment;
use App\Models\SubRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    private function invoicer(): User
    {
        return $this->createEmployeeViaApi(UserRole::Invoicer, [
            'username' => 'invoicer_dist',
            'phone' => '0509100001',
        ]);
    }

    private function createRep(string $suffix): User
    {
        return $this->createEmployeeViaApi(UserRole::Rep, [
            'username' => "rep_{$suffix}",
            'phone' => '05091'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
        ]);
    }

    /** @return array{region: Region, sub_region: SubRegion, pharmacies: array<int, Pharmacy>} */
    private function seedRegionWithPharmacies(User $invoicer, int $count = 2): array
    {
        $region = Region::query()->create(['name' => 'Dist Region '.uniqid(), 'status' => 'active']);
        $subRegion = SubRegion::query()->create([
            'region_id' => $region->id,
            'name' => 'Sub '.uniqid(),
            'status' => 'active',
        ]);

        $pharmacies = [];

        for ($i = 0; $i < $count; $i++) {
            $pharmacies[] = Pharmacy::query()->create([
                'name' => "Pharmacy {$i} ".uniqid(),
                'phone' => '0770'.random_int(1000000, 9999999),
                'region_id' => $region->id,
                'sub_region_id' => $subRegion->id,
                'status' => 'active',
                'current_balance' => 0,
                'created_by' => $invoicer->id,
            ]);
        }

        return ['region' => $region, 'sub_region' => $subRegion, 'pharmacies' => $pharmacies];
    }

    public function test_invoicer_can_assign_region_in_add_mode(): void
    {
        $invoicer = $this->invoicer();
        $rep = $this->createRep('one');
        ['region' => $region] = $this->seedRegionWithPharmacies($invoicer, 2);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$rep->id}/regions", [
                'region_id' => $region->id,
                'mode' => AssignmentMode::Add->value,
            ])
            ->assertCreated()
            ->assertJsonPath('pharmacies_assigned', 2);

        $this->assertSame(1, RepRegionAssignment::query()->where('status', 'active')->count());
        $this->assertSame(2, RepPharmacyAssignment::query()->where('rep_id', $rep->id)->where('status', 'active')->count());
    }

    public function test_transfer_mode_ends_other_rep_assignments(): void
    {
        $invoicer = $this->invoicer();
        $repA = $this->createRep('a');
        $repB = $this->createRep('b');
        ['region' => $region, 'pharmacies' => $pharmacies] = $this->seedRegionWithPharmacies($invoicer, 1);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$repA->id}/regions", [
                'region_id' => $region->id,
                'mode' => AssignmentMode::Add->value,
            ])
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$repB->id}/regions", [
                'region_id' => $region->id,
                'mode' => AssignmentMode::Transfer->value,
            ])
            ->assertCreated();

        $this->assertSame(
            0,
            RepPharmacyAssignment::query()
                ->where('rep_id', $repA->id)
                ->where('status', 'active')
                ->count(),
        );

        $this->assertSame(
            1,
            RepPharmacyAssignment::query()
                ->where('rep_id', $repB->id)
                ->where('pharmacy_id', $pharmacies[0]->id)
                ->where('status', 'active')
                ->count(),
        );
    }

    public function test_shared_pharmacy_when_two_reps_assigned_in_add_mode(): void
    {
        $invoicer = $this->invoicer();
        $repA = $this->createRep('shared_a');
        $repB = $this->createRep('shared_b');
        ['pharmacies' => $pharmacies] = $this->seedRegionWithPharmacies($invoicer, 1);

        $payload = [
            'pharmacy_ids' => [$pharmacies[0]->id],
            'mode' => AssignmentMode::Add->value,
        ];

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$repA->id}/pharmacies", $payload)
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$repB->id}/pharmacies", $payload)
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->getJson('/api/v1/distribution/pharmacies/shared')
            ->assertOk()
            ->assertJsonCount(1, 'pharmacies');
    }

    public function test_remove_region_requires_reason(): void
    {
        $invoicer = $this->invoicer();
        $rep = $this->createRep('remove');
        ['region' => $region] = $this->seedRegionWithPharmacies($invoicer, 1);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$rep->id}/regions", [
                'region_id' => $region->id,
            ])
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$rep->id}/regions/{$region->id}/remove", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$rep->id}/regions/{$region->id}/remove", [
                'reason' => 'Coverage change',
            ])
            ->assertOk();

        $this->assertSame(0, RepRegionAssignment::query()->where('status', 'active')->count());
    }

    public function test_transfer_region_between_reps(): void
    {
        $invoicer = $this->invoicer();
        $repA = $this->createRep('from');
        $repB = $this->createRep('to');
        ['region' => $region] = $this->seedRegionWithPharmacies($invoicer, 2);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$repA->id}/regions", [
                'region_id' => $region->id,
            ])
            ->assertCreated();

        $this->actingAsApiUser($invoicer)
            ->postJson('/api/v1/distribution/regions/transfer', [
                'from_rep_id' => $repA->id,
                'to_rep_id' => $repB->id,
                'region_id' => $region->id,
                'effective_date' => '2026-07-07',
                'reason' => 'Territory rebalancing',
            ])
            ->assertOk()
            ->assertJsonPath('pharmacies_assigned', 2);

        $this->assertSame(
            0,
            RepPharmacyAssignment::query()->where('rep_id', $repA->id)->where('status', 'active')->count(),
        );

        $this->assertSame(
            2,
            RepPharmacyAssignment::query()->where('rep_id', $repB->id)->where('status', 'active')->count(),
        );
    }

    public function test_set_primary_rep_for_shared_pharmacy(): void
    {
        $invoicer = $this->invoicer();
        $repA = $this->createRep('primary_a');
        $repB = $this->createRep('primary_b');
        ['pharmacies' => $pharmacies] = $this->seedRegionWithPharmacies($invoicer, 1);

        $payload = ['pharmacy_ids' => [$pharmacies[0]->id], 'mode' => AssignmentMode::Add->value];

        $this->actingAsApiUser($invoicer)->postJson("/api/v1/distribution/reps/{$repA->id}/pharmacies", $payload);
        $this->actingAsApiUser($invoicer)->postJson("/api/v1/distribution/reps/{$repB->id}/pharmacies", $payload);

        $this->actingAsApiUser($invoicer)
            ->postJson("/api/v1/distribution/reps/{$repB->id}/pharmacies/{$pharmacies[0]->id}/set-primary")
            ->assertOk()
            ->assertJsonPath('assignment.is_primary', true);

        $this->assertTrue(
            RepPharmacyAssignment::query()
                ->where('rep_id', $repB->id)
                ->where('pharmacy_id', $pharmacies[0]->id)
                ->where('is_primary', true)
                ->exists(),
        );
    }

    public function test_dashboard_and_unassigned_pharmacies(): void
    {
        $invoicer = $this->invoicer();
        $this->seedRegionWithPharmacies($invoicer, 3);

        $this->actingAsApiUser($invoicer)
            ->getJson('/api/v1/distribution/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.pharmacies_total', 3)
            ->assertJsonPath('summary.pharmacies_unassigned', 3);

        $this->actingAsApiUser($invoicer)
            ->getJson('/api/v1/distribution/pharmacies/unassigned')
            ->assertOk()
            ->assertJsonCount(3, 'pharmacies');
    }

    public function test_supervisor_can_view_but_not_manage_distribution(): void
    {
        $supervisor = $this->supervisorUser();

        $this->actingAsApiUser($supervisor)
            ->getJson('/api/v1/distribution/dashboard')
            ->assertOk();

        $rep = User::query()->role(UserRole::Rep)->first();

        if ($rep === null) {
            $rep = $this->createRep('supervisor_test');
        }

        $this->actingAsApiUser($supervisor)
            ->postJson("/api/v1/distribution/reps/{$rep->id}/pharmacies", [
                'pharmacy_ids' => [],
            ])
            ->assertForbidden();
    }
}
