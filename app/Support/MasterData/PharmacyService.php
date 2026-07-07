<?php

namespace App\Support\MasterData;

use App\Enums\PharmacyStatus;
use App\Models\Pharmacy;
use App\Models\Region;
use App\Models\User;
use App\Support\Geography\RegionService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function __construct(
        private readonly RegionService $regionService,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Pharmacy
    {
        $region = Region::query()->findOrFail($data['region_id']);
        $this->regionService->assertRegionIsActive($region);
        $this->regionService->assertSubRegionBelongsToRegion($region->id, $data['sub_region_id']);

        $duplicate = $this->findDuplicates($data);
        if ($duplicate['confirmed']->isNotEmpty()) {
            throw ValidationException::withMessages([
                'name' => ['A pharmacy with matching name, phone, region, and address already exists.'],
            ]);
        }

        return Pharmacy::query()->create([
            'name' => $data['name'],
            'responsible' => $data['responsible'] ?? null,
            'phone' => $data['phone'],
            'phone_secondary' => $data['phone_secondary'] ?? null,
            'region_id' => $data['region_id'],
            'sub_region_id' => $data['sub_region_id'],
            'address' => $data['address'] ?? null,
            'admin_notes' => $data['admin_notes'] ?? null,
            'status' => PharmacyStatus::Active,
            'current_balance' => 0,
            'created_by' => $actor->id,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Pharmacy $pharmacy, array $data): Pharmacy
    {
        if (isset($data['region_id'], $data['sub_region_id'])) {
            $region = Region::query()->findOrFail($data['region_id']);
            $this->regionService->assertRegionIsActive($region);
            $this->regionService->assertSubRegionBelongsToRegion($region->id, $data['sub_region_id']);
        }

        $pharmacy->fill([
            'name' => $data['name'] ?? $pharmacy->name,
            'responsible' => $data['responsible'] ?? $pharmacy->responsible,
            'phone' => $data['phone'] ?? $pharmacy->phone,
            'phone_secondary' => $data['phone_secondary'] ?? $pharmacy->phone_secondary,
            'region_id' => $data['region_id'] ?? $pharmacy->region_id,
            'sub_region_id' => $data['sub_region_id'] ?? $pharmacy->sub_region_id,
            'address' => $data['address'] ?? $pharmacy->address,
            'admin_notes' => $data['admin_notes'] ?? $pharmacy->admin_notes,
        ])->save();

        return $pharmacy->refresh()->load(['region', 'subRegion']);
    }

    public function suspend(Pharmacy $pharmacy, string $reason): Pharmacy
    {
        if ($pharmacy->status !== PharmacyStatus::Active) {
            throw ValidationException::withMessages(['status' => ['Pharmacy is not active.']]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => ['Suspend reason is required.']]);
        }

        $pharmacy->update(['status' => PharmacyStatus::Suspended]);

        return $pharmacy->refresh();
    }

    public function activate(Pharmacy $pharmacy): Pharmacy
    {
        if ($pharmacy->status !== PharmacyStatus::Suspended) {
            throw ValidationException::withMessages(['status' => ['Pharmacy is not suspended.']]);
        }

        $pharmacy->update(['status' => PharmacyStatus::Active]);

        return $pharmacy->refresh();
    }

    public function archive(Pharmacy $pharmacy): Pharmacy
    {
        $pharmacy->update(['status' => PharmacyStatus::Archived]);
        $pharmacy->delete();

        return $pharmacy->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{confirmed: Collection<int, Pharmacy>, possible: Collection<int, Pharmacy>}
     */
    public function findDuplicates(array $data): array
    {
        $query = Pharmacy::query()
            ->where('name', $data['name'])
            ->where('phone', $data['phone'])
            ->where('region_id', $data['region_id']);

        $confirmed = (clone $query)
            ->when(
                filled($data['address'] ?? null),
                fn ($builder) => $builder->where('address', $data['address']),
                fn ($builder) => $builder->whereNull('address'),
            )
            ->get();

        $possible = (clone $query)
            ->whereNotIn('id', $confirmed->pluck('id'))
            ->get();

        return ['confirmed' => $confirmed, 'possible' => $possible];
    }
}
