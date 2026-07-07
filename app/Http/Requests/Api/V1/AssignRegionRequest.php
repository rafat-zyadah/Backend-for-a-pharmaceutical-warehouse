<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\AssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('distribution.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'region_id' => ['required', 'uuid', 'exists:regions,id'],
            'mode' => ['sometimes', Rule::enum(AssignmentMode::class)],
            'start_date' => ['sometimes', 'date'],
        ];
    }
}
