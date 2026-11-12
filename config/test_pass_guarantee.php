<?php

declare(strict_types=1);

/*
 * Pass Your Test Guarantee add-on for the public booking form.
 *
 * Always optional: learners opt in on the payment step and pay `price`,
 * whatever the package size or payment mode. It is never included free.
 * (To claim it, the pupil must still complete 10+ hours with DRIVE before
 * their test — see the Terms of Service.)
 *
 * Always read these values through `App\Support\TestPassGuarantee`.
 */
return [
    'price' => (float) env('TEST_PASS_GUARANTEE_PRICE', 50),

    'terms_url' => env('TEST_PASS_GUARANTEE_TERMS_URL', rtrim((string) env('APP_URL'), '/').'/terms-of-service#test-pass-guarantee'),
];
