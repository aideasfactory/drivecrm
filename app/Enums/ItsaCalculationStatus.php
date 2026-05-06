<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Local processing state for a triggered calculation row. HMRC's API uses
 * `IS_PROCESSED` / `IS_NOT_PROCESSED` / `ERROR` strings on the metadata
 * block — we map those into this tighter set on read.
 */
enum ItsaCalculationStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Errored = 'errored';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Calculation in progress',
            self::Processed => 'Ready',
            self::Errored => 'HMRC reported an error',
        };
    }

    public static function fromHmrcOutcome(?string $outcome): self
    {
        return match ($outcome) {
            'IS_PROCESSED' => self::Processed,
            'ERROR' => self::Errored,
            default => self::Pending,
        };
    }
}
