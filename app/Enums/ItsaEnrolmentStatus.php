<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MTD ITSA enrolment state for an instructor — stored on `instructors.mtd_itsa_status`.
 *
 * OAuth success ≠ MTD ITSA enrolment. The user must separately register for SA,
 * have submitted a return recently, and have signed up each income source for
 * MTD via gov.uk. We resolve this state by calling Business Details and
 * inspecting the response. `not_authorised` / `missing_scope` are persisted
 * check failures so the UI does not keep claiming the check has never run.
 */
enum ItsaEnrolmentStatus: string
{
    case Unknown = 'unknown';
    case NotSignedUp = 'not_signed_up';
    case IncomeSourceMissing = 'income_source_missing';
    case NotAuthorised = 'not_authorised';
    case MissingScope = 'missing_scope';
    case SignedUpVoluntary = 'signed_up_voluntary';
    case Mandated = 'mandated';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Enrolment not checked yet',
            self::NotSignedUp => 'Not signed up to MTD ITSA',
            self::IncomeSourceMissing => 'No business signed up for MTD',
            self::NotAuthorised => 'HMRC is not authorised for this NI number',
            self::MissingScope => 'Income Tax permissions not granted',
            self::SignedUpVoluntary => 'Signed up (voluntary)',
            self::Mandated => 'Signed up (mandated)',
        };
    }

    public function canSubmit(): bool
    {
        return match ($this) {
            self::SignedUpVoluntary, self::Mandated => true,
            default => false,
        };
    }
}
