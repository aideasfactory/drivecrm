<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentTestType: string
{
    case Theory = 'theory';

    case Practical = 'practical';

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Theory test',
            self::Practical => 'Practical test',
        };
    }
}
