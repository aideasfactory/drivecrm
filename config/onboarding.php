<?php

declare(strict_types=1);

/*
 * Public /onboarding flow.
 *
 * The postcode instructor search (onboarding steps 2 & 4) automatically
 * excludes the /booking coverage-gate instructors from config/booking.php.
 * `excluded_instructor_ids` is an optional comma-separated list of ADDITIONAL
 * instructor IDs to hide (e.g. other test accounts with blanket coverage).
 * Read by App\Actions\FindInstructorsByPostcodeSectorAction.
 */
return [
    'excluded_instructor_ids' => env('ONBOARDING_EXCLUDED_INSTRUCTOR_IDS', ''),
];
