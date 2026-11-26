<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Head Office's answer, at a pupil transfer, for a past lesson that was never
 * signed off: did it take place?
 */
enum TransferLessonDecision: string
{
    /** It took place: it stays with the old instructor, who signs it off and is paid. */
    case Happened = 'happened';

    /** It was missed: the pupil keeps it, unscheduled, with the new instructor. */
    case Missed = 'missed';
}
