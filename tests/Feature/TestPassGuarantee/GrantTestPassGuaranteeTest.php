<?php

declare(strict_types=1);

use App\Actions\Student\GrantTestPassGuaranteeAction;
use App\Enums\PaymentMode;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('it flags the student when the order includes the guarantee', function () {
    $order = Order::factory()->create([
        'payment_mode' => PaymentMode::UPFRONT,
        'includes_test_pass_guarantee' => true,
        'test_pass_guarantee_pence' => 0,
    ]);

    $student = app(GrantTestPassGuaranteeAction::class)($order);

    expect($student->hasTestPassGuarantee())->toBeTrue();
    expect($student->fresh()->test_pass_guarantee_order_id)->toBe($order->id);
});

test('it does nothing when the order does not include the guarantee', function () {
    $order = Order::factory()->create(['includes_test_pass_guarantee' => false]);

    expect(app(GrantTestPassGuaranteeAction::class)($order))->toBeNull();
    expect($order->student->fresh()->hasTestPassGuarantee())->toBeFalse();
});

test('granting twice keeps the original grant', function () {
    $order = Order::factory()->create([
        'includes_test_pass_guarantee' => true,
        'test_pass_guarantee_pence' => 5000,
    ]);

    app(GrantTestPassGuaranteeAction::class)($order);
    $firstGrantAt = $order->student->fresh()->test_pass_guarantee_at;

    $this->travel(1)->days();
    app(GrantTestPassGuaranteeAction::class)($order->fresh());

    expect($order->student->fresh()->test_pass_guarantee_at->equalTo($firstGrantAt))->toBeTrue();
});

test('the admin student detail shows the guarantee flag', function () {
    $this->actingAs(User::factory()->create());

    $student = Student::factory()->withTestPassGuarantee()->create();

    $this->getJson("/students/{$student->id}")
        ->assertSuccessful()
        ->assertJsonPath('student.has_test_pass_guarantee', true);
});

test('the admin student detail shows no flag for other students', function () {
    $this->actingAs(User::factory()->create());

    $student = Student::factory()->create();

    $this->getJson("/students/{$student->id}")
        ->assertSuccessful()
        ->assertJsonPath('student.has_test_pass_guarantee', false)
        ->assertJsonPath('student.test_pass_guarantee_at', null);
});

test('the pupils index shows the guarantee flag', function () {
    $this->actingAs(User::factory()->create());

    Student::factory()->withTestPassGuarantee()->create();

    $this->get(route('pupils.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Pupils/Index')
            ->where('pupils.0.has_test_pass_guarantee', true)
        );
});
