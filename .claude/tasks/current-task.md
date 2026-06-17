# Task: Pupil Driving-Test → Instructor Diary Integration

## Overview

Link the "Book practical test" pupil action (checklist) to the instructor's diary
so a pupil's test date *is* a practical-test calendar slot, and a practical-test
calendar slot created in the diary can be assigned to a specific pupil.

Today the two flows are independent:

- `app/Actions/Instructor/CreateCalendarItemAction.php` can mark a calendar item
  as a `practical_test` (item_type) — blocks 1hr prep + 1hr test + 30min buffer
  — but only stores the student name as free-text in `notes`. There is no FK to
  `students`.
- `app/Models/StudentChecklistItem.php` has a `book_practical_test` item that,
  when ticked, captures a date + notes on the checklist row. It does not touch
  the calendar.

We need a single source of truth for a pupil's practical test:

1. The pupil's "Book Driving Test" action creates a calendar item on their
   instructor's diary AND marks the checklist item `book_practical_test` as
   checked with the test date.
2. Looking at the pupil shows the linked diary slot.
3. Looking at the diary shows the linked pupil name on the practical-test slot.
4. Cancelling from either side unwires both.

## Phase 1: Planning ✅

### Surface-area decisions
- **Add `student_id` to `calendar_items`** — nullable FK. Only meaningful for
  `item_type = practical_test`. Doesn't disturb regular lesson slots (those
  still link to students via `lessons.order.student`).
- **Add `calendar_item_id` to `student_checklist_items`** — nullable FK for the
  `book_practical_test` row to point at the diary slot. Lets the checklist item
  know which slot to delete if unchecked, and lets the UI show "View in diary".
- **Reuse `CreateCalendarItemAction`** — accept an optional `Student` parameter.
  This keeps the diary-side creation logic in one place. The new "Book Driving
  Test" action wraps it and *also* updates the checklist.
- **New Action: `BookDrivingTestAction`** in `app/Actions/Student/` — invokes
  `CreateCalendarItemAction` with the pupil's instructor, then ticks the
  `book_practical_test` checklist row and stores the new calendar item id on it.
- **New Action: `CancelDrivingTestAction`** — inverse: deletes the linked
  calendar item if any, unticks the checklist row.
- **New PupilController endpoints:**
  - `POST /students/{student}/driving-test` — book the test (date, time)
  - `DELETE /students/{student}/driving-test` — cancel the booked test
  - `GET /students/{student}/driving-test` — returns current booked slot (or null)
- **Hook the existing checklist toggle path** for `book_practical_test` so a
  manual tick/untick from the checklist UI also creates/deletes the diary slot.
  This is the "if an instructor adds a test date on the pupil record, it should
  appear on the diary" requirement.
- **Surface student on practical-test slots in the diary** — extend
  `GetInstructorCalendarAction` to set `student_name` and `student_id` for
  practical-test items (not just lessons).

### Out of scope
- Editing the test date once booked (can be done via cancel + re-book).
- DVSA integration / external booking.
- Notifications when a test is added/cancelled (instructor adds the test
  themselves; no external party to notify in this iteration).

## Phase 2: Implementation ✅

### Backend

- [x] Migration `2026_06_17_100000_add_student_id_to_calendar_items_table.php`
      — nullable FK + index. `.claude/database-schema.md` updated.
- [x] Migration
      `2026_06_17_100001_add_calendar_item_id_to_student_checklist_items_table.php`
      — nullable FK + index. `.claude/database-schema.md` updated.
- [x] `CalendarItem` model — `student_id` fillable + `student()` BelongsTo.
- [x] `StudentChecklistItem` model — `calendar_item_id` fillable +
      `calendarItem()` BelongsTo.
- [x] `CreateCalendarItemAction` — accepts `?Student $student = null` and
      persists it on the practical-test branch (also uses pupil name as the
      default `notes`).
- [x] `InstructorService::addCalendarItem` — pass `student` through.
- [x] `StoreCalendarItemRequest` — accepts nullable `student_id` (exists check).
- [x] `InstructorController::storeCalendarItem` — resolves Student from
      `student_id` and passes it through.
- [x] `formatCalendarItem` and `GetInstructorCalendarAction` surface
      `student_id` and `student_name` on practical-test rows.
- [x] `BookDrivingTestAction` in `app/Actions/Student/`.
- [x] `CancelDrivingTestAction` in `app/Actions/Student/`.
- [x] `ToggleChecklistItemAction` — special-cases `book_practical_test` to
      route through the two actions above.
- [x] `PupilController` — `showDrivingTest`, `bookDrivingTest`,
      `cancelDrivingTest`. Also accepts `start_time` on the existing toggle
      endpoint.
- [x] Routes in `routes/web.php`.
- [ ] api.md — n/a (these are web routes used only by the admin Vue UI; the
      existing checklist toggle route is also undocumented in api.md, which is
      scoped to the mobile `/api/v1/*` surface).

### Frontend

- [x] `StudentChecklistSection.vue` — dedicated "Book Driving Test" dialog
      (date + time) for the `book_practical_test` row; new badges show the
      booked date, test window, and "On instructor diary" confirmation.
- [x] `types/instructor.ts` — added `student_id` to `CalendarItemResponse`.

### Tests

- [x] `tests/Feature/PupilDrivingTestBookingTest.php` (5 Pest tests covering
      book, cancel, re-book, checklist link, missing-instructor failure path).
      Per project rule, not run.

## Phase 3: Reflection ✅

- `results.md` written to the project root with a client-facing summary and
  a confidence score of 8/10.

**Why this shape:**
- Reused `CreateCalendarItemAction` end-to-end — the new BookDrivingTest
  action is the thinnest possible wrapper that also touches the checklist.
- A single nullable FK on each side (calendar_items, checklist_items) is
  enough to keep both views aligned. No extra `driving_test_bookings`
  table — the calendar item *is* the booking.
- Treated the pupil flow as the canonical entry point. Direct diary
  deletion is a less common path, called out in results.md as a follow-up.

**Risks acknowledged:**
- Direct deletion of a practical-test slot from the schedule tab leaves the
  pupil's checklist row out of sync until the next page load (the `nullOnDelete`
  on the checklist FK keeps the row valid, but `is_checked` stays true). A
  Model observer on `CalendarItem::deleted` would close this gap — flagged
  for follow-up.
- Mobile API does not yet expose driving-test endpoints. Flagged for
  follow-up.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17
