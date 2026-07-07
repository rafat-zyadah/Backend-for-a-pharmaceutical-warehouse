<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOrderShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.order_item_id' => ['required_with:lines', 'uuid'],
            'lines.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'lines.*.bonus_qty' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
