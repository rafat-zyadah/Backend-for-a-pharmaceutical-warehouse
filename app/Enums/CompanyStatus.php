<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعالة',
            self::Suspended => 'موقوفة',
            self::Archived => 'مؤرشفة',
        };
    }
}
