<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('companies.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var \App\Models\Company $company */
        $company = $this->route('company');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($company->id)],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_info' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
