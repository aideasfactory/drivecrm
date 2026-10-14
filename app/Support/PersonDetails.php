<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Shared validation rules for names, UK mobiles and dates of birth captured on
 * the public booking forms. Mirrored client-side in
 * resources/js/lib/personDetailsValidation.ts — keep both in sync.
 */
final class PersonDetails
{
    /** Letters (any script), spaces, hyphens and straight/curly apostrophes; must start with a letter. */
    public const string NAME_PATTERN = "/^\p{L}[\p{L}\p{M}\s'’-]*$/u";

    public const string UK_MOBILE_PATTERN = '/^\+447\d{9}$/';

    public static function minimumAge(): int
    {
        return (int) config('onboarding.learner_minimum_age', 17);
    }

    /**
     * Latest date of birth that still meets the minimum age today.
     */
    public static function latestDateOfBirth(): string
    {
        return CarbonImmutable::today()->subYears(self::minimumAge())->toDateString();
    }

    /**
     * Normalise a UK mobile into E.164 (+447XXXXXXXXX), ignoring spaces, dashes
     * and brackets. Accepts 07…, 7…, 447… and +447… (including "+44 07…").
     * Anything unrecognisable is returned stripped so validation can reject it.
     */
    public static function normaliseUkMobile(string $phone): string
    {
        $stripped = (string) preg_replace('/[\s\-().]/', '', $phone);

        if (preg_match('/^(?:(?:\+|00)?440?|0)?(7\d{9})$/', $stripped, $matches)) {
            return '+44'.$matches[1];
        }

        return $stripped;
    }

    /**
     * @return array<int, string>
     */
    public static function nameRules(): array
    {
        return ['bail', 'required', 'string', 'max:100', 'regex:'.self::NAME_PATTERN];
    }

    /**
     * @return array<int, string>
     */
    public static function emailRules(): array
    {
        return ['bail', 'required', 'string', 'max:255', 'email:rfc', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/'];
    }

    /**
     * @return array<int, string>
     */
    public static function ukMobileRules(): array
    {
        return ['bail', 'required', 'string', 'regex:'.self::UK_MOBILE_PATTERN];
    }

    /**
     * @return array<int, string>
     */
    public static function dateOfBirthRules(): array
    {
        return [
            'bail',
            'required',
            'date_format:Y-m-d',
            'before:tomorrow',
            'before_or_equal:'.self::latestDateOfBirth(),
        ];
    }
}
