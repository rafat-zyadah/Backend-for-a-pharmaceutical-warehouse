<?php

namespace App\Enums;

enum PharmacyStatus: string
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
