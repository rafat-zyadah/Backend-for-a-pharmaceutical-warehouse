<?php

namespace App\Http\Resources\Api\V1;

use App\Support\Products\ProductAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'company_id' => $this->company_id,
            'company_name' => $this->whenLoaded('company', fn () => $this->company?->name),
            'product_name' => $this->product_name,
            'scientific_name' => $this->scientific_name,
            'quantity' => $this->quantity,
            'quantity_invoiced' => $this->quantity_invoiced,
            'remaining_quantity' => $this->remainingQuantity(),
            'bonus_qty' => $this->bonus_qty,
            'bonus_qty_invoiced' => $this->bonus_qty_invoiced,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'line_total' => $this->line_total,
            'offer_source' => $this->offer_source->value,
            'promo_snapshot' => $this->promo_snapshot,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_fully_invoiced' => $this->isFullyInvoiced(),
            'availability' => $product !== null
                ? ProductAvailability::availability($product->quantity)
                : null,
            'stock_quantity' => $this->when(
                $request->user()?->can('orders.manage') && $product !== null,
                fn () => $product?->quantity,
            ),
        ];
    }
}
