<?php

namespace App\Enums;

enum EligibilityStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Eligible => 'Eligible',
            self::Ineligible => 'Ineligible',
        };
    }
}
