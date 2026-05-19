<?php

declare(strict_types=1);

namespace App\Enums;

enum VehicleMethod: string
{
    case Simplified = 'simplified';
    case Actual = 'actual';

    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Simplified',
            self::Actual => 'Advanced',
        };
    }

    public function hmrcLabel(): string
    {
        return match ($this) {
            self::Simplified => 'Simplified',
            self::Actual => 'Actual',
        };
    }
}
