<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four flavours of HMRC tax calculation we may trigger. Phase 3.5 only
 * uses `FinalDeclaration` to crystallise the year, but in-year and
 * intent-to-crystallise variants are useful preview helpers and may be
 * exposed in a later iteration.
 */
enum ItsaCalculationType: string
{
    case InYear = 'inYear';
    case IntentToCrystallise = 'intentToCrystallise';
    case Crystallisation = 'crystallisation';
    case FinalDeclaration = 'finalDeclaration';

    public function label(): string
    {
        return match ($this) {
            self::InYear => 'In-year preview',
            self::IntentToCrystallise => 'Intent to crystallise',
            self::Crystallisation => 'Crystallisation',
            self::FinalDeclaration => 'Final declaration',
        };
    }

    /**
     * Whether the calculation request should set `finalDeclaration=true` on
     * the trigger query string. Only the FinalDeclaration variant locks in
     * the figures.
     */
    public function isFinal(): bool
    {
        return $this === self::FinalDeclaration;
    }
}
