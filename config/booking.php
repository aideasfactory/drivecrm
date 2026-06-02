<?php

declare(strict_types=1);

/*
 * Public /booking landing flow.
 *
 * `instructor_ids` maps the user's selected transmission to the instructor whose
 * coverage area gates the "We have lessons in your area" result on step 2.
 * Read by App\Http\Controllers\Booking\StepTwoController.
 */
return [
    'instructor_ids' => [
        'manual' => env('BOOKING_INSTRUCTOR_MANUAL_ID'),
        'automatic' => env('BOOKING_INSTRUCTOR_AUTOMATIC_ID'),
        'both' => env('BOOKING_INSTRUCTOR_BOTH_ID'),
    ],

    /*
     * Recipient for the admin notification emailed when a /booking enquiry
     * reaches step 2 (the coverage-check result). If null/empty, no email
     * is sent and a debug entry is logged.
     */
    'admin_email' => env('ADMIN_EMAIL'),
];
