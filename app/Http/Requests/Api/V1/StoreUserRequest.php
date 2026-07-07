<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\User::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $role = $this->input('role');
        $requiresEmployeeProfile = in_array($role, [UserRole::Rep->value, UserRole::Invoicer->value], true);

        return [
            'role' => ['required', Rule::enum(UserRole::class)],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'residence' => [Rule::requiredIf($requiresEmployeeProfile), 'nullable', 'string', 'max:255'],
            'province' => [Rule::requiredIf($requiresEmployeeProfile), 'nullable', 'string', 'max:255'],
            'birth_date' => [Rule::requiredIf($requiresEmployeeProfile), 'nullable', 'date'],
        ];
    }
}
