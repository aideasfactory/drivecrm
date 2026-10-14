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
 *
 * `entry_hosts` are public hostnames whose site root (/) enters /onboarding.
 * Any other host — including app.just-drive.co.uk — keeps the redirect from
 * / to /booking. Override with a comma-separated ONBOARDING_ENTRY_HOSTS list
 * when a non-production hostname should follow the Drive Plus entry.
 *
 * `learner_minimum_age` is the youngest learner the booking form accepts when
 * booking for someone else (Terms of Service: at least 17). Read by
 * App\Support\ContactDetails.
 */
return [
    'learner_minimum_age' => (int) env('ONBOARDING_LEARNER_MINIMUM_AGE', 17),

    'excluded_instructor_ids' => env('ONBOARDING_EXCLUDED_INSTRUCTOR_IDS', ''),

    'entry_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('ONBOARDING_ENTRY_HOSTS', 'app.drive-plus.co.uk')),
    ))),
];
