<?php

declare(strict_types=1);

use App\Actions\Onboarding\CreateOrderFromEnquiryAction;
use App\Enums\PaymentMode;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Enquiry;
use App\Models\Instructor;
use App\Models\LessonPayment;
use App\Models\Package;
use App\Models\Student;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    config([
        'fees.override_to_zero' => true,
        'test_pass_guarantee.price' => 50,
        'test_pass_guarantee.free_minimum_hours' => 10,
    ]);
});

/**
 * Build an enquiry that has reached the summary step with the given package,
 * slot length and add-on choice.
 */
function guaranteeEnquiry(Instructor $instructor, Package $package, string $startTime, string $endTime, bool $optedIn, array $calendarItemIds = []): Enquiry
{
    return Enquiry::create([
        'data' => [
            'current_step' => 5,
            'steps' => [
                'step1' => ['first_name' => 'Test', 'last_name' => 'Learner', 'email' => 'learner@example.com', 'phone' => '07700900000', 'postcode' => 'TS7 0AB', 'privacy_consent' => true],
                'step2' => ['instructor_id' => $instructor->id],
                'step3' => ['package_id' => $package->id],
                'step4' => [
                    'date' => now()->addDays(3)->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'calendar_item_id' => $calendarItemIds[0] ?? null,
                    'calendar_item_ids' => $calendarItemIds,
                ],
                'step5' => ['test_pass_guarantee' => $optedIn],
            ],
        ],
        'current_step' => 5,
        'max_step_reached' => 6,
    ]);
}

/**
 * @return array<int, int>
 */
function guaranteeCalendarItems(Instructor $instructor, int $count, string $startTime, string $endTime): array
{
    $ids = [];

    for ($i = 0; $i < $count; $i++) {
        $calendar = Calendar::factory()->create([
            'instructor_id' => $instructor->id,
            'date' => now()->addDays(3)->addWeeks($i)->toDateString(),
        ]);

        $ids[] = CalendarItem::factory()->create([
            'calendar_id' => $calendar->id,
            'start_time' => $startTime.':00',
            'end_time' => $endTime.':00',
            'is_available' => false,
            'status' => 'draft',
        ])->id;
    }

    return $ids;
}

test('step 5 saves the pass your test guarantee choice', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 3, 'total_price_pence' => 15000]);
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', false);

    $this->post(route('onboarding.step5.store', ['uuid' => $enquiry->id]), [
        'test_pass_guarantee' => true,
    ])->assertRedirect(route('onboarding.step6', ['uuid' => $enquiry->id]));

    expect($enquiry->fresh()->getStepData(5)['test_pass_guarantee'])->toBeTrue();
});

test('step 5 rejects a non-boolean guarantee choice', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 3, 'total_price_pence' => 15000]);
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', false);

    $this->post(route('onboarding.step5.store', ['uuid' => $enquiry->id]), [
        'test_pass_guarantee' => 'maybe',
    ])->assertSessionHasErrors('test_pass_guarantee');
});

test('step 5 tells the learner a 10 hour booking gets the guarantee free when paid in full', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 5, 'total_price_pence' => 25000]);
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', false);

    $this->get(route('onboarding.step5', ['uuid' => $enquiry->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Step5')
            ->where('testPassGuarantee.booked_hours', 10.0)
            ->where('testPassGuarantee.free_when_paid_in_full', true)
            ->where('testPassGuarantee.price', '50.00')
            ->where('testPassGuarantee.upfront.is_free', true)
            ->where('testPassGuarantee.weekly.included', false)
        );
});

test('step 5 offers the paid add-on for bookings under 10 hours', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 4, 'total_price_pence' => 20000]);
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', true);

    $this->get(route('onboarding.step5', ['uuid' => $enquiry->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('testPassGuarantee.booked_hours', 8.0)
            ->where('testPassGuarantee.free_when_paid_in_full', false)
            ->where('testPassGuarantee.opted_in', true)
            ->where('testPassGuarantee.upfront.charge_pence', 5000)
        );
});

