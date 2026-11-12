<?php

declare(strict_types=1);

/*
 * Pass Your Test Guarantee add-on for the public booking form.
 *
 * Always charged at `price` and never included free. Packages with
 * `mandatory_minimum_lessons` or more lessons (5 lessons = 10 hours) always
 * include it; smaller packages can opt in on the payment step. Weekly payers
 * pay it with their first instalment.
 * (To claim it, the pupil must still complete 10+ hours with DRIVE before
 * their test — see the Terms of Service.)
 *
 * Always read these values through `App\Support\TestPassGuarantee`.
 */
return [
    'price' => (float) env('TEST_PASS_GUARANTEE_PRICE', 50),

    'mandatory_minimum_lessons' => (int) env('TEST_PASS_GUARANTEE_MANDATORY_MINIMUM_LESSONS', 5),

    'terms_url' => env('TEST_PASS_GUARANTEE_TERMS_URL', rtrim((string) env('APP_URL'), '/').'/terms-of-service#test-pass-guarantee'),
];
