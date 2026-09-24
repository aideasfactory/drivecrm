<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Instructor;
use App\Models\Package;
use App\Models\User;

beforeEach(function () {
    config([
        'fees.booking_fee' => 19.99,
        'fees.digital_fee_per_lesson' => 3.99,
        'fees.override_to_zero' => false,
    ]);

    $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
    $this->instructor = Instructor::factory()->create();
});

it('includes booking and digital fees in the instructor package list used by the booking sheets', function () {
    Package::factory()->forInstructor($this->instructor)->create([
        'total_price_pence' => 60000,
        'lessons_count' => 10,
        'active' => true,
    ]);

    $this->actingAs($this->owner)
        ->getJson(route('instructors.packages', $this->instructor))
        ->assertOk()
        ->assertJsonPath('packages.0.formatted_total_price', '£600.00')
        ->assertJsonPath('packages.0.booking_fee', '£19.99')
        ->assertJsonPath('packages.0.digital_fee', '£39.90')
        ->assertJsonPath('packages.0.total_price', '£659.89')
        ->assertJsonPath('packages.0.weekly_payment', '£65.99');
});
