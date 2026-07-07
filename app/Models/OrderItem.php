<?php

namespace App\Models;

use App\Enums\OfferSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'product_id',
        'company_id',
        'product_name',
        'scientific_name',
        'quantity',
        'quantity_invoiced',
        'bonus_qty',
        'bonus_qty_invoiced',
        'unit_price',
        'discount',
        'line_total',
        'offer_source',
        'promo_snapshot',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'offer_source' => OfferSource::class,
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'promo_snapshot' => 'array',
            'expiry_date' => 'date',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->quantity_invoiced);
    }

    public function remainingBonusQuantity(): int
    {
        return max(0, $this->bonus_qty - $this->bonus_qty_invoiced);
    }

    public function isFullyInvoiced(): bool
    {
        return $this->remainingQuantity() === 0;
    }
}
