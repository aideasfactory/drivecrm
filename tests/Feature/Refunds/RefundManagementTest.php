<?php

declare(strict_types=1);

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundAction;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Student;
use App\Models\User;
use App\Services\StripeService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function ownerUser(): User
{
    return User::factory()->create(['role' => UserRole::OWNER]);
}

/**
 * @return array{owner: User, instructor: Instructor, student: Student, order: Order, lesson: Lesson, calendarItem: CalendarItem}
 */
function paidWeeklyBooking(array $overrides = []): array
{
    $owner = $overrides['owner'] ?? ownerUser();
    $instructor = Instructor::factory()->create();
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = Order::factory()->create([
        'student_id' => $student->id,
        'instructor_id' => $instructor->id,
        'payment_mode' => PaymentMode::WEEKLY,
        'status' => OrderStatus::ACTIVE,
    ]);

    $calendar = Calendar::factory()->create([
        'instructor_id' => $instructor->id,
        'date' => '2026-08-12',
    ]);

    $calendarItem = CalendarItem::factory()->create([
        'calendar_id' => $calendar->id,
        'start_time' => '14:00:00',
        'end_time' => '16:00:00',
        'status' => CalendarItemStatus::BOOKED,
        'is_available' => false,
    ]);

    $lesson = Lesson::factory()->create([
        'order_id' => $order->id,
        'instructor_id' => $instructor->id,
        'calendar_item_id' => $calendarItem->id,
        'date' => '2026-08-12',
        'start_time' => '14:00',
        'end_time' => '16:00',
        'amount_pence' => 4000,
        'status' => LessonStatus::PENDING,
        'student_lesson_number' => 1,
    ]);

    LessonPayment::factory()->paid()->create([
        'lesson_id' => $lesson->id,
        'amount_pence' => 4000,
        'stripe_charge_id' => 'ch_test_paid_lesson',
    ]);

    return compact('owner', 'instructor', 'student', 'order', 'lesson', 'calendarItem');
}

beforeEach(function () {
    Notification::fake();
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('guests are redirected from the refunds page', function () {
    $this->get(route('refunds.index'))->assertRedirect(route('login'));
});

test('non-owners cannot view refunds', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $this->actingAs($user);

    $this->get(route('refunds.index'))->assertForbidden();
});

test('non-owners cannot process or mark refunds complete', function () {
    $user = User::factory()->create(['role' => UserRole::STUDENT]);
    $this->actingAs($user);

    $refund = Refund::factory()->create();

    $this->post(route('refunds.process', $refund))->assertForbidden();
    $this->post(route('refunds.complete', $refund))->assertForbidden();
});

test('owners can view refunds newest first with running totals', function () {
    Carbon::setTestNow('2026-08-27 14:24:00');

    $owner = ownerUser();
    $this->actingAs($owner);

    $older = Refund::factory()->create([
        'amount_pence' => 3000,
        'status' => RefundStatus::PENDING,
        'requested_at' => now()->subDay(),
    ]);
    $newer = Refund::factory()->create([
        'amount_pence' => 4500,
        'status' => RefundStatus::PENDING,
        'requested_at' => now(),
    ]);
    Refund::factory()->completed()->create([
        'amount_pence' => 2000,
        'requested_at' => now()->subDays(2),
        'processed_by_user_id' => $owner->id,
        'completed_at' => now()->subDay(),
    ]);

    $this->get(route('refunds.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Refunds/Index')
            ->has('refunds.data', 3)
            ->where('refunds.data.0.id', $newer->id)
            ->where('refunds.data.1.id', $older->id)
            ->where('totals.pending_count', 2)
            ->where('totals.pending_amount_pence', 7500)
            ->where('totals.completed_count', 1)
            ->where('totals.completed_amount_pence', 2000)
            ->where('totals.requested_count', 3)
            ->where('totals.requested_amount_pence', 9500)
        );

    Carbon::setTestNow();
});

test('cancelling a paid lesson queues a pending refund for the dashboard', function () {
    $booking = paidWeeklyBooking();
    $this->actingAs($booking['owner']);

    $response = $this->deleteJson(
        route('instructors.calendar.items.destroy', [
            'instructor' => $booking['instructor'],
            'calendarItem' => $booking['calendarItem'],
        ]),
        [
            'reason' => 'Student has moved away.',
            'scope' => 'single',
            'refund_action' => RefundAction::REQUEST->value,
        ],
    );

    $response->assertOk()
        ->assertJsonPath('cancelled_count', 1)
        ->assertJsonPath('refunds_created_count', 1)
        ->assertJsonPath('refund_required_count', 1);

    $refund = Refund::query()->where('lesson_id', $booking['lesson']->id)->first();

    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(RefundStatus::PENDING)
        ->and($refund->amount_pence)->toBe(4000)
        ->and($refund->requested_by_user_id)->toBe($booking['owner']->id)
        ->and($refund->reason)->toBe('Student has moved away.');
});

test('cancelling a paid lesson can skip creating a refund', function () {
    $booking = paidWeeklyBooking();
    $this->actingAs($booking['owner']);

    $this->deleteJson(
        route('instructors.calendar.items.destroy', [
            'instructor' => $booking['instructor'],
            'calendarItem' => $booking['calendarItem'],
        ]),
        [
            'reason' => 'No refund needed.',
            'refund_action' => RefundAction::NONE->value,
        ],
    )->assertOk();

    expect(Refund::query()->where('lesson_id', $booking['lesson']->id)->exists())->toBeFalse();
});

test('cancelling an unpaid lesson does not create a refund', function () {
    $booking = paidWeeklyBooking();
    $booking['lesson']->lessonPayment->update(['status' => PaymentStatus::DUE, 'paid_at' => null]);
    $this->actingAs($booking['owner']);

    $this->deleteJson(
        route('instructors.calendar.items.destroy', [
            'instructor' => $booking['instructor'],
            'calendarItem' => $booking['calendarItem'],
        ]),
        ['reason' => 'Never paid.'],
    )->assertOk();

    expect(Refund::query()->count())->toBe(0);
});

test('owners can issue a pending refund through stripe and record a paper trail', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-07 14:24:00'));

    $owner = User::factory()->create([
        'name' => 'Gavin Boak',
        'role' => UserRole::OWNER,
    ]);
    $this->actingAs($owner);

    $booking = paidWeeklyBooking(['owner' => $owner]);

    $refund = Refund::factory()->create([
        'lesson_id' => $booking['lesson']->id,
        'order_id' => $booking['order']->id,
        'lesson_payment_id' => $booking['lesson']->lessonPayment->id,
        'student_id' => $booking['student']->id,
        'instructor_id' => $booking['instructor']->id,
        'requested_by_user_id' => $owner->id,
        'amount_pence' => 4000,
        'status' => RefundStatus::PENDING,
        'requested_at' => now()->subHour(),
    ]);

    $this->mock(StripeService::class, function ($mock) {
        $mock->shouldReceive('createRefund')
            ->once()
            ->with('ch_test_paid_lesson', 4000, Mockery::type('array'), 'refund-'.$refund->id)
            ->andReturn(['success' => true, 'refund_id' => 're_test123']);
    });

    $this->post(route('refunds.process', $refund))
        ->assertRedirect()
        ->assertSessionHas('success', 'Gavin Boak made refund on 07/08/2026 14:24');

    $refund->refresh();

    expect($refund->status)->toBe(RefundStatus::COMPLETED)
        ->and($refund->method)->toBe(RefundMethod::STRIPE)
        ->and($refund->stripe_refund_id)->toBe('re_test123')
        ->and($refund->processed_by_user_id)->toBe($owner->id)
        ->and($refund->paperTrail())->toBe('Gavin Boak made refund on 07/08/2026 14:24');

    expect($booking['lesson']->lessonPayment->fresh()->status)->toBe(PaymentStatus::REFUNDED);

    Carbon::setTestNow();
});

