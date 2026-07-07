<?php

namespace App\Support\Products;

use Illuminate\Support\Carbon;

class ProductAvailability
{
    public static function availability(int $quantity): string
    {
        if ($quantity === 0) {
            return 'out_of_stock';
        }

        $threshold = (int) config('pharmacy.low_stock_threshold', 100);

        return $quantity < $threshold ? 'low_stock' : 'available';
    }

    public static function expiryStatus(Carbon $expiryDate): string
    {
        if ($expiryDate->isPast()) {
            return 'expired';
        }

        if ($expiryDate->lte(now()->addDays(90))) {
            return 'near_expiry';
        }

        return 'valid';
    }
}
