<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target !== null && ($this->user()?->can('update', $target) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var \App\Models\User $target */
        $target = $this->route('user');
        $requiresEmployeeProfile = in_array($target->role, [UserRole::Rep, UserRole::Invoicer], true);

        return [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($target->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($target->id),
            ],
            'password' => ['sometimes', 'string', 'min:6'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'residence' => [$requiresEmployeeProfile ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255'],
            'province' => [$requiresEmployeeProfile ? 'sometimes' : 'nullable', 'nullable', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
