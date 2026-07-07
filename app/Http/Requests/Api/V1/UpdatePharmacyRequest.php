<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pharmacies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'responsible' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'phone_secondary' => ['sometimes', 'nullable', 'string', 'max:32'],
            'region_id' => ['sometimes', 'uuid', 'exists:regions,id'],
            'sub_region_id' => ['sometimes', 'uuid', 'exists:sub_regions,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
