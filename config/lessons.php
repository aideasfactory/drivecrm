<?php

declare(strict_types=1);

/*
 * Lesson sign-off behaviour.
 *
 * `recommendations_delay_minutes` delays dispatch of the AI resource
 * recommendations job after sign-off so the email doesn't land alongside
 * the feedback request email.
 */
return [
    'recommendations_delay_minutes' => (int) env('LESSON_RECOMMENDATIONS_DELAY_MINUTES', 120),
];