test('owners can mark a refund complete after refunding in stripe by hand', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-07 14:24:00'));

    $owner = User::factory()->create([
        'name' => 'Gavin Boak',
        'role' => UserRole::OWNER,
    ]);
    $this->actingAs($owner);

    $booking = paidWeeklyBooking(['owner' => $owner]);

    $refund = Refund::factory()->create([
        'lesson_id' => $booking['lesson']->id,
        'order_id' => $booking['order']->id,
        'lesson_payment_id' => $booking['lesson']->lessonPayment->id,
        'student_id' => $booking['student']->id,
        'instructor_id' => $booking['instructor']->id,
        'requested_by_user_id' => $owner->id,
        'amount_pence' => 4000,
        'status' => RefundStatus::PENDING,
        'requested_at' => now()->subHour(),
    ]);

    $this->post(route('refunds.complete', $refund))
        ->assertRedirect()
        ->assertSessionHas('success', 'Gavin Boak made refund on 07/08/2026 14:24');

    $refund->refresh();

    expect($refund->status)->toBe(RefundStatus::COMPLETED)
        ->and($refund->method)->toBe(RefundMethod::MANUAL)
        ->and($refund->processed_by_user_id)->toBe($owner->id)
        ->and($booking['lesson']->lessonPayment->fresh()->status)->toBe(PaymentStatus::REFUNDED);

    Carbon::setTestNow();
});

test('a completed refund cannot be processed again', function () {
    $owner = ownerUser();
    $this->actingAs($owner);

    $refund = Refund::factory()->completed()->create([
        'processed_by_user_id' => $owner->id,
    ]);

    $this->post(route('refunds.process', $refund))
        ->assertRedirect()
        ->assertSessionHas('error', 'This refund has already been completed.');
});

test('cancelling a paid lesson with stripe action issues the refund immediately', function () {
    $owner = User::factory()->create([
        'name' => 'Gavin Boak',
        'role' => UserRole::OWNER,
    ]);
    $booking = paidWeeklyBooking(['owner' => $owner]);
    $this->actingAs($owner);

    $this->mock(StripeService::class, function ($mock) {
        $mock->shouldReceive('createRefund')
            ->once()
            ->andReturn(['success' => true, 'refund_id' => 're_from_diary']);
    });

    $this->deleteJson(
        route('instructors.calendar.items.destroy', [
            'instructor' => $booking['instructor'],
            'calendarItem' => $booking['calendarItem'],
        ]),
        [
            'reason' => 'Staff refund from diary.',
            'refund_action' => RefundAction::STRIPE->value,
        ],
    )
        ->assertOk()
        ->assertJsonPath('refunds_created_count', 1)
        ->assertJsonPath('refunds_processed_count', 1)
        ->assertJsonPath('refund_required_count', 0);

    $refund = Refund::query()->where('lesson_id', $booking['lesson']->id)->first();

    expect($refund?->status)->toBe(RefundStatus::COMPLETED)
        ->and($refund?->method)->toBe(RefundMethod::STRIPE)
        ->and($refund?->processed_by_user_id)->toBe($owner->id);
});
