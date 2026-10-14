<?php

declare(strict_types=1);

/*
 * Public school contact details shown on the booking and onboarding
 * checkout (sidebar, header, and contact actions).
 *
 * One value: config('contact.school'). Display phone may include spaces;
 * tel: links strip them. Override with the env vars below when needed.
 */
return [
    'school' => [
        'name' => env('SCHOOL_NAME', 'DRIVE Driving School'),
        'location' => env('SCHOOL_LOCATION', 'United Kingdom'),
        'phone' => env('SCHOOL_PHONE', '0800 368 9215'),
        'email' => env('SCHOOL_EMAIL', 'lessons@just-drive.co.uk'),
    ],
];
