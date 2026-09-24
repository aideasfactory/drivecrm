<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * Lenient parsing of the plain-text values found in legacy import CSVs.
 * Each parser returns null for a value it cannot understand, so the bundle
 * validator can report the row instead of throwing mid-import.
 */
final class ImportValues
{
    /**
     * Parse a date written as YYYY-MM-DD or UK DD/MM/YYYY (leading zeros optional).
     */
    public static function date(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        foreach (['!Y-m-d', '!d/m/Y', '!j/n/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            // Round-trip check rejects overflow dates such as 31/02.
            if ($date !== false && $date->format(ltrim($format, '!')) === $value) {
                return CarbonImmutable::instance($date);
            }
        }

        return null;
    }

    /**
     * Parse a time written as H:MM, HH:MM or HH:MM:SS into "HH:MM".
     */
    public static function time(string $value): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Parse a pounds amount ("35", "35.5", "£1,035.00") into pence.
     */
    public static function pence(string $value): ?int
    {
        $clean = str_replace([',', '£', ' '], '', trim($value));

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        [$pounds, $pence] = array_pad(explode('.', $clean), 2, '0');

        return ((int) $pounds) * 100 + (int) str_pad($pence, 2, '0');
    }

    /**
     * Normalise a postcode sector ("eh12 " → "EH12"), or null if it is not one.
     */
    public static function postcodeSector(string $value): ?string
    {
        $sector = strtoupper(str_replace(' ', '', trim($value)));

        return preg_match('/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?$/', $sector) ? $sector : null;
    }
}
