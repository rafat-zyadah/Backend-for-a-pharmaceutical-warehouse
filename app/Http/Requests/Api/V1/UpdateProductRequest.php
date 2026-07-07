<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProductBaseOfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'scientific_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity' => ['prohibited'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'purchase_date' => ['sometimes', 'date'],
            'production_date' => ['sometimes', 'date'],
            'expiry_date' => ['sometimes', 'date'],
            'rep_visible' => ['sometimes', 'boolean'],
            'base_offer' => ['sometimes', 'nullable', 'array'],
            'base_offer.required_qty' => ['required_with:base_offer', 'integer', 'min:1'],
            'base_offer.bonus_qty' => ['required_with:base_offer', 'integer', 'min:0'],
            'base_offer.status' => ['sometimes', Rule::enum(ProductBaseOfferStatus::class)],
        ];
    }
}
