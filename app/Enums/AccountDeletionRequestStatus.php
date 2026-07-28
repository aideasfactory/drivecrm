<?php

namespace App\Enums;

enum AccountDeletionRequestStatus: string
{
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
