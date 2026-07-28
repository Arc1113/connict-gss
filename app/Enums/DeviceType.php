<?php

namespace App\Enums;

enum DeviceType: string
{
    case Server = 'server';
    case Scanner = 'scanner';
    case Stage = 'stage';
    case Control = 'control';
}