test('step 6 adds the paid add-on to the pay in full total', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 4, 'total_price_pence' => 20000]);
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', true);

    $this->get(route('onboarding.step6', ['uuid' => $enquiry->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding/Step6')
            ->where('pricing.upfront.total', '£250.00')
            ->where('pricing.weekly.first_payment', '£100.00')
            ->where('pricing.weekly.total_over_time', '£250.00')
        );
});

test('a 10 hour booking paid in full includes the guarantee free', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 5, 'total_price_pence' => 25000]);
    $itemIds = guaranteeCalendarItems($instructor, 5, '10:00', '12:00');
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', false, $itemIds);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = app(CreateOrderFromEnquiryAction::class)->execute($enquiry, $student, $package, PaymentMode::UPFRONT);

    expect($order->includes_test_pass_guarantee)->toBeTrue();
    expect($order->test_pass_guarantee_pence)->toBe(0);
    expect($order->total_price_pence)->toBe(25000);
});

test('a 10 hour booking paid weekly without opting in does not include the guarantee', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 5, 'total_price_pence' => 25000]);
    $itemIds = guaranteeCalendarItems($instructor, 5, '10:00', '12:00');
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', false, $itemIds);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = app(CreateOrderFromEnquiryAction::class)->execute($enquiry, $student, $package, PaymentMode::WEEKLY);

    expect($order->includes_test_pass_guarantee)->toBeFalse();
    expect($order->test_pass_guarantee_pence)->toBe(0);
    expect(LessonPayment::whereIn('lesson_id', $order->lessons()->pluck('id'))->sum('test_pass_guarantee_pence'))->toEqual(0);
});

test('a weekly payer who opts in is charged the add-on on their first weekly payment', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 5, 'total_price_pence' => 25000]);
    $itemIds = guaranteeCalendarItems($instructor, 5, '10:00', '12:00');
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', true, $itemIds);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = app(CreateOrderFromEnquiryAction::class)->execute($enquiry, $student, $package, PaymentMode::WEEKLY);

    expect($order->includes_test_pass_guarantee)->toBeTrue();
    expect($order->test_pass_guarantee_pence)->toBe(5000);
    expect($order->total_price_pence)->toBe(30000);

    $payments = LessonPayment::query()
        ->join('lessons', 'lessons.id', '=', 'lesson_payments.lesson_id')
        ->where('lessons.order_id', $order->id)
        ->orderBy('lessons.date')
        ->select('lesson_payments.*')
        ->get();

    expect($payments)->toHaveCount(5);
    expect($payments->first()->amount_pence)->toBe(10000);
    expect($payments->first()->test_pass_guarantee_pence)->toBe(5000);
    expect($payments->skip(1)->pluck('amount_pence')->all())->toBe([5000, 5000, 5000, 5000]);
    expect($payments->sum('amount_pence'))->toBe(30000);
});

test('a small booking paid in full that opts in is charged the add-on', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 2, 'total_price_pence' => 10000]);
    $itemIds = guaranteeCalendarItems($instructor, 2, '10:00', '12:00');
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', true, $itemIds);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = app(CreateOrderFromEnquiryAction::class)->execute($enquiry, $student, $package, PaymentMode::UPFRONT);

    expect($order->includes_test_pass_guarantee)->toBeTrue();
    expect($order->test_pass_guarantee_pence)->toBe(5000);
    expect($order->total_price_pence)->toBe(15000);
});

test('a small booking paid in full without opting in does not include the guarantee', function () {
    $instructor = Instructor::factory()->create();
    $package = Package::factory()->create(['lessons_count' => 2, 'total_price_pence' => 10000]);
    $itemIds = guaranteeCalendarItems($instructor, 2, '10:00', '12:00');
    $enquiry = guaranteeEnquiry($instructor, $package, '10:00', '12:00', false, $itemIds);
    $student = Student::factory()->create(['instructor_id' => $instructor->id]);

    $order = app(CreateOrderFromEnquiryAction::class)->execute($enquiry, $student, $package, PaymentMode::UPFRONT);

    expect($order->includes_test_pass_guarantee)->toBeFalse();
    expect($order->total_price_pence)->toBe(10000);
});
