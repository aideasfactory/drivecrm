# Only active pupils in the add-booking pupil list

**Status:** ✅ Complete — 2026-10-31
**Ticket:** Mission Control (Jack, due 7 Nov) — "Only active pupils should show in the list when an instructor is adding a booking."

## Overview
Pupil pickers used to start a booking must list only pupils with `students.status = 'active'` (`Student::isActive()`). The general pupil lists stay as they are.

## Phase 1: Investigation ✅
- ✓ "Active" = `students.status === 'active'`. Other values: `inactive`, `on_hold`, `passed`, `failed`, `completed`. Staff-deleted pupils are soft-deleted and set to `inactive`
- ✓ Mobile: no dedicated picker endpoint. The app books via `POST /api/v1/students/{student}/orders`; the only instructor pupil list is `GET /api/v1/instructor/students` (grouped, also the pupils screen). The drivecrm-app repo isn't accessible, so it's unknown which groups the app's picker reads
- ✓ Web: the admin diary Add Booking sheet (`AddBookingFromSlotSheet.vue`) gets its students from `ScheduleTab.vue`, via `GET /instructors/{id}/pupils`. That endpoint already defaults to `status=active`, but the default is shared with the Active Pupils tab
- ✓ No picker: the pupil-page Book Lessons section (fixed pupil) and the staff "Book lessons for a student" onboarding link (details typed in)
- ✓ Short-notice slot offers already push only to active students (`CreateSlotOfferAction`)

## Phase 2: Implementation ✅
- ✓ `InstructorStudentController@index`: optional `active_only` boolean. When it's set, `passed` / `inactive` are empty and `recent_activity` is filtered to active. The default response is unchanged
- ✓ `ScheduleTab.vue`: `loadStudents()` sends `status=active` explicitly
- ✓ `.claude/api.md`: query param, picker note on orders store, changelog

## Reflection
The mobile change only takes effect once the app calls `?active_only=1` (or reads only `data.active`). `POST /students/{student}/orders` still accepts any of the instructor's pupils whatever their status. Server-side enforcement there would touch order creation, so it was left out.

💳 Payment impact: NONE — no order, checkout, Stripe, hold or pricing code changed. `POST /students/{student}/orders` behaves exactly as before.
