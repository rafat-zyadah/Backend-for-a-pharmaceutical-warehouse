<?php

namespace App\Support\Distribution;

use App\Enums\AssignmentMode;
use App\Enums\AssignmentStatus;
use App\Enums\PharmacyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Pharmacy;
use App\Models\Region;
use App\Models\RepPharmacyAssignment;
use App\Models\RepRegionAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionService
{
    public function assertRep(User $user): void
    {
        if ($user->role !== UserRole::Rep) {
            throw ValidationException::withMessages([
                'rep_id' => ['User must be a rep.'],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'rep_id' => ['Rep must be active.'],
            ]);
        }
    }

    /** @return array<string, int> */
    public function dashboardStats(): array
    {
        $pharmacies = Pharmacy::query()->whereNull('deleted_at');
        $total = (clone $pharmacies)->count();
        $active = (clone $pharmacies)->where('status', PharmacyStatus::Active)->count();
        $suspended = (clone $pharmacies)->where('status', PharmacyStatus::Suspended)->count();

        $assignedIds = RepPharmacyAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->distinct()
            ->pluck('pharmacy_id');

        $sharedCount = RepPharmacyAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->select('pharmacy_id')
            ->groupBy('pharmacy_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        $assigned = $assignedIds->count();
        $unassigned = $total - $assigned;

        $regionsAssigned = RepRegionAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->distinct('region_id')
            ->count('region_id');

        return [
            'pharmacies_total' => $total,
            'pharmacies_active' => $active,
            'pharmacies_suspended' => $suspended,
            'pharmacies_assigned' => $assigned,
            'pharmacies_unassigned' => max(0, $unassigned),
            'pharmacies_shared' => $sharedCount,
            'regions_assigned' => $regionsAssigned,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{region: RepRegionAssignment, pharmacies_assigned: int}
     */
    public function assignRegion(User $rep, Region $region, array $data, User $actor): array
    {
        $this->assertRep($rep);

        $mode = AssignmentMode::from($data['mode'] ?? AssignmentMode::Add->value);
        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])
            : now();

        return DB::transaction(function () use ($rep, $region, $mode, $startDate, $actor): array {
            if ($mode === AssignmentMode::Transfer) {
                $this->endRegionAssignmentsForOthers($region, $rep, $startDate);
            }

            $regionAssignment = $this->ensureActiveRegionAssignment($rep, $region, $startDate, $actor);

            $pharmacies = Pharmacy::query()
                ->where('region_id', $region->id)
                ->where('status', PharmacyStatus::Active)
                ->get();

            $assignedCount = 0;

            foreach ($pharmacies as $pharmacy) {
                if ($mode === AssignmentMode::Transfer) {
                    $this->endPharmacyAssignmentsForOthers($pharmacy, $rep, $startDate);
                }

                $this->ensureActivePharmacyAssignment($rep, $pharmacy, $startDate, $actor);
                $assignedCount++;
            }

            return [
                'region' => $regionAssignment,
                'pharmacies_assigned' => $assignedCount,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, RepPharmacyAssignment>
     */
    public function assignPharmacies(User $rep, array $data, User $actor): Collection
    {
        $this->assertRep($rep);

        $mode = AssignmentMode::from($data['mode'] ?? AssignmentMode::Add->value);
        $startDate = isset($data['start_date'])
            ? Carbon::parse($data['start_date'])
            : now();

        $pharmacyIds = $data['pharmacy_ids'];

        return DB::transaction(function () use ($rep, $mode, $startDate, $pharmacyIds, $actor): Collection {
            $assignments = collect();

            foreach ($pharmacyIds as $pharmacyId) {
                $pharmacy = Pharmacy::query()->findOrFail($pharmacyId);

                if ($pharmacy->status !== PharmacyStatus::Active) {
                    throw ValidationException::withMessages([
                        'pharmacy_ids' => ["Pharmacy {$pharmacy->name} is not active."],
                    ]);
                }

                if ($mode === AssignmentMode::Transfer) {
                    $this->endPharmacyAssignmentsForOthers($pharmacy, $rep, $startDate);
                }

                $assignments->push(
                    $this->ensureActivePharmacyAssignment($rep, $pharmacy, $startDate, $actor),
                );
            }

            return $assignments;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function transferRegion(array $data, User $actor): array
    {
        $fromRep = User::query()->findOrFail($data['from_rep_id']);
        $toRep = User::query()->findOrFail($data['to_rep_id']);
        $region = Region::query()->findOrFail($data['region_id']);

        $this->assertRep($fromRep);
        $this->assertRep($toRep);

        $effectiveDate = Carbon::parse($data['effective_date']);
        $reason = $data['reason'];

        return DB::transaction(function () use ($fromRep, $toRep, $region, $effectiveDate, $actor): array {
            $this->endRepRegionAssignment($fromRep, $region, $effectiveDate);
            $this->endRepPharmaciesInRegion($fromRep, $region, $effectiveDate);

            return $this->assignRegion($toRep, $region, [
                'mode' => AssignmentMode::Add->value,
                'start_date' => $effectiveDate->toDateString(),
            ], $actor);
        });
    }

    public function removeRegion(User $rep, Region $region, string $reason, User $actor): array
    {
        $this->assertRep($rep);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Removal reason is required.']]);
        }

        $endDate = now();

        return DB::transaction(function () use ($rep, $region, $endDate): array {
            $regionEnded = $this->endRepRegionAssignment($rep, $region, $endDate);
            $pharmaciesEnded = $this->endRepPharmaciesInRegion($rep, $region, $endDate);

            $orphaned = Pharmacy::query()
                ->where('region_id', $region->id)
                ->where('status', PharmacyStatus::Active)
                ->whereDoesntHave('repAssignments', fn ($q) => $q->where('status', AssignmentStatus::Active))
                ->count();

            return [
                'region_ended' => $regionEnded,
                'pharmacies_ended' => $pharmaciesEnded,
                'pharmacies_now_unassigned' => $orphaned,
            ];
        });
    }

    public function removePharmacy(User $rep, Pharmacy $pharmacy, User $actor): RepPharmacyAssignment
    {
        $this->assertRep($rep);

        $assignment = RepPharmacyAssignment::query()
            ->where('rep_id', $rep->id)
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'pharmacy_id' => ['Rep is not assigned to this pharmacy.'],
            ]);
        }

        $assignment->update([
            'status' => AssignmentStatus::Ended,
            'end_date' => now()->toDateString(),
            'is_primary' => false,
        ]);

        return $assignment->refresh();
    }

    public function setPrimaryRep(Pharmacy $pharmacy, User $rep, User $actor): RepPharmacyAssignment
    {
        $this->assertRep($rep);

        $assignment = RepPharmacyAssignment::query()
            ->where('rep_id', $rep->id)
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'rep_id' => ['Rep must have an active assignment to this pharmacy.'],
            ]);
        }

        RepPharmacyAssignment::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->update(['is_primary' => false]);

        $assignment->update(['is_primary' => true]);

        return $assignment->refresh();
    }

    /** @return Collection<int, Pharmacy> */
    public function unassignedPharmacies(): Collection
    {
        return Pharmacy::query()
            ->with(['region', 'subRegion'])
            ->where('status', PharmacyStatus::Active)
            ->whereDoesntHave('repAssignments', fn ($q) => $q->where('status', AssignmentStatus::Active))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Pharmacy> */
    public function sharedPharmacies(): Collection
    {
        $sharedIds = RepPharmacyAssignment::query()
            ->where('status', AssignmentStatus::Active)
            ->select('pharmacy_id')
            ->groupBy('pharmacy_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('pharmacy_id');

        return Pharmacy::query()
            ->with(['region', 'subRegion', 'activeRepAssignments.rep'])
            ->whereIn('id', $sharedIds)
            ->orderBy('name')
            ->get();
    }

    public function isPharmacyShared(Pharmacy $pharmacy): bool
    {
        return RepPharmacyAssignment::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->count() > 1;
    }

    private function ensureActiveRegionAssignment(
        User $rep,
        Region $region,
        Carbon $startDate,
        User $actor,
    ): RepRegionAssignment {
        $existing = RepRegionAssignment::query()
            ->where('rep_id', $rep->id)
            ->where('region_id', $region->id)
            ->where('status', AssignmentStatus::Active)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return RepRegionAssignment::query()->create([
            'rep_id' => $rep->id,
            'region_id' => $region->id,
            'status' => AssignmentStatus::Active,
            'start_date' => $startDate->toDateString(),
            'created_by' => $actor->id,
        ]);
    }

    private function ensureActivePharmacyAssignment(
        User $rep,
        Pharmacy $pharmacy,
        Carbon $startDate,
        User $actor,
    ): RepPharmacyAssignment {
        $existing = RepPharmacyAssignment::query()
            ->where('rep_id', $rep->id)
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $activeCount = RepPharmacyAssignment::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', AssignmentStatus::Active)
            ->count();

        return RepPharmacyAssignment::query()->create([
            'rep_id' => $rep->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => AssignmentStatus::Active,
            'is_primary' => $activeCount === 0,
            'start_date' => $startDate->toDateString(),
            'created_by' => $actor->id,
        ]);
    }

    private function endRegionAssignmentsForOthers(Region $region, User $exceptRep, Carbon $endDate): int
    {
        return RepRegionAssignment::query()
            ->where('region_id', $region->id)
            ->where('rep_id', '!=', $exceptRep->id)
            ->where('status', AssignmentStatus::Active)
            ->update([
                'status' => AssignmentStatus::Ended,
                'end_date' => $endDate->toDateString(),
            ]);
    }

    private function endPharmacyAssignmentsForOthers(Pharmacy $pharmacy, User $exceptRep, Carbon $endDate): int
    {
        return RepPharmacyAssignment::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->where('rep_id', '!=', $exceptRep->id)
            ->where('status', AssignmentStatus::Active)
            ->update([
                'status' => AssignmentStatus::Ended,
                'end_date' => $endDate->toDateString(),
                'is_primary' => false,
            ]);
    }

    private function endRepRegionAssignment(User $rep, Region $region, Carbon $endDate): bool
    {
        return RepRegionAssignment::query()
            ->where('rep_id', $rep->id)
            ->where('region_id', $region->id)
            ->where('status', AssignmentStatus::Active)
            ->update([
                'status' => AssignmentStatus::Ended,
                'end_date' => $endDate->toDateString(),
            ]) > 0;
    }

    private function endRepPharmaciesInRegion(User $rep, Region $region, Carbon $endDate): int
    {
        $pharmacyIds = Pharmacy::query()
            ->where('region_id', $region->id)
            ->pluck('id');

        return RepPharmacyAssignment::query()
            ->where('rep_id', $rep->id)
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->where('status', AssignmentStatus::Active)
            ->update([
                'status' => AssignmentStatus::Ended,
                'end_date' => $endDate->toDateString(),
                'is_primary' => false,
            ]);
    }
}
