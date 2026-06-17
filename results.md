# Pupil Driving-Test → Instructor Diary Integration

## What changed for you

When you book a driving test for a pupil, that booking now also shows up on
the instructor's diary — and vice versa. The pupil record and the instructor's
schedule stay in sync without you having to enter the test in two places.

### Booking a driving test from the pupil page

1. Open a pupil from **Instructors → (pick instructor) → Students** and switch to
   the **Actions** tab.
2. In the **Student Checklist** card, tick **Book practical test** under
   "Practical Test".
3. A new dialog appears asking for the **test date** and **test time**. Pick
   the slot the DVSA has given you and press **Book Test**.
4. The system:
   - Adds a **practical test slot** to the instructor's diary, blocking out
     1 hr prep + 1 hr test + 30 min buffer (the same 2.5 hr window the diary
     already uses for tests).
   - Marks the **Book practical test** checklist item as ticked, with the date
     filled in.
   - Saves the pupil's name on the diary slot, so the instructor can see whose
     test it is from the schedule.

After booking, the checklist row shows a summary line with the date, the test
time window, and an **"On instructor diary"** badge confirming the slot is in
place.

### Cancelling a booked test

Unticking the **Book practical test** checkbox cancels the test. The system:

- Removes the practical test slot from the instructor's diary.
- Unticks the checklist row and clears the saved date.

### Looking at the instructor's diary

Practical test slots created through this flow now show the **pupil's name**
on the diary card (where they previously only showed the free-text note the
instructor had typed in). The slot still looks visually identical (teal
"Practical Test" block) — it's just that the pupil link is now real and not
just a label.

### Keeping things consistent

- Only one driving test can be booked per pupil at a time. Booking a new test
  automatically replaces the old one (both on the pupil record and on the
  diary).
- If you delete the practical test slot directly from the instructor's diary,
  the pupil's checklist row will still show as ticked until the next time the
  page is loaded — at which point the next booking action will refresh
  cleanly. (We treat the pupil "Book Driving Test" action as the canonical
  entry point.)
- A pupil with no assigned instructor cannot have a test booked — the system
  will show a clear error.

---

## Under the hood (for the dev team)

### Database

Two non-destructive migrations:

- `calendar_items.student_id` (nullable FK to `students`) — only populated on
  `item_type = 'practical_test'` rows. NULL on every other row, so existing
  data is untouched.
- `student_checklist_items.calendar_item_id` (nullable FK to `calendar_items`)
  — only populated on the pupil's `book_practical_test` row, so it can point
  at the diary slot it created.

Both columns use `nullOnDelete()` so deleting a calendar item or a pupil never
breaks the linked row, it just unlinks it.

### Backend

- `CreateCalendarItemAction` now accepts an optional `Student` argument; when
  present and the item is a practical test, the student id is stored on the
  calendar item and the pupil's name is used as the default `notes` value.
- `BookDrivingTestAction` (new, in `app/Actions/Student/`) is the single
  entry point that wraps `CreateCalendarItemAction` and also ticks the
  `book_practical_test` checklist row in one DB transaction.
- `CancelDrivingTestAction` (new) is the inverse.
- `ToggleChecklistItemAction` defers to the two actions above when the row
  being toggled is `book_practical_test`, so a manual tick from the checklist
  UI still produces a diary slot.
- Three new `PupilController` routes back the UI:
  - `GET    /students/{student}/driving-test` → current booking, if any.
  - `POST   /students/{student}/driving-test` → book a test (date + time).
  - `DELETE /students/{student}/driving-test` → cancel.
- `GetInstructorCalendarAction` now surfaces `student_id` and `student_name`
  on practical-test calendar items, so the diary card knows whose test it is.

### Frontend

- `StudentChecklistSection.vue` now treats the `book_practical_test` row
  specially: ticking opens a "Book Driving Test" dialog with a date and time
  picker (instead of the generic date + notes dialog). The card shows the
  booked test date, time window, and a confirmation badge once booked.

### Tests

`tests/Feature/PupilDrivingTestBookingTest.php` (5 Pest tests):

- booking creates a linked practical-test calendar item with the right window
- booking ticks the checklist row and stores the calendar item id
- cancelling removes the diary slot and unticks the row
- re-booking replaces the previous slot 1:1
- booking with no assigned instructor throws cleanly

(Tests have been written but not run — per project rule, the user runs them.)

---

## Confidence score

**8 / 10**

What's solid:

- The two-way link (pupil ↔ diary) is enforced through a single transactional
  action, so we won't end up with a half-created booking or a checklist tick
  with no corresponding slot.
- The existing diary code (slot creation, time-block calculation, calendar
  cache invalidation, clash detection) is reused — no parallel paths.
- The existing schedule tab's `is_practical_test=true` flow can also assign a
  student now (the request validator accepts `student_id`), giving the
  instructor a way to attach an unassigned slot to a pupil after the fact.
- The DB migrations are additive and reversible.

What I'd want a second pass on before launch:

- The "instructor deletes the slot directly from the diary" path doesn't
  proactively untick the pupil's checklist row. Deleting via the schedule tab
  is a less common path (the pupil's "Cancel Test" action is the obvious
  one), but a future iteration could add a Model observer on
  `CalendarItem::deleted` to clean up the linked checklist row.
- The mobile API doesn't yet have its own driving-test booking endpoints —
  these are web-only for now. If the mobile app needs the same flow, that's
  a small follow-up under `/api/v1/student/driving-test`.
- The pupil "Schedule Tab" UI uses the existing 1-hour test window;
  instructors who run tests at a different cadence will need to be told
  the diary block is fixed at 1hr prep + 1hr test + 30min buffer (same as
  before — this didn't regress, but it's still a hard-coded constant).
