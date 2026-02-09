<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
