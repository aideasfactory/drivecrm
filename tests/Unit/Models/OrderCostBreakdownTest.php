<?php

declare(strict_types=1);

use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;

function feeOrder(array $overrides = []): Order
{
    return new Order(array_merge([
        'package_total_price_pence' => 60000,
        'package_lessons_count' => 10,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 65989,
    ], $overrides));
}

it('itemises lessons, booking fee and digital fee', function () {
    expect(feeOrder()->costBreakdownLines())->toBe([
        'Lessons: £600.00',
        'Booking fee: £19.99',
        'Digital fee: £39.90',
    ]);
});

it('omits zero fee lines from the breakdown', function () {
    $order = feeOrder(['digital_fee_pence' => 0, 'total_price_pence' => 61999]);

    expect($order->costBreakdownLines())->toBe([
        'Lessons: £600.00',
        'Booking fee: £19.99',
    ]);
});

it('knows whether an order carries fees', function () {
    expect(feeOrder()->hasFees())->toBeTrue()
        ->and(feeOrder(['booking_fee_pence' => 0, 'digital_fee_pence' => 0])->hasFees())->toBeFalse();
});

it('formats the weekly instalment including the fee share', function () {
    expect(feeOrder()->formatted_weekly_instalment)->toBe('£65.99');
});

it('falls back to the package total for the weekly instalment on legacy orders', function () {
    $order = feeOrder(['total_price_pence' => null, 'booking_fee_pence' => 0, 'digital_fee_pence' => 0]);

    expect($order->formatted_weekly_instalment)->toBe('£60.00');
});

it('formats a payment breakdown with a trailing blank line', function () {
    expect(LessonPayment::breakdownLines(['lesson' => 6000, 'booking_fee' => 200, 'digital_fee' => 399]))->toBe([
        '**Cost breakdown:**',
        'Lesson cost: £60.00',
        'Booking fee (weekly instalment): £2.00',
        'Digital services fee (weekly instalment): £3.99',
        '',
    ]);
});

it('returns no payment breakdown lines when there are no fees', function () {
    expect(LessonPayment::breakdownLines(['lesson' => 6000, 'booking_fee' => 0, 'digital_fee' => 0]))->toBe([]);
});

it('spreads the fee-inclusive order total across upfront lesson records', function () {
    $order = feeOrder();
    $lesson = new Lesson(['amount_pence' => 6000]);

    $shares = collect(range(0, 9))
        ->map(fn (int $index): int => LessonPayment::orderShareForLesson($order, $lesson, $index, 10));

    expect($shares->sum())->toBe(65989)
        ->and($shares->first())->toBe(6599);
});

it('uses the lesson price for upfront lesson records on legacy orders without a total', function () {
    $order = feeOrder(['total_price_pence' => null]);
    $lesson = new Lesson(['amount_pence' => 6000]);

    expect(LessonPayment::orderShareForLesson($order, $lesson, 0, 10))->toBe(6000);
});
