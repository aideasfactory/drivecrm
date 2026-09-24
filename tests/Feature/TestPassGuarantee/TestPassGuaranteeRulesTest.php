<?php

declare(strict_types=1);

use App\Enums\PaymentMode;
use App\Support\TestPassGuarantee;

beforeEach(function () {
    config([
        'test_pass_guarantee.price' => 50,
        'test_pass_guarantee.free_minimum_hours' => 10,
    ]);
});

it('works out booked hours from lessons and slot length', function (int $lessons, ?string $start, ?string $end, float $expected) {
    expect(TestPassGuarantee::bookedHours($lessons, $start, $end))->toBe($expected);
})->with([
    '5 two-hour lessons' => [5, '10:00', '12:00', 10.0],
    '10 one-hour lessons' => [10, '09:00', '10:00', 10.0],
    '4 ninety-minute lessons' => [4, '09:00', '10:30', 6.0],
    'missing times fall back to one hour per lesson' => [10, null, null, 10.0],
    'invalid slot falls back to one hour per lesson' => [3, '12:00', '10:00', 3.0],
]);

it('gives the guarantee free for 10+ hours paid in full, even without opting in', function () {
    expect(TestPassGuarantee::resolve(10, PaymentMode::UPFRONT, false))
        ->toBe(['included' => true, 'charge_pence' => 0, 'is_free' => true]);

    expect(TestPassGuarantee::resolve(20, PaymentMode::UPFRONT, true))
        ->toBe(['included' => true, 'charge_pence' => 0, 'is_free' => true]);
});

it('does not give the guarantee free to weekly payers', function () {
    expect(TestPassGuarantee::resolve(20, PaymentMode::WEEKLY, false))
        ->toBe(['included' => false, 'charge_pence' => 0, 'is_free' => false]);
});

it('charges the add-on to weekly payers who opt in', function () {
    expect(TestPassGuarantee::resolve(20, PaymentMode::WEEKLY, true))
        ->toBe(['included' => true, 'charge_pence' => 5000, 'is_free' => false]);
});

it('does not give the guarantee free to bookings under 10 hours paid in full', function () {
    expect(TestPassGuarantee::resolve(9.5, PaymentMode::UPFRONT, false))
        ->toBe(['included' => false, 'charge_pence' => 0, 'is_free' => false]);
});

it('charges the add-on to small bookings paid in full that opt in', function () {
    expect(TestPassGuarantee::resolve(6, PaymentMode::UPFRONT, true))
        ->toBe(['included' => true, 'charge_pence' => 5000, 'is_free' => false]);
});

it('reads the price and threshold from config', function () {
    config([
        'test_pass_guarantee.price' => 75,
        'test_pass_guarantee.free_minimum_hours' => 12,
    ]);

    expect(TestPassGuarantee::pricePence())->toBe(7500);
    expect(TestPassGuarantee::resolve(10, PaymentMode::UPFRONT, false)['included'])->toBeFalse();
    expect(TestPassGuarantee::resolve(12, PaymentMode::UPFRONT, false)['is_free'])->toBeTrue();
});
