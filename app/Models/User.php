<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property Carbon|null $birth_date
 * @property Carbon|null $suspended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'username',
        'name',
        'phone',
        'email',
        'avatar_url',
        'residence',
        'province',
        'birth_date',
        'role',
        'status',
        'password',
        'suspended_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'password' => 'encrypted',
            'birth_date' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSupervisor(): bool
    {
        return $this->role === UserRole::Supervisor;
    }

    public function isEmployee(): bool
    {
        return in_array($this->role, [UserRole::Rep, UserRole::Invoicer], true);
    }

    public function passwordVisibleTo(User $viewer): bool
    {
        return $viewer->can('viewPassword', $this);
    }

    /** @param Builder<self> $query */
    public function scopeRole(Builder $query, UserRole $role): Builder
    {
        return $query->where('role', $role);
    }

    /** @param Builder<self> $query */
    public function scopeStatus(Builder $query, UserStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /** @return HasMany<RepRegionAssignment, $this> */
    public function regionAssignments(): HasMany
    {
        return $this->hasMany(RepRegionAssignment::class, 'rep_id');
    }

    /** @return HasMany<RepPharmacyAssignment, $this> */
    public function pharmacyAssignments(): HasMany
    {
        return $this->hasMany(RepPharmacyAssignment::class, 'rep_id');
    }

    /** @return HasMany<RepRegionAssignment, $this> */
    public function activeRegionAssignments(): HasMany
    {
        return $this->hasMany(RepRegionAssignment::class, 'rep_id')
            ->where('status', \App\Enums\AssignmentStatus::Active);
    }

    /** @return HasMany<RepPharmacyAssignment, $this> */
    public function activePharmacyAssignments(): HasMany
    {
        return $this->hasMany(RepPharmacyAssignment::class, 'rep_id')
            ->where('status', \App\Enums\AssignmentStatus::Active);
    }
}
