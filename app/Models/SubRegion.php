<?php

namespace App\Models;

use App\Enums\RegionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubRegion extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'region_id',
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegionStatus::class,
        ];
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<Pharmacy, $this> */
    public function pharmacies(): HasMany
    {
        return $this->hasMany(Pharmacy::class);
    }
}
