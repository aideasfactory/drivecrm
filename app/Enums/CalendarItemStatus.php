<?php

namespace App\Enums;

enum CalendarItemStatus: string
{
    case DRAFT = 'draft';
    case RESERVED = 'reserved';
    case BOOKED = 'booked';
    case COMPLETED = 'completed';
}
