<?php

declare(strict_types=1);

/*
 * How long unpaid bookings hold their diary slots, and when weekly lesson
 * payments fall due.
 *
 * - Learners booking themselves (booking form or mobile app) pay there and
 *   then; slots are held for `learner_hold_minutes`.
 * - Instructor bookings email a payment link; slots are held until
 *   `instructor_hold_hours_before_lesson` before the first lesson.
 * - Bookings-team (staff) bookings email a payment link; slots are held until
 *   midnight in `timezone`.
 * - No hold is ever shorter than `minimum_hold_minutes`.
 *
 * Always read these values through `App\Support\BookingPayments`.
 */
return [
    'learner_hold_minutes' => (int) env('BOOKING_LEARNER_HOLD_MINUTES', 15),

    'minimum_hold_minutes' => (int) env('BOOKING_MINIMUM_HOLD_MINUTES', 15),

    'instructor_hold_hours_before_lesson' => (int) env('BOOKING_INSTRUCTOR_HOLD_HOURS_BEFORE_LESSON', 48),

    'weekly_payment_due_hours_before_lesson' => (int) env('BOOKING_WEEKLY_PAYMENT_DUE_HOURS_BEFORE_LESSON', 48),

    'timezone' => env('BOOKING_TIMEZONE', 'Europe/London'),
];
