<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TransferRegionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('distribution.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from_rep_id' => ['required', 'uuid', 'exists:users,id'],
            'to_rep_id' => ['required', 'uuid', 'exists:users,id', 'different:from_rep_id'],
            'region_id' => ['required', 'uuid', 'exists:regions,id'],
            'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
