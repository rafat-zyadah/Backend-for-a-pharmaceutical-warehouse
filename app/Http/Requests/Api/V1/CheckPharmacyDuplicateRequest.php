<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckPharmacyDuplicateRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:32'],
            'region_id' => ['required', 'uuid', 'exists:regions,id'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
