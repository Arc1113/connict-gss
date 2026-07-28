<?php

namespace App\Enums;

enum ScanResult: string
{
    case Accepted = 'accepted';
    case Invalid = 'invalid';
    case Duplicate = 'duplicate';
    case Ineligible = 'ineligible';
    case Rejected = 'rejected';
}
