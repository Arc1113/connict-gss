<?php

namespace App\Enums;

enum PresentationMode: string
{
    case Automatic = 'automatic';
    case Controlled = 'controlled';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Controlled => 'Controlled',
        };
    }
}
