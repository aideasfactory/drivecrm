# Task: Remove "Both" from booking transmission dropdown

## Overview

The public-facing booking form at `/booking` currently lets a prospect pick
"Manual", "Automatic" or "Both" as a transmission preference. The "Both" option
should be removed from the UI and rejected by the form-request validation so a
prospect cannot submit `transmission=both` through the booking flow.

Scope is the public booking form **only**. The internal instructor-management
UI (admin/AddInstructorSheet) still uses `both` to describe instructors who can
teach either gearbox — that is a different concept and stays untouched.
Historical Enquiry rows that already hold `transmission=both` keep their value
and their existing display labels (`Either / no preference` in admin email,
`Either` in the Enquiries index).

## Phase 1: Planning ✅

### Files that need changing
- `resources/js/pages/Booking/Step1.vue` — remove the `<option value="both">`
  from the transmission `<select>` so it can never appear in the UI.
- `app/Http/Requests/Booking/StepOneRequest.php` — drop `both` from the
  `in:manual,automatic,both` validation rule so a hand-crafted POST also fails
  server-side.

### Files deliberately NOT changed (and why)
- `config/booking.php` — still maps `both => BOOKING_INSTRUCTOR_BOTH_ID`. Kept
  because instructors are still tagged `both` internally and the admin team may
  still create historical/manual enquiries via other tooling. No code path
  reaches it from the public form once both is removed from the validator.
- `app/Http/Controllers/Booking/StepTwoController.php` — `$step1Data['transmission'] ?? 'both'`
  fallback stays. It only kicks in for malformed step-1 data and is harmless;
  changing it is out of scope (defensive code for an internal flow).
- `app/Mail/BookingEnquirySubmittedMail.php` — `transmissionLabel()` keeps the
  `'both' => 'Either / no preference'` branch so historical enquiries display
  correctly in admin emails.
- `resources/js/pages/Enquiries/Index.vue` — same reason as above: keeps
  rendering historical `both` rows correctly.
- `resources/js/components/Instructors/AddInstructorSheet.vue` — internal
  instructor management, out of scope for the booking-form ticket.
- TypeScript instructor types — describe instructor capability, not booking
  preference; unchanged.

### Risks / things to watch
- Existing Enquiries in the DB with `transmission=both` must still render — the
  display-side label mappings stay intact, so they will.
- `StepTwoController::resolveInstructorId('both')` is unreachable from the
  public form post-change but still works if called directly. No regression.

## Phase 2: Implementation ✅

- [x] Removed `<option value="both">Both</option>` from
      `resources/js/pages/Booking/Step1.vue` (line 228 in the original file).
- [x] Updated `StepOneRequest::rules()` so `transmission` is
      `['required', 'in:manual,automatic']`. The existing custom error message
      `transmission.in => 'Please choose a transmission preference'` stays
      correct because it doesn't enumerate the allowed values.

### Verification of intent
- Existing default in Step1.vue (`transmission: existingData.transmission || 'manual'`)
  is unchanged — a user landing on the form with no prior data now sees Manual
  pre-selected, which still maps to a valid option.
- The dropdown is `required`, so an explicit empty selection is already
  prevented by the browser. With `both` removed, the only submittable values
  are `manual` and `automatic`.
- If a prospect had picked `both` in a previous session and the form is
  rehydrating from `existingData`, the `<select>`'s v-model will hold a value
  with no matching `<option>`, so the field renders blank and the prospect
  must choose again — desired behaviour, since `both` is no longer offered.

## Phase 3: Reflection ✅

### What went well
- Tight, two-file change that matches the brief exactly. No collateral
  refactor of related concepts (instructor capability tagging,
  historical-display labels) that share the `both` literal but aren't part of
  the public booking surface.

### Subtle decisions worth flagging
- **Left `config/booking.php`'s `'both' => instructor_id` mapping in place.**
  The public form can no longer submit `both`, so that branch is unreachable
  from the booking flow. Removing it would have been a tidy-up beyond the
  ticket's scope and would risk breaking any internal tooling that still
  treats `both` as a valid instructor bucket.
- **Left the `transmissionLabel()` mappings in `BookingEnquirySubmittedMail`
  and `Enquiries/Index.vue` intact.** Historical enquiries with
  `transmission=both` keep rendering as "Either / no preference" / "Either".
- **Did not touch the instructor-management form
  (`AddInstructorSheet.vue`).** That dropdown describes which gearboxes an
  instructor can teach — a capability flag, not a booking preference. The
  ticket scope is explicitly the `/booking` form.
- **Did not delete the `'both' => 'Either / no preference'` row from the
  mail label map.** Without it, historical enquiries would render an empty
  cell in the admin email.

### Out of scope / follow-up
- No tests added (project rule: user maintains tests manually).
- No Pint / lint run (project rule: user handles style).
- If the team later decides `both` should never appear anywhere in the
  product, a follow-up ticket should: drop the `both` config key, prune the
  display-side label branches, and consider a data migration to remap any
  remaining `transmission=both` enquiries to a chosen default.

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
