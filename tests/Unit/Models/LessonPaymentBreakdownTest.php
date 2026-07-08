<?php

declare(strict_types=1);

use App\Models\LessonPayment;
use App\Models\Order;

/*
|--------------------------------------------------------------------------
| LessonPayment::weeklyBreakdown — Cost Breakdown Tests
|--------------------------------------------------------------------------
|
| Verifies that a weekly payment amount is decomposed into its constituent
| components (lesson, booking fee, digital fee) proportional to the order's
| totals, and that the components always sum to the payment amount exactly.
|
*/

it('splits a payment proportionally across lesson, booking fee and digital fee', function () {
    $order = new Order([
        'package_total_price_pence' => 60000, // £600
        'booking_fee_pence' => 1999,          // £19.99
        'digital_fee_pence' => 3990,          // £3.99 * 10
        'total_price_pence' => 65989,
    ]);

    // Per-lesson amount for a 10-lesson package
    $amountPence = (int) round(65989 / 10);

    $breakdown = LessonPayment::weeklyBreakdown($order, $amountPence);

    expect($breakdown)->toHaveKeys(['lesson', 'booking_fee', 'digital_fee']);
    expect($breakdown['lesson'] + $breakdown['booking_fee'] + $breakdown['digital_fee'])
        ->toBe($amountPence);
    expect($breakdown['lesson'])->toBeGreaterThan(0);
    expect($breakdown['booking_fee'])->toBeGreaterThan(0);
    expect($breakdown['digital_fee'])->toBeGreaterThan(0);
});

it('components sum to the payment amount exactly even when rounding produces remainders', function () {
    $order = new Order([
        'package_total_price_pence' => 12345,
        'booking_fee_pence' => 1234,
        'digital_fee_pence' => 567,
        'total_price_pence' => 14146,
    ]);

    // Deliberately awkward payment amount to force rounding
    $amountPence = 3333;

    $breakdown = LessonPayment::weeklyBreakdown($order, $amountPence);

    expect($breakdown['lesson'] + $breakdown['booking_fee'] + $breakdown['digital_fee'])
        ->toBe($amountPence);
});

it('returns the whole amount as lesson cost when the order has no totals', function () {
    $order = new Order([
        'package_total_price_pence' => 0,
        'booking_fee_pence' => 0,
        'digital_fee_pence' => 0,
        'total_price_pence' => 0,
    ]);

    $breakdown = LessonPayment::weeklyBreakdown($order, 5000);

    expect($breakdown)->toBe([
        'lesson' => 5000,
        'booking_fee' => 0,
        'digital_fee' => 0,
    ]);
});

it('returns zero components when the payment amount is zero', function () {
    $order = new Order([
        'package_total_price_pence' => 60000,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 3990,
        'total_price_pence' => 65989,
    ]);

    $breakdown = LessonPayment::weeklyBreakdown($order, 0);

    expect($breakdown['lesson'])->toBe(0);
    expect($breakdown['booking_fee'])->toBe(0);
    expect($breakdown['digital_fee'])->toBe(0);
});

it('has a zero digital fee component when the order has no digital fee', function () {
    $order = new Order([
        'package_total_price_pence' => 60000,
        'booking_fee_pence' => 1999,
        'digital_fee_pence' => 0,
        'total_price_pence' => 61999,
    ]);

    $amountPence = (int) round(61999 / 10);

    $breakdown = LessonPayment::weeklyBreakdown($order, $amountPence);

    expect($breakdown['digital_fee'])->toBe(0);
    expect($breakdown['lesson'] + $breakdown['booking_fee'])->toBe($amountPence);
});
