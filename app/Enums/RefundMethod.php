<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundMethod: string
{
    case STRIPE = 'stripe';
    case MANUAL = 'manual';
}
