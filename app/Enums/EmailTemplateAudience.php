<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailTemplateAudience: string
{
    case Learner = 'learner';
    case Instructor = 'instructor';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Learner',
            self::Instructor => 'Instructor',
            self::Both => 'Instructor & learner',
        };
    }
}
