<?php

declare(strict_types=1);

namespace App\Support\Hmrc;

/**
 * Percent-encodes HMRC fraud-prevention header values.
 *
 * Keys and values are encoded. Separators (=, &, ,) are left literal.
 * Spaces are %20 (RFC 3986), never +.
 */
final class FraudHeaderEncoder
{
    /**
     * @param  array<string, string|int|float>  $pairs
     */
    public static function keyValueList(array $pairs): string
    {
        $parts = [];

        foreach ($pairs as $key => $value) {
            $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }

    /**
     * @param  array<int, array<string, string|int|float>>  $structures
     */
    public static function structureList(array $structures): string
    {
        $encoded = [];

        foreach ($structures as $pairs) {
            $encoded[] = self::keyValueList($pairs);
        }

        return implode(',', $encoded);
    }
}
