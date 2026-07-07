<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'rep' => $this->whenLoaded('rep', fn () => [
                'id' => $this->rep?->id,
                'name' => $this->rep?->name,
            ]),
            'pharmacy' => $this->whenLoaded('pharmacy', fn () => new PharmacyResource($this->pharmacy)),
            'region' => $this->whenLoaded('region', fn () => [
                'id' => $this->region?->id,
                'name' => $this->region?->name,
            ]),
            'sub_region' => $this->whenLoaded('subRegion', fn () => [
                'id' => $this->subRegion?->id,
                'name' => $this->subRegion?->name,
            ]),
            'rep_notes' => $this->rep_notes,
            'invoicer_notes' => $this->invoicer_notes,
            'rejection_reason' => $this->rejection_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'original_snapshot' => $this->when(
                $request->user()?->can('orders.manage'),
                $this->original_snapshot,
            ),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
