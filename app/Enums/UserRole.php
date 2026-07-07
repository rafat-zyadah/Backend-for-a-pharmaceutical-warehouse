<?php

namespace App\Enums;

enum UserRole: string
{
    case Supervisor = 'supervisor';
    case Invoicer = 'invoicer';
    case Rep = 'rep';

    public function label(): string
    {
        return match ($this) {
            self::Supervisor => 'مشرف',
            self::Invoicer => 'مفوتر',
            self::Rep => 'مندوب',
        };
    }

    public function allowedPlatform(): ClientPlatform
    {
        return match ($this) {
            self::Supervisor => ClientPlatform::Web,
            self::Invoicer => ClientPlatform::Desktop,
            self::Rep => ClientPlatform::Mobile,
        };
    }
}
