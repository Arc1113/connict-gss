<?php

namespace App\Enums;

use Symfony\Component\HttpFoundation\Response;

enum ScanStatus: string
{
    case Accepted = 'accepted';
    case InvalidToken = 'invalid_token';
    case CeremonyInactive = 'ceremony_inactive';
    case StudentIneligible = 'student_ineligible';
    case DuplicateActive = 'duplicate_active';
    case AlreadyCompleted = 'already_completed';
    case StageBusy = 'stage_busy';
    case Unauthorized = 'unauthorized';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Accepted => Response::HTTP_CREATED,
            self::InvalidToken, self::StudentIneligible => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::CeremonyInactive,
            self::DuplicateActive,
            self::AlreadyCompleted,
            self::StageBusy => Response::HTTP_CONFLICT,
            self::Unauthorized => Response::HTTP_FORBIDDEN,
        };
    }
}
