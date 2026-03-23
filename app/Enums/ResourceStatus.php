<?php

namespace App\Enums;

enum ResourceStatus: string
{
    case PUBLISHED = 'published';
    case DRAFT = 'draft';
}
