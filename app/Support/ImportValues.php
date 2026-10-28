<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Lenient parsing of the plain-text values found in legacy import CSVs.
 * Each parser returns null for a value it cannot understand, so the bundle
 * validator can report the row instead of throwing mid-import.
 */
final class ImportValues
{
    /**
     * Hash of a random password nobody knows, for an imported account.
     *
     * It is never used to sign in — the welcome email replaces it (password
     * set-up link or a fresh temporary password) — so the lowest bcrypt cost
     * is used: ~1ms instead of ~230ms per account, which dominated large
     * imports. 48 random characters can't be guessed whatever the cost.
     */
    public static function placeholderPasswordHash(): string
    {
        return Hash::make(Str::random(48), ['rounds' => 4]);
    }

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

    /**
     * Normalise a postcode_sector cell that may hold several sectors joined by
     * a separator ("CW8.CW9" → ["CW8", "CW9"], "PR7." → ["PR7"]). Null if any
     * part is not a sector, or there are none.
     *
     * @return array<int, string>|null
     */
    public static function postcodeSectors(string $value): ?array
    {
        $parts = array_filter(
            array_map('trim', preg_split('/[.,;\/|]/', $value)),
            fn (string $part) => $part !== '',
        );

        $sectors = array_map(fn (string $part) => self::postcodeSector($part), $parts);

        if ($sectors === [] || in_array(null, $sectors, true)) {
            return null;
        }

        return array_values(array_unique($sectors));
    }

    /**
     * The instructor ID from a Drive+ import ref ("DRIVE-24" → 24), or null if
     * the ref is not one. Shown on the instructor's page as their import ref.
     */
    public static function driveInstructorId(string $ref): ?int
    {
        return preg_match('/^DRIVE-(\d+)$/', trim($ref), $matches) ? (int) $matches[1] : null;
    }
}
