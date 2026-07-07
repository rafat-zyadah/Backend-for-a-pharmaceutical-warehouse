<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RegionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('regions.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(RegionStatus::class)],
        ];
    }
}
