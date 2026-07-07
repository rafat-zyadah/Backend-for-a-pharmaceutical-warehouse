<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class RepDistributionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'role' => UserRole::Rep->value,
            'regions_count' => $this->whenCounted('activeRegionAssignments'),
            'pharmacies_count' => $this->whenCounted('activePharmacyAssignments'),
            'shared_pharmacies_count' => $this->when(
                isset($this->shared_pharmacies_count),
                fn () => $this->shared_pharmacies_count,
            ),
            'regions' => RepRegionAssignmentResource::collection($this->whenLoaded('activeRegionAssignments')),
            'pharmacies' => RepPharmacyAssignmentResource::collection($this->whenLoaded('activePharmacyAssignments')),
        ];
    }
}
