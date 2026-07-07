<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    private bool $includePassword = false;

    public function withPassword(bool $include = true): self
    {
        $this->includePassword = $include;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canViewPassword = $this->includePassword
            && $viewer !== null
            && $this->resource->passwordVisibleTo($viewer);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'residence' => $this->residence,
            'province' => $this->province,
            'birth_date' => $this->birth_date?->toDateString(),
            'role' => $this->role->value,
            'status' => $this->status->value,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'password' => $this->when($canViewPassword, fn () => $this->password),
        ];
    }
}
