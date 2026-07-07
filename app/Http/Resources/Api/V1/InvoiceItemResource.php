<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'company_name' => $this->company_name,
            'quantity' => $this->quantity,
            'bonus_qty' => $this->bonus_qty,
            'unit_price' => $this->unit_price,
            'discount' => $this->discount,
            'line_total' => $this->line_total,
            'offer_source' => $this->offer_source->value,
            'promo_snapshot' => $this->promo_snapshot,
        ];
    }
}
