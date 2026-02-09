<?php

namespace App\Enums;

enum PaymentMode: string
{
    case UPFRONT = 'upfront';
    case WEEKLY = 'weekly';
}
