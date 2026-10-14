<?php

declare(strict_types=1);

/*
 * Booking and digital fee configuration.
 *
 * `booking_fee` is a one-off fee charged per order.
 * `digital_fee_per_hour` is charged for each hour of lesson time in the
 * booking (a 2-hour lesson is twice a 1-hour lesson; a package is the rate
 * times its total hours). `DIGITAL_FEE_PER_LESSON` is still read when
 * `DIGITAL_FEE_PER_HOUR` is unset, so existing deployments keep their rate
 * and apply it per hour.
 *
 * When no slot length is known yet (package lists), each lesson is treated
 * as `standard_lesson_minutes` (the 2-hour diary slot). Checkout, order
 * creation and pricing calls that receive a start and end time use that slot.
 *
 * When `override_to_zero` is true, both fees are treated as £0 by every
 * pricing touchpoint (checkout preview, order creation, package display
 * accessors). The underlying fee values remain configured so they can be
 * re-enabled by flipping the flag back off.
 *
 * Always read fees through `App\Support\Fees` — it applies the override rule
 * in one place and exposes helpers in both pounds and pence.
 */
$digitalFeePerHour = env('DIGITAL_FEE_PER_HOUR', env('DIGITAL_FEE_PER_LESSON', 1.80));

return [
    'booking_fee' => (float) env('BOOKING_FEE', 19.99),

    'digital_fee_per_hour' => (float) $digitalFeePerHour,

    /*
     * Same unit rate as digital_fee_per_hour. Kept so config overrides of the
     * old key still supply the rate; it is no longer "per lesson".
     */
    'digital_fee_per_lesson' => (float) $digitalFeePerHour,

    'standard_lesson_minutes' => 120,

    'override_to_zero' => filter_var(env('FEES_OVERRIDE_TO_ZERO', false), FILTER_VALIDATE_BOOLEAN),
];
