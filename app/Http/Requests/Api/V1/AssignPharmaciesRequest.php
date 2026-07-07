<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignPharmaciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('distribution.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pharmacy_ids' => ['required', 'array', 'min:1'],
            'pharmacy_ids.*' => ['uuid', 'exists:pharmacies,id'],
            'mode' => ['sometimes', Rule::enum(AssignmentMode::class)],
            'start_date' => ['sometimes', 'date'],
        ];
    }
}
