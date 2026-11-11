<?php

declare(strict_types=1);

namespace App\Actions\Import;

class ParseImportedLocationsAction
{
    /**
     * `Label: address, POSTCODE`, `address, POSTCODE`, `address POSTCODE` or just
     * `POSTCODE`, where POSTCODE is a full UK postcode at the very end (space
     * between the halves optional). Anything else doesn't import cleanly.
     */
    protected const LOCATION_PATTERN = '/^(?:(?<address>.*?)[,\s]+)?(?<outward>[A-Z]{1,2}\d[A-Z\d]?)\s?(?<inward>\d[A-Z]{2})$/i';

    public const REPEAT_REASON = 'Same as an earlier location';

    /** An optional `Label:` prefix (no comma, up to 40 characters). */
    protected const LABEL_PATTERN = '/^(?<label>[^:,]{1,40}):\s*(?<rest>.*)$/s';

    /**
     * Split a legacy `locations` cell (locations joined by `|`) into pickup
     * points, in the order given. Each is either clean — label (null when the
     * cell has none), address (the postcode when there is no address) and the
     * normalised postcode ("ls146rx" → "LS14 6RX") — or skipped with a reason.
     * Nothing is fixed up: a part postcode, a typo or no postcode is skipped,
     * as is a repeat of an earlier location (same address + postcode).
     *
     * @return array<int, array{raw: string, label: string|null, address: string|null, postcode: string|null, skip_reason: string|null}>
     */
    public function __invoke(string $cell): array
    {
        $locations = [];
        /** @var array<string, true> $seen */
        $seen = [];

        foreach (explode('|', $cell) as $raw) {
            $raw = trim($raw);

            if ($raw === '') {
                continue;
            }

            $label = null;
            $rest = $raw;

            if (preg_match(self::LABEL_PATTERN, $raw, $matches)) {
                $label = trim($matches['label']) !== '' ? trim($matches['label']) : null;
                $rest = trim($matches['rest']);
            }

            if (! preg_match(self::LOCATION_PATTERN, $rest, $matches)) {
                $locations[] = ['raw' => $raw, 'label' => $label, 'address' => null, 'postcode' => null, 'skip_reason' => 'No full postcode at the end'];

                continue;
            }

            $postcode = strtoupper($matches['outward'].' '.$matches['inward']);
            $address = trim((string) ($matches['address'] ?? ''), " \t\n\r,");
            $key = strtolower(preg_replace('/\s+/', ' ', $address)).'|'.$postcode;

            if (isset($seen[$key])) {
                $locations[] = ['raw' => $raw, 'label' => $label, 'address' => null, 'postcode' => null, 'skip_reason' => self::REPEAT_REASON];

                continue;
            }

            $seen[$key] = true;

            $locations[] = [
                'raw' => $raw,
                'label' => $label,
                'address' => $address !== '' ? $address : $postcode,
                'postcode' => $postcode,
                'skip_reason' => null,
            ];
        }

        return $locations;
    }
}
