<?php

declare(strict_types=1);

/*
 * Lesson sign-off and reminder behaviour.
 *
 * `recommendations_delay_minutes` delays dispatch of the AI resource
 * recommendations job after sign-off so the email doesn't land alongside
 * the feedback request email.
 *
 * `day_before_reminder` is the UK-time window (HH:MM, Europe/London) in which
 * learners are pushed a reminder for tomorrow's lessons. Lessons booked for
 * tomorrow after the window closes get no reminder.
 */
return [
    'recommendations_delay_minutes' => (int) env('LESSON_RECOMMENDATIONS_DELAY_MINUTES', 120),

    'day_before_reminder' => [
        'send_from' => env('LESSON_DAY_BEFORE_REMINDER_FROM', '18:00'),
        'send_until' => env('LESSON_DAY_BEFORE_REMINDER_UNTIL', '21:00'),
    ],
];
