<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_number',
        'rep_id',
        'pharmacy_id',
        'region_id',
        'sub_region_id',
        'status',
        'rep_notes',
        'invoicer_notes',
        'rejection_reason',
        'cancellation_reason',
        'subtotal',
        'discount_total',
        'total',
        'original_snapshot',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'original_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    /** @return BelongsTo<Pharmacy, $this> */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
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

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function hasInvoicedShipments(): bool
    {
        return $this->invoices()->exists();
    }
}
