<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Receipt upload constraints shared by the API create/upload endpoints and the
 * `/finances/config` response, so the app validates against the same limit the
 * server actually enforces.
 */
final class FinanceReceiptUploads
{
    /**
     * Effective max receipt size in KB: the configured limit, capped by PHP's
     * `upload_max_filesize` and `post_max_size`. Files above the PHP limits never
     * reach validation intact, so advertising a bigger number would be a lie.
     */
    public static function maxSizeKb(): int
    {
        $limits = array_filter([
            (int) config('finances.receipt.max_size_kb', 10240),
            self::iniSizeToKb((string) ini_get('upload_max_filesize')),
            self::iniSizeToKb((string) ini_get('post_max_size')),
        ], fn (int $kb): bool => $kb > 0);

        return $limits === [] ? 10240 : min($limits);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedMimes(): array
    {
        return (array) config('finances.receipt.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png']);
    }

    /**
     * @return array<int, string>
     */
    public static function rules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.implode(',', self::allowedMimes()),
            'max:'.self::maxSizeKb(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute = 'receipt'): array
    {
        $maxMb = self::formatMb(self::maxSizeKb());
        $types = strtoupper(implode(', ', self::allowedMimes()));

        return [
            "{$attribute}.required" => 'Please attach a receipt file.',
            "{$attribute}.uploaded" => "The receipt could not be uploaded. Make sure it is a {$types} file under {$maxMb} MB and try again.",
            "{$attribute}.file" => 'The receipt must be a file.',
            "{$attribute}.mimes" => "The receipt must be one of: {$types}.",
            "{$attribute}.max" => "The receipt is too large. The maximum size is {$maxMb} MB.",
        ];
    }

    /**
     * Convert php.ini shorthand (e.g. "2M", "512K", "1G") to KB. Returns 0 for
     * "0"/empty, which PHP treats as unlimited.
     */
    private static function iniSizeToKb(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => intdiv($number, 1024),
        };
    }

    private static function formatMb(int $kb): string
    {
        $mb = $kb / 1024;

        return floor($mb) === $mb ? (string) (int) $mb : number_format($mb, 1);
    }
}
