<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RegionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('regions.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var \App\Models\Region $region */
        $region = $this->route('region');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('regions', 'name')->ignore($region->id)],
            'status' => ['sometimes', Rule::enum(RegionStatus::class)],
        ];
    }
}
