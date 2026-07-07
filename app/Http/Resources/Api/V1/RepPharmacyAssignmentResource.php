<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RepPharmacyAssignment */
class RepPharmacyAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rep_id' => $this->rep_id,
            'pharmacy_id' => $this->pharmacy_id,
            'rep' => $this->whenLoaded('rep', fn () => [
                'id' => $this->rep?->id,
                'name' => $this->rep?->name,
            ]),
            'pharmacy' => $this->whenLoaded('pharmacy', fn () => new PharmacyResource($this->pharmacy)),
            'status' => $this->status->value,
            'is_primary' => $this->is_primary,
            'is_shared' => $this->when(
                isset($this->is_shared),
                fn () => (bool) $this->is_shared,
            ),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
        ];
    }
}
