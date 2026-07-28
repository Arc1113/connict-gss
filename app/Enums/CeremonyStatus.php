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
}
