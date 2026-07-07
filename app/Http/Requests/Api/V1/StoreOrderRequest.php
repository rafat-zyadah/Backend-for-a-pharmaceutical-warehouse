<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\OfferSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.submit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pharmacy_id' => ['required', 'uuid', 'exists:pharmacies,id'],
            'rep_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount' => ['sometimes', 'numeric', 'min:0'],
            'items.*.offer_source' => ['sometimes', Rule::enum(OfferSource::class)],
            'items.*.bonus_qty' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
