<?php

namespace App\Enums;

enum RegionStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعالة',
            self::Inactive => 'غير فعالة',
        };
    }
}
