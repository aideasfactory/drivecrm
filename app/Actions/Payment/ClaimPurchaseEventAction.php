<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\ContactDetails;

class ClaimPurchaseEventAction
{
    /**
     * Build the GTM `purchase` dataLayer payload for a paid order, exactly
     * once. The first call stamps purchase_tracked_at (atomically, so two
     * concurrent success-page loads can't both claim it); every later call
     * returns null, so a refresh or revisit never reports the sale twice.
     *
     * `user_data` carries the payer's details for Google Ads enhanced
     * conversions; the frontend only pushes it once ad_user_data consent is
     * granted (see usePurchaseTracking.ts).
     *
     * @return array{event: string, transaction_id: string, value: float, currency: string, items: array<int, array{item_name: string, item_id: string, price: float, quantity: int}>, user_data: array<string, mixed>|null}|null
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

        $order->loadMissing(['package', 'student.pickupPoints']);

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
            'user_data' => $this->userData($order),
        ];
    }

    /**
     * User-provided data in the shape GTM's "User-Provided Data" variable and
     * gtag expect. Uses the person who paid (the booker when booking for
     * someone else); the pickup postcode is only theirs when the learner
     * booked for themselves.
     *
     * @return array{email?: string, phone_number?: string, address?: array{first_name?: string, last_name?: string, postal_code?: string, country: string}}|null
     */
    private function userData(Order $order): ?array
    {
        $student = $order->student;

        if (! $student) {
            return null;
        }

        $booker = $student->getBookerDetails();

        $email = $this->clean($booker['email'] ?? null);
        $phone = ContactDetails::toE164($this->clean($booker['phone'] ?? null));

        $postcode = null;
        if ($student->owns_account) {
            $pickupPoint = $student->pickupPoints->firstWhere('is_default', true) ?? $student->pickupPoints->first();
            $postcode = $this->clean($pickupPoint?->postcode);
        }

        $address = array_filter([
            'first_name' => $this->clean($booker['first_name'] ?? null),
            'last_name' => $this->clean($booker['surname'] ?? null),
            'postal_code' => $postcode ? strtoupper((string) preg_replace('/\s+/', '', $postcode)) : null,
        ]);

        $userData = array_filter([
            'email' => $email ? strtolower($email) : null,
            'phone_number' => $phone,
            'address' => $address !== [] ? [...$address, 'country' => 'GB'] : null,
        ]);

        return $userData !== [] ? $userData : null;
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
