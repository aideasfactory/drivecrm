<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Student;
use App\Models\User;

/**
 * Who may book a cash order (the pupil pays the instructor in cash at each
 * lesson, with no booking or digital fee). Cash is only for pupils whose
 * cash payments an owner has turned on (off by default, including straight
 * after import), and only owners with full admin access can choose it: not
 * the bookings team (restricted owners), instructors or pupils.
 */
final class CashBookings
{
    public static function allowedFor(?User $bookedBy, Student $student): bool
    {
        return $bookedBy !== null
            && $bookedBy->hasFullOwnerAccess()
            && $student->cash_payments_enabled === true;
    }
}
