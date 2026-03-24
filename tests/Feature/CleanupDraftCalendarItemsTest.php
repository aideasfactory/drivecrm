<?php

use App\Enums\CalendarItemStatus;
use App\Models\CalendarItem;

test('it resets draft calendar items created before today back to available', function () {
    // Draft item created yesterday — should be reset
    $draft = CalendarItem::factory()->create([
        'is_available' => false,
        'status' => CalendarItemStatus::DRAFT,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('calendar:cleanup-drafts')
        ->expectsOutputToContain('Reset 1 draft calendar item(s) back to available.')
        ->assertSuccessful();

    $draft->refresh();
    expect($draft->is_available)->toBeTrue();
    expect($draft->status)->toBeNull();
});

test('it does not reset draft items created today', function () {
    $draft = CalendarItem::factory()->create([
        'is_available' => false,
        'status' => CalendarItemStatus::DRAFT,
        'created_at' => now(),
    ]);

    $this->artisan('calendar:cleanup-drafts')
        ->expectsOutputToContain('Reset 0 draft calendar item(s) back to available.')
        ->assertSuccessful();

    $draft->refresh();
    expect($draft->status)->toBe(CalendarItemStatus::DRAFT);
    expect($draft->is_available)->toBeFalse();
});

test('it does not touch booked or reserved items', function () {
    CalendarItem::factory()->create([
        'is_available' => true,
        'status' => CalendarItemStatus::BOOKED,
        'created_at' => now()->subDay(),
    ]);

    CalendarItem::factory()->create([
        'is_available' => true,
        'status' => CalendarItemStatus::RESERVED,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('calendar:cleanup-drafts')
        ->expectsOutputToContain('Reset 0 draft calendar item(s) back to available.')
        ->assertSuccessful();
});

test('dry run shows count without resetting', function () {
    $draft = CalendarItem::factory()->create([
        'is_available' => false,
        'status' => CalendarItemStatus::DRAFT,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('calendar:cleanup-drafts --dry-run')
        ->expectsOutputToContain('Would reset 1 draft calendar item(s) to available.')
        ->assertSuccessful();

    $draft->refresh();
    expect($draft->status)->toBe(CalendarItemStatus::DRAFT);
    expect($draft->is_available)->toBeFalse();
});
