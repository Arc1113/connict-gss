<?php

namespace App\Enums;

enum AnnouncementProvider: string
{
    case Azure = 'azure';
    case Kokoro = 'kokoro';
}
