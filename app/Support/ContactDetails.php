<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validation rules shared by the public booking flows for booker and learner
 * contact details. Mirrored client-side in resources/js/lib/contactValidation.ts,
 * so keep the patterns in sync.
 */
final class ContactDetails
{
    /**
     * Letters (any script), spaces, hyphens and straight or curly apostrophes.
     * iOS inserts a curly apostrophe (’) by default.
     */
    public const NAME_REGEX = '/^\p{L}[\p{L}\p{M}\s\'’\-]*$/u';

    public const UK_MOBILE_REGEX = '/^\+447\d{9}$/';

    public static function learnerMinimumAge(): int
    {
        return (int) config('onboarding.learner_minimum_age', 17);
    }

    /**
     * Convert a UK mobile typed as 07… or +447… (whitespace ignored) to
     * E.164. Anything else is returned with whitespace stripped so the
     * regex rule rejects it.
     */
    public static function normaliseUkMobile(string $phone): string
    {
        $phone = (string) preg_replace('/\s+/u', '', $phone);

        if (preg_match('/^07\d{9}$/', $phone) === 1) {
            return '+44'.substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Normalise a UK-flavoured phone number into E.164 (e.g. "+447545399797").
     *
     * The booking forms are UK-only, so bare numbers are assumed to be GB.
     * Returns null when the result isn't a plausible E.164 length (8–15 digits).
     */
    public static function toE164(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = (string) preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if ($hasPlus) {
            $normalised = '+'.$digits;
        } elseif (str_starts_with($digits, '00')) {
            $normalised = '+'.substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $normalised = '+44'.substr($digits, 1);
        } elseif (str_starts_with($digits, '44')) {
            $normalised = '+'.$digits;
        } else {
            $normalised = '+44'.$digits;
        }

        $length = strlen($normalised) - 1;

        if ($length < 8 || $length > 15) {
            return null;
        }

        return $normalised;
    }
}
