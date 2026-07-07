<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Archived => 'مؤرشف',
        };
    }
}
