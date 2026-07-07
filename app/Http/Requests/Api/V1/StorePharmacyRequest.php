<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StorePharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pharmacies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'phone_secondary' => ['nullable', 'string', 'max:32'],
            'region_id' => ['required', 'uuid', 'exists:regions,id'],
            'sub_region_id' => ['required', 'uuid', 'exists:sub_regions,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
