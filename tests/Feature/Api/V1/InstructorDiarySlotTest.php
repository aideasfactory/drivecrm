<?php

declare(strict_types=1);

use App\Enums\CalendarItemStatus;
use App\Enums\PaymentMode;
use App\Enums\SlotOfferStatus;
use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\Package;
use App\Models\SlotOffer;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Support\Facades\Notification;

function diaryInstructorToken(): array
{
    $user = User::factory()->create(['role' => UserRole::INSTRUCTOR]);
    $instructor = Instructor::factory()->create(['user_id' => $user->id]);

    return [
        'user' => $user,
        'instructor' => $instructor,
        'token' => $user->createToken('Test Device')->plainTextToken,
    ];
}

function diaryStudentToken(Instructor $instructor): array
{
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'instructor_id' => $instructor->id,
        'status' => 'active',
    ]);

    return [
        'user' => $user,
        'student' => $student,
        'token' => $user->createToken('Test Device')->plainTextToken,
    ];
}

function diaryOpenSlot(Instructor $instructor, ?string $date = null): CalendarItem
{
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => $date ?? now()->addDays(3)->toDateString(),
    ]);

    return CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'is_available' => true,
        'status' => null,
    ]);
}

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
        $mock->shouldReceive('createCheckoutSession')->andReturn([
            'success' => true,
            'session_id' => 'cs_test',
            'url' => 'https://checkout.stripe.com/test',
        ]);
    });
});

test('an instructor can update an empty diary slot', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);
    $newDate = now()->addDays(4)->toDateString();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/calendar/items/'.$item->id, [
        'date' => $newDate,
        'start_time' => '14:00',
        'end_time' => '16:00',
        'is_available' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('mode', 'single')
        ->assertJsonPath('data.start_time', '14:00');
});

test('an instructor can delete an empty diary slot', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/v1/instructor/calendar/items/'.$item->id);

    $response->assertOk()
        ->assertJsonPath('message', 'Calendar item removed successfully.');

    expect(CalendarItem::find($item->id))->toBeNull();
});

test('an instructor can book a student onto a specific open diary slot', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);
    ['student' => $student] = diaryStudentToken($instructor);
    $package = Package::factory()->forInstructor($instructor)->create([
        'lessons_count' => 1,
        'total_price_pence' => 4000,
        'active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/orders", [
        'package_id' => $package->id,
        'payment_mode' => PaymentMode::WEEKLY->value,
        'calendar_item_id' => $item->id,
    ]);

    $response->assertCreated();

    $item->refresh();
    expect($item->status)->toBe(CalendarItemStatus::RESERVED)
        ->and($item->lessons()->count())->toBe(1);
});

test('booking onto a taken diary slot returns a validation error', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);
    $item->update(['status' => CalendarItemStatus::BOOKED, 'is_available' => false]);
    ['student' => $student] = diaryStudentToken($instructor);
    $package = Package::factory()->forInstructor($instructor)->create([
        'lessons_count' => 1,
        'total_price_pence' => 4000,
        'active' => true,
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/students/{$student->id}/orders", [
        'package_id' => $package->id,
        'payment_mode' => PaymentMode::WEEKLY->value,
        'calendar_item_id' => $item->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('calendar_item_id');
});

test('calendar index includes future_siblings_count and has_open_offer', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/v1/instructor/calendar/items?date='.$item->calendar->date->format('Y-m-d').'&available_only=false');

    $response->assertOk()
        ->assertJsonPath('data.0.has_open_offer', false)
        ->assertJsonPath('data.0.future_siblings_count', 0);
});

test('an instructor can offer an empty slot with a one-off price', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);
    diaryStudentToken($instructor);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson("/api/v1/instructor/calendar/items/{$item->id}/offers", [
        'message' => 'Free this afternoon if anyone wants it',
        'one_off_price_pence' => 4500,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.message', 'Free this afternoon if anyone wants it');

    expect(Package::query()->where('instructor_id', $instructor->id)->where('is_one_off', true)->exists())->toBeTrue()
        ->and(SlotOffer::query()->where('calendar_item_id', $item->id)->where('status', SlotOfferStatus::Open)->exists())->toBeTrue();
});

