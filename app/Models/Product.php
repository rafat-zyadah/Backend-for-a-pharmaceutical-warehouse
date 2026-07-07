<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Product extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'name',
        'scientific_name',
        'quantity',
        'price',
        'purchase_date',
        'production_date',
        'expiry_date',
        'rep_visible',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'purchase_date' => 'date',
            'production_date' => 'date',
            'expiry_date' => 'date',
            'rep_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<ProductBaseOffer, $this> */
    public function baseOffer(): HasOne
    {
        return $this->hasOne(ProductBaseOffer::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date instanceof Carbon
            && $this->expiry_date->isPast();
    }
}
