<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MTD ITSA enrolment state for an instructor — stored on `instructors.mtd_itsa_status`.
 *
 * OAuth success ≠ MTD ITSA enrolment. The user must separately register for SA,
 * have submitted a return recently, and have signed up each income source for
 * MTD via gov.uk. We resolve this state by calling Business Details and
 * inspecting the response.
 */
enum ItsaEnrolmentStatus: string
{
    case Unknown = 'unknown';
    case NotSignedUp = 'not_signed_up';
    case IncomeSourceMissing = 'income_source_missing';
    case SignedUpVoluntary = 'signed_up_voluntary';
    case Mandated = 'mandated';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Checking your MTD enrolment…',
            self::NotSignedUp => 'Not signed up to MTD ITSA',
            self::IncomeSourceMissing => 'No business signed up for MTD',
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
