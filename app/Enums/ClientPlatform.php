<?php

namespace App\Enums;

enum ClientPlatform: string
{
    case Web = 'web';
    case Desktop = 'desktop';
    case Mobile = 'mobile';

    public static function fromHeader(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
