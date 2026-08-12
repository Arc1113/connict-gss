<?php

namespace App\Enums;

enum PresentationMode: string
{
    case Automatic = 'automatic';
    case FullyAutomatic = 'fully_automatic';
    case Controlled = 'controlled';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Assisted automatic',
            self::FullyAutomatic => 'Fully automatic',
            self::Controlled => 'Controlled',
        };
    }
}
