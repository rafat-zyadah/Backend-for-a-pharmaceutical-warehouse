<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OfferSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'invoicer_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.order_item_id' => ['sometimes', 'uuid'],
            'items.*.product_id' => ['required_with:items', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'items.*.discount' => ['sometimes', 'numeric', 'min:0'],
            'items.*.bonus_qty' => ['sometimes', 'integer', 'min:0'],
            'items.*.offer_source' => ['sometimes', Rule::enum(OfferSource::class)],
        ];
    }
}
