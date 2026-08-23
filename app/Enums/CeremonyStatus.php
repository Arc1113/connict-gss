<?php

namespace App\Enums;

enum CeremonyStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Prepared => 'Prepared',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Archived], true);
    }
}
