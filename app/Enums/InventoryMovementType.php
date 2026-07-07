<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case StockIn = 'stock_in';
    case Sale = 'sale';
    case Bonus = 'bonus';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'إدخال مخزون',
            self::Sale => 'بيع',
            self::Bonus => 'بونص',
            self::Return => 'مرتجع',
            self::Adjustment => 'تعديل مخزون',
        };
    }
}
