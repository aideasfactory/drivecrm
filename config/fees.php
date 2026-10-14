<?php

declare(strict_types=1);

/*
 * Booking and digital fee configuration.
 *
 * `booking_fee` is a one-off fee charged per order.
 * `digital_fee_per_hour` is charged for every hour of lesson time. Lessons are
 * always `lesson_hours` long (the diary only creates 2-hour slots), so each
 * lesson carries the hourly rate × `lesson_hours` (£1.80 × 2 = £3.60).
 *
 * When `override_to_zero` is true, both fees are treated as £0 by every
 * pricing touchpoint (checkout preview, order creation, package display
 * accessors). The underlying `booking_fee` / `digital_fee_per_hour` values
 * remain configured so they can be re-enabled by flipping the flag back off.
 *
 * Always read fees through `App\Support\Fees` — it applies the override rule
 * in one place and exposes helpers in both pounds and pence.
 */
return [
    'booking_fee' => (float) env('BOOKING_FEE', 19.99),

    'digital_fee_per_hour' => (float) env('DIGITAL_FEE_PER_HOUR', 1.80),

    'lesson_hours' => 2,

    'override_to_zero' => filter_var(env('FEES_OVERRIDE_TO_ZERO', false), FILTER_VALIDATE_BOOLEAN),
];
