<?php

declare(strict_types=1);

namespace App\Enums;

enum ItsaBusinessType: string
{
    case SelfEmployment = 'self-employment';
    case UkProperty = 'uk-property';
    case ForeignProperty = 'foreign-property';

    public function label(): string
    {
        return match ($this) {
            self::SelfEmployment => 'Self-employment',
            self::UkProperty => 'UK property',
            self::ForeignProperty => 'Foreign property',
        };
    }
}
