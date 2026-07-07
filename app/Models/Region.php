<?php

namespace App\Models;

use App\Enums\RegionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegionStatus::class,
        ];
    }

    /** @return HasMany<SubRegion, $this> */
    public function subRegions(): HasMany
    {
        return $this->hasMany(SubRegion::class);
    }

    /** @return HasMany<Pharmacy, $this> */
    public function pharmacies(): HasMany
    {
        return $this->hasMany(Pharmacy::class);
    }
}
