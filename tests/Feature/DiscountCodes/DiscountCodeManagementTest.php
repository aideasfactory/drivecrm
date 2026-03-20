<?php

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

test('guests cannot view discount codes index', function () {
    $response = $this->get(route('discount-codes.index'));

    $response->assertRedirect();
});

test('authenticated users can view discount codes index', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('discount-codes.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('DiscountCodes/Index')
        ->has('discountCodes')
    );
});

test('discount codes index displays existing codes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    DiscountCode::factory()->create([
        'label' => 'Summer Promo',
        'percentage' => 10,
    ]);

    $response = $this->get(route('discount-codes.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('DiscountCodes/Index')
        ->has('discountCodes', 1)
        ->where('discountCodes.0.label', 'Summer Promo')
        ->where('discountCodes.0.percentage', 10)
        ->where('discountCodes.0.formatted_percentage', '10% off')
    );
});

test('guests cannot create discount codes', function () {
    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->postJson(route('discount-codes.store'), [
            'label' => 'Test Discount',
            'percentage' => 10,
        ]);

    $response->assertUnauthorized();
});

test('authenticated users can create a discount code', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('discount-codes.store'), [
            'label' => 'New Year Sale',
            'percentage' => 15,
        ]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'label' => 'New Year Sale',
        'percentage' => 15,
        'formatted_percentage' => '15% off',
        'active' => true,
    ]);

    $this->assertDatabaseHas('discount_codes', [
        'label' => 'New Year Sale',
        'percentage' => 15,
        'active' => true,
    ]);
});

test('discount code gets a UUID on creation', function () {
    $user = User::factory()->create();

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('discount-codes.store'), [
            'label' => 'UUID Test',
            'percentage' => 5,
        ]);

    $code = DiscountCode::where('label', 'UUID Test')->first();

    expect($code)->not->toBeNull();
    expect($code->id)->toBeString();
    expect(strlen($code->id))->toBe(36);
});

test('store discount code validates required fields', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('discount-codes.store'), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['label', 'percentage']);
});

test('store discount code validates percentage must be 5, 10, 15, or 20', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->postJson(route('discount-codes.store'), [
            'label' => 'Invalid Discount',
            'percentage' => 25,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['percentage']);
});

test('authenticated users can delete a discount code', function () {
    $user = User::factory()->create();
    $code = DiscountCode::factory()->create(['label' => 'To Delete']);

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->actingAs($user)
        ->deleteJson(route('discount-codes.destroy', $code));

    $response->assertNoContent();
    $this->assertDatabaseMissing('discount_codes', ['id' => $code->id]);
});

test('guests cannot delete discount codes', function () {
    $code = DiscountCode::factory()->create();

    $response = $this->withoutMiddleware(ValidateCsrfToken::class)
        ->deleteJson(route('discount-codes.destroy', $code));

    $response->assertUnauthorized();
});
