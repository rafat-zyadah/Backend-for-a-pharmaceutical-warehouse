<?php

namespace App\Models;

use App\Enums\InvoiceReturnStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_number',
        'order_id',
        'shipment_number',
        'invoice_type',
        'rep_id',
        'pharmacy_id',
        'region_id',
        'status',
        'return_status',
        'subtotal',
        'discount_total',
        'total',
        'balance_before',
        'balance_after',
        'pdf_extracted_at',
        'printed_at',
        'print_count',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'return_status' => InvoiceReturnStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'pdf_extracted_at' => 'datetime',
            'printed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Pharmacy, $this> */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /** @return BelongsTo<User, $this> */
    public function rep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
