<?php

namespace App\Enums;

enum InvoiceReturnStatus: string
{
    case None = 'none';
    case PartiallyReturned = 'partially_returned';
    case FullyReturned = 'fully_returned';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بدون مرتجع',
            self::PartiallyReturned => 'مرتجعة جزئياً',
            self::FullyReturned => 'مرتجعة بالكامل',
        };
    }
}
