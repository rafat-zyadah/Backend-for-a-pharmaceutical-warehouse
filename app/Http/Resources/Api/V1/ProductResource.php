<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserRole;
use App\Support\Products\ProductAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    private bool $hideQuantity = false;

    public function hideQuantity(bool $hide = true): self
    {
        $this->hideQuantity = $hide;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $shouldHideQuantity = $this->hideQuantity
            || $request->user()?->role === UserRole::Rep;

        $data = [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'name' => $this->name,
            'scientific_name' => $this->scientific_name,
            'price' => $this->price,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'production_date' => $this->production_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'rep_visible' => $this->rep_visible,
            'status' => $this->status->value,
            'availability' => ProductAvailability::availability($this->quantity),
            'expiry_status' => $this->expiry_date
                ? ProductAvailability::expiryStatus($this->expiry_date)
                : null,
            'base_offer' => $this->whenLoaded('baseOffer', fn () => $this->baseOffer ? [
                'required_qty' => $this->baseOffer->required_qty,
                'bonus_qty' => $this->baseOffer->bonus_qty,
                'status' => $this->baseOffer->status->value,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if (! $shouldHideQuantity) {
            $data['quantity'] = $this->quantity;
        }

        return $data;
    }
}
