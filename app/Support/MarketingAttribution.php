<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Ad-click and campaign parameters passed from just-drive.co.uk into the
 * public booking/onboarding flows, plus the GTM purchase event fired once a
 * booking is paid.
 */
final class MarketingAttribution
{
    /**
     * @var list<string>
     */
    public const CLICK_ID_PARAMS = ['gclid', 'gbraid', 'wbraid', 'fbclid'];

    private const MAX_VALUE_LENGTH = 500;

    private const PURCHASE_TRACKED_DAYS = 365;

    /**
     * Click IDs and utm_* parameters from the request query string.
     *
     * @return array<string, string>
     */
    public static function fromRequest(Request $request): array
    {
        $params = [];

        foreach ($request->query() as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            $isTrackingParam = in_array($key, self::CLICK_ID_PARAMS, true)
                || preg_match('/^utm_[a-z0-9_]{1,40}$/', $key) === 1;

            $value = trim($value);

            if ($isTrackingParam && $value !== '') {
                $params[$key] = mb_substr($value, 0, self::MAX_VALUE_LENGTH);
            }
        }

        return $params;
    }

    /**
     * Enquiry tracking data: the attribution params plus the "Google ads"
     * source label downstream tools (Bird CRM, admin email) key off gclid.
     *
     * @param  array<string, string>  $params
     * @return array<string, string>|null
     */
    public static function enquiryTracking(array $params): ?array
    {
        if ($params === []) {
            return null;
        }

        if (isset($params['gclid'])) {
            $params['source'] = 'Google ads';
        }

        return $params;
    }

    /**
     * GA4 ecommerce payload for the paid order's dataLayer "purchase" event.
     * Returns null after the first call for an order so the event is only
     * pushed once, whichever success page or browser renders it.
     *
     * @return array{transaction_id: string, value: float, currency: string, payment_mode: string|null, items: list<array<string, mixed>>}|null
     */
    public static function purchaseEventOnce(Order $order): ?array
    {
        if (! Cache::add('gtm-purchase-tracked:'.$order->id, true, now()->addDays(self::PURCHASE_TRACKED_DAYS))) {
            return null;
        }

        $order->loadMissing('package');

        $value = round($order->firstPaymentBreakdown()['total_pence'] / 100, 2);

        return [
            'transaction_id' => (string) $order->id,
            'value' => $value,
            'currency' => 'GBP',
            'payment_mode' => $order->payment_mode?->value,
            'items' => [[
                'item_id' => $order->package_id !== null ? (string) $order->package_id : null,
                'item_name' => $order->package?->name,
                'item_category' => 'Driving lessons',
                'item_variant' => $order->payment_mode?->value,
                'price' => $value,
                'quantity' => 1,
            ]],
        ];
    }
}
