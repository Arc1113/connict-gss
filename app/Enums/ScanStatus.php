<?php

namespace App\Enums;

use Symfony\Component\HttpFoundation\Response;

enum ScanStatus: string
{
    case Queued = 'queued';
    case InvalidToken = 'invalid_token';
    case CeremonyInactive = 'ceremony_inactive';
    case StudentIneligible = 'student_ineligible';
    case AlreadyQueued = 'already_queued';
    case AlreadyPresenting = 'already_presenting';
    case AlreadyCompleted = 'already_completed';
    case SkippedRequiresStageAction = 'skipped_requires_stage_action';
    case Unauthorized = 'unauthorized';
    case RequestFailed = 'request_failed';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Queued => Response::HTTP_CREATED,
            self::InvalidToken, self::StudentIneligible => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::CeremonyInactive,
            self::AlreadyQueued,
            self::AlreadyPresenting,
            self::AlreadyCompleted,
            self::SkippedRequiresStageAction => Response::HTTP_CONFLICT,
            self::Unauthorized => Response::HTTP_FORBIDDEN,
            self::RequestFailed => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
