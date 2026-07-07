<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ProductBaseOfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'scientific_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'purchase_date' => ['required', 'date'],
            'production_date' => ['required', 'date'],
            'expiry_date' => ['required', 'date', 'after:production_date'],
            'rep_visible' => ['sometimes', 'boolean'],
            'base_offer' => ['sometimes', 'nullable', 'array'],
            'base_offer.required_qty' => ['required_with:base_offer', 'integer', 'min:1'],
            'base_offer.bonus_qty' => ['required_with:base_offer', 'integer', 'min:0'],
            'base_offer.status' => ['sometimes', Rule::enum(ProductBaseOfferStatus::class)],
        ];
    }
}
