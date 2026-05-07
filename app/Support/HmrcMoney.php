<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Single source of truth for monetary conversion across the three formats
 * the HMRC integration handles:
 *
 * - UI input: pounds with up to 2dp ("1234.56" or 1234.56).
 * - DB storage: bigInteger pence (123456).
 * - HMRC payload: decimal pounds with exactly 2dp (1234.56 as a JSON number).
 */
final class HmrcMoney
{
    /**
     * Convert a UI/string/float pound value to integer pence.
     */
    public static function fromInput(string|int|float $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        $stringValue = is_float($value) ? number_format($value, 2, '.', '') : trim($value);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $stringValue)) {
            throw new InvalidArgumentException("Invalid monetary value: {$stringValue}");
        }

        $negative = str_starts_with($stringValue, '-');
        $stringValue = ltrim($stringValue, '-');

        [$pounds, $pence] = array_pad(explode('.', $stringValue), 2, '0');
        $pence = str_pad($pence, 2, '0');

        $total = ((int) $pounds) * 100 + (int) $pence;

        return $negative ? -$total : $total;
    }

    /**
     * Format integer pence as a 2dp pound string for UI display.
     */
    public static function toDisplay(int $pence): string
    {
        return number_format($pence / 100, 2, '.', '');
    }

    /**
     * Format integer pence as a JSON number with exactly 2dp for HMRC payloads.
     *
     * Returned as a float — encoders honour 2dp because PHP's float-to-string
     * uses the platform `serialize_precision`. Callers that need a guaranteed
     * fixed string representation should use `toDisplay()` and quote it.
     */
    public static function toHmrcPayload(int $pence, bool $allowNegative = true, bool $allowZero = true): float
    {
        if (! $allowNegative && $pence < 0) {
            throw new InvalidArgumentException('HMRC field disallows negative values.');
        }

        if (! $allowZero && $pence === 0) {
            throw new InvalidArgumentException('HMRC field disallows zero values.');
        }

        return round($pence / 100, 2);
    }
}
