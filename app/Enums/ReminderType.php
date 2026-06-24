<?php

declare(strict_types=1);

namespace App\Enums;

enum ReminderType: string
{
    case MILES_START = 'miles_start';
    case MILES_END = 'miles_end';
    case PAYMENT_DUE_48H = 'payment_due_48h';
}
