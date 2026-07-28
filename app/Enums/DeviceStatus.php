<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Disabled = 'disabled';
}
