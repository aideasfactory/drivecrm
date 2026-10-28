<?php

declare(strict_types=1);

namespace App\Enums;

enum LessonCardStatus: string
{
    case SignedOff = 'signed_off';
    case NeedsSignOff = 'needs_sign_off';
    case Current = 'current';
    case Upcoming = 'upcoming';
    case Draft = 'draft';
    /** Kept in the booking to rebook later: no date until the instructor rebooks it. */
    case Banked = 'banked';
}
