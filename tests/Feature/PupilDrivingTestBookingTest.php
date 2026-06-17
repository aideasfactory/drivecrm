<?php

declare(strict_types=1);

use App\Actions\Student\BookDrivingTestAction;
use App\Actions\Student\CancelDrivingTestAction;
use App\Actions\Student\Checklist\GetStudentChecklistAction;
use App\Enums\CalendarItemType;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Student;

beforeEach(function () {
    $this->instructor = Instructor::factory()->create();
    $this->student = Student::factory()->create([
        'instructor_id' => $this->instructor->id,
        'first_name' => 'Alex',
        'surname' => 'Pupil',
    ]);

    // Seed the default checklist rows so book_practical_test exists.
    (new GetStudentChecklistAction)($this->student);
});

test('booking a driving test creates a linked practical-test calendar item', function () {
    $calendarItem = app(BookDrivingTestAction::class)(
        $this->student,
        '2026-08-12',
        '11:00',
    );

    // 2.5hr blocked window: 10:00 prep → 11:00 test → 12:00 end → 12:30 buffer
    expect($calendarItem->item_type)->toBe(CalendarItemType::PracticalTest);
    expect($calendarItem->start_time)->toBe('10:00:00');
    expect($calendarItem->end_time)->toBe('12:30:00');
    expect($calendarItem->student_id)->toBe($this->student->id);
    expect($calendarItem->calendar->instructor_id)->toBe($this->instructor->id);
    expect($calendarItem->notes)->toBe('Alex Pupil');
    expect((bool) $calendarItem->is_available)->toBeFalse();
});

test('booking a driving test ticks the book_practical_test checklist row', function () {
    $calendarItem = app(BookDrivingTestAction::class)(
        $this->student,
        '2026-08-12',
        '11:00',
    );

    $checklistItem = $this->student->checklistItems()
        ->where('key', 'book_practical_test')
        ->firstOrFail();

    expect((bool) $checklistItem->is_checked)->toBeTrue();
    expect($checklistItem->date->format('Y-m-d'))->toBe('2026-08-12');
    expect($checklistItem->calendar_item_id)->toBe($calendarItem->id);
});

test('cancelling a driving test removes the diary slot and unticks the checklist', function () {
    $calendarItem = app(BookDrivingTestAction::class)(
        $this->student,
        '2026-08-12',
        '11:00',
    );

    app(CancelDrivingTestAction::class)($this->student);

    expect(CalendarItem::find($calendarItem->id))->toBeNull();

    $checklistItem = $this->student->checklistItems()
        ->where('key', 'book_practical_test')
        ->firstOrFail();

    expect((bool) $checklistItem->is_checked)->toBeFalse();
    expect($checklistItem->date)->toBeNull();
    expect($checklistItem->calendar_item_id)->toBeNull();
});

test('booking a second test for the same pupil replaces the first', function () {
    $first = app(BookDrivingTestAction::class)(
        $this->student,
        '2026-08-12',
        '11:00',
    );

    $second = app(BookDrivingTestAction::class)(
        $this->student,
        '2026-09-04',
        '14:00',
    );

    expect(CalendarItem::find($first->id))->toBeNull();
    expect($second->student_id)->toBe($this->student->id);
    expect($second->id)->not->toBe($first->id);

    $checklistItem = $this->student->checklistItems()
        ->where('key', 'book_practical_test')
        ->firstOrFail();

    expect($checklistItem->calendar_item_id)->toBe($second->id);
});

test('booking fails cleanly when the pupil has no instructor', function () {
    $orphan = Student::factory()->create(['instructor_id' => null]);
    (new GetStudentChecklistAction)($orphan);

    expect(
        fn () => app(BookDrivingTestAction::class)($orphan, '2026-08-12', '11:00'),
    )->toThrow(RuntimeException::class);
});
