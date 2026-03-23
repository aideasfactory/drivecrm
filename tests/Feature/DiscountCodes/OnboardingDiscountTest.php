<?php

use App\Models\DiscountCode;
use App\Models\Enquiry;

test('onboarding start stores discount in enquiry when valid UUID provided', function () {
    $discount = DiscountCode::factory()->create([
        'label' => 'Test Discount',
        'percentage' => 10,
        'active' => true,
    ]);

    $response = $this->get('/onboarding?discount=' . $discount->id);

    $response->assertRedirect();

    $enquiry = Enquiry::latest()->first();
    expect($enquiry)->not->toBeNull();

    $discountData = $enquiry->getDiscountData();
    expect($discountData)->not->toBeNull();
    expect($discountData['id'])->toBe($discount->id);
    expect($discountData['percentage'])->toBe(10);
    expect($discountData['label'])->toBe('Test Discount');
});

test('onboarding start ignores inactive discount code', function () {
    $discount = DiscountCode::factory()->inactive()->create();

    $response = $this->get('/onboarding?discount=' . $discount->id);

    $response->assertRedirect();

    $enquiry = Enquiry::latest()->first();
    expect($enquiry)->not->toBeNull();
    expect($enquiry->getDiscountData())->toBeNull();
});

test('onboarding start ignores invalid UUID', function () {
    $response = $this->get('/onboarding?discount=invalid-uuid-here');

    $response->assertRedirect();

    $enquiry = Enquiry::latest()->first();
    expect($enquiry)->not->toBeNull();
    expect($enquiry->getDiscountData())->toBeNull();
});

test('onboarding start works without discount parameter', function () {
    $response = $this->get('/onboarding');

    $response->assertRedirect();

    $enquiry = Enquiry::latest()->first();
    expect($enquiry)->not->toBeNull();
    expect($enquiry->getDiscountData())->toBeNull();
});

test('step 3 passes discount data to frontend', function () {
    $discount = DiscountCode::factory()->create([
        'label' => 'Spring Sale',
        'percentage' => 15,
        'active' => true,
    ]);

    // Create enquiry with discount
    $enquiry = Enquiry::create([
        'data' => [
            'current_step' => 1,
            'steps' => [
                'step1' => ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@test.com', 'phone' => '07700900000', 'postcode' => 'TS7 0AB', 'privacy_consent' => true],
                'step2' => ['instructor_id' => 1],
            ],
            'discount' => [
                'id' => $discount->id,
                'label' => 'Spring Sale',
                'percentage' => 15,
            ],
        ],
        'current_step' => 3,
        'max_step_reached' => 3,
    ]);

    $response = $this->get(route('onboarding.step3', ['uuid' => $enquiry->id]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Onboarding/Step3')
        ->has('discount')
        ->where('discount.percentage', 15)
        ->where('discount.label', 'Spring Sale')
    );
});
