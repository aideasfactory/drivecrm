<?php

declare(strict_types=1);

use App\Enums\PaymentMode;
use App\Models\Package;

it('turns weekly into pay in full for a single-lesson package', function () {
    expect((new Package(['lessons_count' => 1]))->paymentModeFor(PaymentMode::WEEKLY))->toBe(PaymentMode::UPFRONT);
});

it('keeps weekly for packages of two or more lessons', function () {
    expect((new Package(['lessons_count' => 2]))->paymentModeFor(PaymentMode::WEEKLY))->toBe(PaymentMode::WEEKLY);
});

it('leaves other payment modes unchanged', function (PaymentMode $mode) {
    expect((new Package(['lessons_count' => 1]))->paymentModeFor($mode))->toBe($mode);
})->with([PaymentMode::UPFRONT, PaymentMode::CASH]);
