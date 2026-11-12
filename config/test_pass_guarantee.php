<?php

declare(strict_types=1);

/*
 * Pass Your Test Guarantee.
 *
 * Mandatory on packages of `mandatory_minimum_hours` or more: always added to
 * the order at `price` and shown in the package price, with no opt-out.
 * On smaller packages it is an optional add-on the learner can tick on the
 * payment step, also at `price`. It is never included free.
 * (To claim it, the pupil must still complete 10+ hours with DRIVE before
 * their test — see the Terms of Service.)
 *
 * Always read these values through `App\Support\TestPassGuarantee`.
 */
return [
    'price' => (float) env('TEST_PASS_GUARANTEE_PRICE', 50),

    'mandatory_minimum_hours' => (float) env('TEST_PASS_GUARANTEE_MANDATORY_MINIMUM_HOURS', 10),

    'terms_url' => env('TEST_PASS_GUARANTEE_TERMS_URL', rtrim((string) env('APP_URL'), '/').'/terms-of-service#test-pass-guarantee'),
];
