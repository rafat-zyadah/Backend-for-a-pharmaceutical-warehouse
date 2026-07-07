<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Active = 'active';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال',
            self::Ended => 'منتهي',
        };
    }
}
