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
    public const NAME_REGEX = "/^\p{L}[\p{L}\p{M}\s'’\-]*$/u";

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
}
