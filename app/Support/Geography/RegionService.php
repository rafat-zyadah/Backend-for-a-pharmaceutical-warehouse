<?php

namespace App\Support\Geography;

use App\Enums\RegionStatus;
use App\Models\Region;
use App\Models\SubRegion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegionService
{
    /** @param  array<string, mixed>  $data */
    public function createRegion(array $data): Region
    {
        return Region::query()->create([
            'name' => $data['name'],
            'status' => RegionStatus::Active,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function updateRegion(Region $region, array $data): Region
    {
        $region->fill([
            'name' => $data['name'] ?? $region->name,
            'status' => isset($data['status']) ? RegionStatus::from($data['status']) : $region->status,
        ])->save();

        return $region->refresh();
    }

    /** @param  array<string, mixed>  $data */
    public function createSubRegion(Region $region, array $data): SubRegion
    {
        if ($region->status !== RegionStatus::Active) {
            throw ValidationException::withMessages([
                'region_id' => ['Cannot add sub-regions to an inactive region.'],
            ]);
        }

        return SubRegion::query()->create([
            'region_id' => $region->id,
            'name' => $data['name'],
            'status' => RegionStatus::Active,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function updateSubRegion(SubRegion $subRegion, array $data): SubRegion
    {
        $subRegion->fill([
            'name' => $data['name'] ?? $subRegion->name,
            'status' => isset($data['status']) ? RegionStatus::from($data['status']) : $subRegion->status,
        ])->save();

        return $subRegion->refresh();
    }

    public function assertSubRegionBelongsToRegion(string $regionId, string $subRegionId): SubRegion
    {
        $subRegion = SubRegion::query()->where('id', $subRegionId)->first();

        if ($subRegion === null || $subRegion->region_id !== $regionId) {
            throw ValidationException::withMessages([
                'sub_region_id' => ['Sub-region must belong to the selected region.'],
            ]);
        }

        if ($subRegion->status !== RegionStatus::Active) {
            throw ValidationException::withMessages([
                'sub_region_id' => ['Sub-region is not active.'],
            ]);
        }

        return $subRegion;
    }

    public function assertRegionIsActive(Region $region): void
    {
        if ($region->status !== RegionStatus::Active) {
            throw ValidationException::withMessages([
                'region_id' => ['Region is not active.'],
            ]);
        }
    }
}
