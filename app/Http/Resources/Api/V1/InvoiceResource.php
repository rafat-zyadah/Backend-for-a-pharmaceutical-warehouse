<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Invoice */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'shipment_number' => $this->shipment_number,
            'invoice_type' => $this->invoice_type->value,
            'status' => $this->status->value,
            'return_status' => $this->return_status->value,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
