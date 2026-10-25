<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Package;
use App\Services\StripeService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['services.stripe.secret' => 'sk_test_dummy']);

    $this->service = new class extends StripeService
    {
        /**
         * @return list<array<string, mixed>>
         */
        public function lineItemsFor(Order $order, Package $package, int $chargeAmountPence): array
        {
            return $this->buildCheckoutLineItems($order, $package, $chargeAmountPence);
        }

        /**
         * @param  array{lesson: int, booking_fee: int, digital_fee: int}  $breakdown
         * @return list<array{amount: int, description: string, component: string}>
         */
        public function invoiceLineItemsFor(int $amountPence, array $breakdown, bool $isInstalment): array
        {
            return $this->buildInvoiceLineItems($amountPence, $breakdown, '10 Lesson Package', '26 Oct 2026 17:30', $isInstalment);
        }
    };

    $this->package = new Package(['name' => '10 Lesson Package', 'lessons_count' => 10]);
    $this->package->stripe_product_id = 'prod_test';
});

it('itemises package price, booking fee and digital fee', function () {
    $order = new Order([
        'package_total_price_pence' => 60000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 65989,
    ]);

    $items = $this->service->lineItemsFor($order, $this->package, 65989);

    expect($items)->toHaveCount(3)
        ->and($items[0]['price_data'])->toMatchArray(['unit_amount' => 60000, 'product' => 'prod_test'])
        ->and($items[1]['price_data']['unit_amount'])->toBe(1999)
        ->and($items[1]['price_data']['product_data']['name'])->toBe('Booking fee')
        ->and($items[2]['price_data']['unit_amount'])->toBe(3990)
        ->and($items[2]['price_data']['product_data']['name'])->toBe('Digital fee')
        ->and($items[2]['price_data']['product_data']['description'])->toContain('10 lessons')
        ->and(collect($items)->sum(fn (array $item): int => $item['price_data']['unit_amount']))->toBe(65989);
});

it('uses the discounted package price as the first line item', function () {
    $order = new Order([
        'package_total_price_pence' => 54000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 59989,
        'discount_percentage' => 10,
    ]);

    $items = $this->service->lineItemsFor($order, $this->package, 59989);

    expect($items)->toHaveCount(3)
        ->and($items[0]['price_data']['unit_amount'])->toBe(54000);
});

it('falls back to a single line item when the components do not add up to the charge', function () {
    $order = new Order([
        'package_total_price_pence' => 60000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 70000,
    ]);

    $items = $this->service->lineItemsFor($order, $this->package, 70000);

    expect($items)->toHaveCount(1)
        ->and($items[0]['price_data']['unit_amount'])->toBe(70000);
});

it('labels invoice fee lines as a weekly instalment only for real instalments', function (bool $isInstalment, string $bookingLabel, string $digitalLabel) {
    $items = $this->service->invoiceLineItemsFor(8109, ['lesson' => 7400, 'booking_fee' => 349, 'digital_fee' => 360], $isInstalment);

    expect(array_column($items, 'description'))->toBe([
        'Lesson cost — 10 Lesson Package on 26 Oct 2026 17:30',
        $bookingLabel,
        $digitalLabel,
    ]);
})->with([
    'instalment' => [true, 'Booking fee (weekly instalment)', 'Digital services fee (weekly instalment)'],
    'single payment' => [false, 'Booking fee', 'Digital services fee'],
]);

it('uses product data when the package has no Stripe product', function () {
    $this->package->stripe_product_id = null;

    $order = new Order([
        'package_total_price_pence' => 60000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 0,
        'digital_fee_pence' => 0,
        'total_price_pence' => 60000,
    ]);

    $items = $this->service->lineItemsFor($order, $this->package, 60000);

    expect($items)->toHaveCount(1)
        ->and($items[0]['price_data']['product_data']['name'])->toBe('10 Lesson Package');
});
