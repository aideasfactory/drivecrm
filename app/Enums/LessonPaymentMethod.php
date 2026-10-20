<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a lesson payment was settled. `null` on a lesson payment means card
 * (Stripe) or a row written before this column existed.
 */
enum LessonPaymentMethod: string
{
    /** Paid through Stripe (Checkout or invoice). */
    case CARD = 'card';

    /** Paid to the instructor outside Stripe (e.g. cash), confirmed by the instructor. */
    case ALTERNATIVE = 'alternative';
}
