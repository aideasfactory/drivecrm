<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Ad-click and campaign query parameters that GTM (GA4, Google Ads, Meta)
 * reads from the landing URL. Entry routes that redirect into the step flow
 * must carry these across, or attribution is lost on the redirect.
 */
final class MarketingParams
{
    /** @var list<string> */
    public const KEYS = [
        'gclid',
        'gbraid',
        'wbraid',
        'fbclid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    /**
     * The marketing parameters present on the request, ready to append to a
     * redirect URL.
     *
     * @return array<string, string>
     */
    public static function fromRequest(Request $request): array
    {
        $params = [];

        foreach (self::KEYS as $key) {
            $value = $request->query($key);

            if (is_string($value) && trim($value) !== '') {
                $params[$key] = trim($value);
            }
        }

        return $params;
    }
}
