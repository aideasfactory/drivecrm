<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundAction: string
{
    case NONE = 'none';
    case REQUEST = 'request';
    case STRIPE = 'stripe';
}
