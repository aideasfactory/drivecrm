<?php

declare(strict_types=1);

/*
 * Pass Your Test Guarantee add-on for the public booking form.
 *
 * Bookings of `free_minimum_hours` or more that are paid in full get the
 * guarantee free. Anyone else (weekly payers, smaller packages) can opt in on
 * the payment step for `price`.
 *
 * Always read these values through `App\Support\TestPassGuarantee`.
 */
return [
    'price' => (float) env('TEST_PASS_GUARANTEE_PRICE', 50),

    'free_minimum_hours' => (float) env('TEST_PASS_GUARANTEE_FREE_MINIMUM_HOURS', 10),

    'terms_url' => env('TEST_PASS_GUARANTEE_TERMS_URL', 'https://just-drive.co.uk/learner-terms-and-conditions/'),
];
