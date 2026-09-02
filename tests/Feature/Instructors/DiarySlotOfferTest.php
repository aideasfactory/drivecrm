<?php

declare(strict_types=1);

use App\Enums\CalendarItemStatus;
use App\Enums\PaymentMode;
use App\Enums\SlotOfferStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Package;
use App\Models\SlotOffer;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->mock(StripeService::class, function ($mock) {
        $mock->shouldIgnoreMissing();
        $mock->shouldReceive('createOrGetCustomer')->andReturn(['success' => true, 'customer_id' => 'cus_test']);
        $mock->shouldReceive('createInvoice')->andReturn([
            'success' => true,
            'invoice_id' => 'in_test',
            'hosted_invoice_url' => 'https://invoice.test',
        ]);
    });
});

test('admin can offer an empty diary slot for an instructor', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $instructor = Instructor::factory()->create();
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => now()->addDays(2)->toDateString(),
    ]);
    $item = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'is_available' => true,
        'status' => null,
    ]);
    $package = Package::factory()->forInstructor($instructor)->create([
        'lessons_count' => 1,
        'total_price_pence' => 4000,
        'active' => true,
    ]);

    $response = $this->postJson(route('instructors.calendar.items.offers.store', [
        'instructor' => $instructor->id,
        'calendarItem' => $item->id,
    ]), [
        'package_id' => $package->id,
        'message' => 'Can anyone take this slot?',
    ]);

    $response->assertCreated()
        ->assertJsonPath('offer.status', 'open');

    expect(SlotOffer::query()->where('calendar_item_id', $item->id)->exists())->toBeTrue();
});

test('admin can add a booking from an empty diary slot', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => now()->addDays(2)->toDateString(),
    ]);
    $item = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'is_available' => true,
        'status' => null,
    ]);
    $package = Package::factory()->forInstructor($instructor)->create([
        'lessons_count' => 1,
        'total_price_pence' => 4000,
        'active' => true,
    ]);

    $response = $this->postJson(route('students.orders.store', $student), [
        'package_id' => $package->id,
        'payment_mode' => PaymentMode::WEEKLY->value,
        'calendar_item_id' => $item->id,
    ]);

    $response->assertCreated();
    expect($item->fresh()->status)->toBe(CalendarItemStatus::RESERVED)
        ->and(SlotOffer::query()->where('calendar_item_id', $item->id)->where('status', SlotOfferStatus::Open)->exists())->toBeFalse();
});
