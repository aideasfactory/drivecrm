<?php

namespace App\Enums;

enum MessageType: string
{
    case DIRECT = 'direct';
    case BROADCAST = 'broadcast';
    case LESSON_ON_WAY = 'lesson_on_way';
    case LESSON_ARRIVED = 'lesson_arrived';
}
