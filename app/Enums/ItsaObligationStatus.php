<?php

declare(strict_types=1);

namespace App\Enums;

enum ItsaObligationStatus: string
{
    case Open = 'Open';
    case Fulfilled = 'Fulfilled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Fulfilled => 'Fulfilled',
        };
    }
}
