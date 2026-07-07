<?php

namespace App\Enums;

enum InvoiceType: string
{
    case FromOrder = 'from_order';
    case Direct = 'direct';
    case PersonalWithdrawal = 'personal_withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::FromOrder => 'من طلبية',
            self::Direct => 'مبيعات مباشرة',
            self::PersonalWithdrawal => 'مسحوبات شخصية',
        };
    }
}
