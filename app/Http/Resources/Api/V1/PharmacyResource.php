<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Pharmacy */
class PharmacyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'responsible' => $this->responsible,
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'region_id' => $this->region_id,
            'sub_region_id' => $this->sub_region_id,
            'region' => $this->whenLoaded('region', fn () => [
                'id' => $this->region?->id,
                'name' => $this->region?->name,
            ]),
            'sub_region' => $this->whenLoaded('subRegion', fn () => [
                'id' => $this->subRegion?->id,
                'name' => $this->subRegion?->name,
            ]),
            'address' => $this->address,
            'admin_notes' => $this->admin_notes,
            'status' => $this->status->value,
            'current_balance' => $this->current_balance,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
