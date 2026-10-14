<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;

class ClaimPurchaseEventAction
{
    /**
     * Build the GTM `purchase` dataLayer payload for a paid order, exactly
     * once. The first call stamps purchase_tracked_at (atomically, so two
     * concurrent success-page loads can't both claim it); every later call
     * returns null, so a refresh or revisit never reports the sale twice.
     *
     * @return array{event: string, transaction_id: string, value: float, currency: string, items: array<int, array{item_name: string, item_id: string, price: float, quantity: int}>}|null
     */
    public function __invoke(Order $order): ?array
    {
        $isPaid = $order->isActive() || $order->status === OrderStatus::COMPLETED;

        if (! $isPaid || $order->purchase_tracked_at !== null) {
            return null;
        }

        $claimed = Order::query()
            ->whereKey($order->id)
            ->whereNull('purchase_tracked_at')
            ->update(['purchase_tracked_at' => now()]);

        if ($claimed === 0) {
            return null;
        }

        $order->loadMissing('package');

        $packagePence = (int) $order->package_total_price_pence;

        return [
            'event' => 'purchase',
            'transaction_id' => (string) $order->id,
            'value' => round((int) ($order->total_price_pence ?? $packagePence) / 100, 2),
            'currency' => 'GBP',
            'items' => [[
                'item_name' => (string) ($order->package_name ?? $order->package?->name),
                'item_id' => (string) $order->package_id,
                'price' => round($packagePence / 100, 2),
                'quantity' => 1,
            ]],
        ];
    }
}
