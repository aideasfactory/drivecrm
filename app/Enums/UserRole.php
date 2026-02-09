<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case INSTRUCTOR = 'instructor';
    case STUDENT = 'student';
}
