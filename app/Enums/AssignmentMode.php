<?php

namespace App\Enums;

enum AssignmentMode: string
{
    case Add = 'add';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Add => 'إضافة',
            self::Transfer => 'نقل',
        };
    }
}