test('a student can list and accept an active short-notice offer', function () {
    ['instructor' => $instructor] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);
    ['student' => $student, 'token' => $studentToken] = diaryStudentToken($instructor);
    $package = Package::factory()->forInstructor($instructor)->create([
        'lessons_count' => 1,
        'total_price_pence' => 3500,
        'active' => true,
    ]);

    SlotOffer::factory()->create([
        'calendar_item_id' => $item->id,
        'instructor_id' => $instructor->id,
        'package_id' => $package->id,
        'status' => SlotOfferStatus::Open,
        'message' => 'Short notice lesson',
    ]);

    $list = $this->withHeaders([
        'Authorization' => 'Bearer '.$studentToken,
    ])->getJson('/api/v1/student/slot-offers');

    $list->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.message', 'Short notice lesson');

    $offerId = $list->json('data.0.id');

    $accept = $this->withHeaders([
        'Authorization' => 'Bearer '.$studentToken,
    ])->postJson("/api/v1/student/slot-offers/{$offerId}/accept", [
        'payment_mode' => 'upfront',
    ]);

    $accept->assertCreated()
        ->assertJsonPath('checkout_url', 'https://checkout.stripe.com/test');

    expect(SlotOffer::find($offerId)->status)->toBe(SlotOfferStatus::Booked)
        ->and(SlotOffer::find($offerId)->student_id)->toBe($student->id)
        ->and($item->fresh()->lessons()->count())->toBe(1);
});

test('a second student cannot accept an offer after it has been taken', function () {
    ['instructor' => $instructor] = diaryInstructorToken();
    $item = diaryOpenSlot($instructor);
    ['token' => $firstToken] = diaryStudentToken($instructor);
    ['token' => $secondToken] = diaryStudentToken($instructor);
    $package = Package::factory()->forInstructor($instructor)->create([
        'lessons_count' => 1,
        'total_price_pence' => 3500,
        'active' => true,
    ]);

    $offer = SlotOffer::factory()->create([
        'calendar_item_id' => $item->id,
        'instructor_id' => $instructor->id,
        'package_id' => $package->id,
        'status' => SlotOfferStatus::Open,
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$firstToken,
    ])->postJson("/api/v1/student/slot-offers/{$offer->id}/accept")
        ->assertCreated();

    $this->withHeaders([
        'Authorization' => 'Bearer '.$secondToken,
    ])->postJson("/api/v1/student/slot-offers/{$offer->id}/accept")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slot_offer');
});

test('moving a booked lesson onto another booking is rejected as a clash', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    $date = now()->addDays(5)->toDateString();
    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => $date,
    ]);

    $booked = CalendarItem::factory()->booked()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);
    $other = CalendarItem::factory()->booked()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
    ]);

    $order = Order::factory()->create(['instructor_id' => $instructor->id]);
    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $booked->id,
        'date' => $date,
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->putJson('/api/v1/instructor/calendar/items/'.$booked->id, [
        'date' => $date,
        'start_time' => '14:00',
        'end_time' => '16:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('start_time');

    expect($other->fresh())->not->toBeNull();
});

test('cancelling a booked slot requires a reason and uses CancelBookingAction', function () {
    ['instructor' => $instructor, 'token' => $token] = diaryInstructorToken();
    ['student' => $student] = diaryStudentToken($instructor);
    $item = diaryOpenSlot($instructor);

    $order = Order::factory()->create([
        'instructor_id' => $instructor->id,
        'student_id' => $student->id,
        'payment_mode' => PaymentMode::WEEKLY,
    ]);
    $item->update(['status' => CalendarItemStatus::BOOKED, 'is_available' => false]);
    Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $item->id,
        'date' => $item->calendar->date->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/v1/instructor/calendar/items/'.$item->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/v1/instructor/calendar/items/'.$item->id, [
        'scope' => 'single',
        'reason' => 'Student has moved away.',
    ]);

    $response->assertOk()
        ->assertJsonPath('cancelled_count', 1);

    expect(CalendarItem::find($item->id))->toBeNull();
});
