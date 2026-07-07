<?php

namespace App\Models;

use App\Enums\PharmacyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pharmacy extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'responsible',
        'phone',
        'phone_secondary',
        'region_id',
        'sub_region_id',
        'address',
        'admin_notes',
        'status',
        'current_balance',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PharmacyStatus::class,
            'current_balance' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<SubRegion, $this> */
    public function subRegion(): BelongsTo
    {
        return $this->belongsTo(SubRegion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<RepPharmacyAssignment, $this> */
    public function repAssignments(): HasMany
    {
        return $this->hasMany(RepPharmacyAssignment::class);
    }

    /** @return HasMany<RepPharmacyAssignment, $this> */
    public function activeRepAssignments(): HasMany
    {
        return $this->hasMany(RepPharmacyAssignment::class)
            ->where('status', \App\Enums\AssignmentStatus::Active);
    }
}
