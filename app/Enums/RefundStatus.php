<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
}
