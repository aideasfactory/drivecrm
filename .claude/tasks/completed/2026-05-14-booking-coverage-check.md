# Task: Booking — Single-Instructor Coverage Check Landing Page

## Overview

Build a parallel public flow at `/booking` that mirrors `/onboarding`'s step 1 (name/email/phone/postcode/consent), then on step 2 checks whether a single configured instructor (ID from env var `BOOKING_INSTRUCTOR_ID`, currently 20) covers the entered postcode sector. Show one of two outcomes:

- **In area:** "We have lessons in your area. Great, someone will be in touch soon."
- **Out of area:** "Sorry, we don't have any lessons in your area."

Per user direction: duplicate the onboarding flow rather than edit it, keeping the two flows fully independent. Reuses the existing `Enquiry` model for lead capture so the existing leads-review tooling still sees these records.

## Phase 1: Planning ✅

### Scope
- New routes under `/booking` — public, no auth.
- Two steps only: details form → result page (no instructor list, no calendar, no payment, no further steps).
- Instructor ID sourced from `config('booking.instructor_id')`, which reads `env('BOOKING_INSTRUCTOR_ID')`.
- Re-uses the existing `Enquiry` model (`data` JSON column stores step 1 inputs and the coverage result).
- Existing onboarding flow at `/onboarding` is untouched.

### Coverage check logic
Strip spaces, uppercase, drop last 3 chars to get postcode sector (e.g. `"TS7 1AB"` → `"TS7"`). Query: instructor (ID from config) is active AND has a `locations` row with `postcode_sector = $sector`. Single `exists()` query — no calendar load.

## Phase 2: Implementation ✅

### Files created
**Config / env**
- `config/booking.php` — single key, `instructor_id` reading `env('BOOKING_INSTRUCTOR_ID')`.
- `.env.example` — added `BOOKING_INSTRUCTOR_ID=` placeholder.

**Backend**
- `app/Http/Middleware/ValidateBookingEnquiryUuid.php` — mirror of `ValidateEnquiryUuid` but redirects to `booking.start` on failure.
- `app/Http/Middleware/ValidateBookingStepAccess.php` — mirror of `ValidateStepAccess` but redirects to `booking.stepN`.
- `app/Http/Controllers/Booking/BookingController.php` — `start()` creates a fresh `Enquiry` with `data.source = 'booking'` and redirects to step 1.
- `app/Http/Controllers/Booking/StepOneController.php` — same form-handling as onboarding StepOne (name/email/phone/postcode normalisation + privacy consent), redirects to step 2. No `booking_for_other`, no instructor prefill.
- `app/Http/Controllers/Booking/StepTwoController.php` — does the coverage check. Computes `inArea`, persists it onto the enquiry's step 2 data, renders `Booking/Step2` with `inArea` boolean.
- `app/Http/Requests/Booking/StepOneRequest.php` — same validation as onboarding StepOneRequest minus `booking_for_other`.

**Frontend (Inertia/Vue)**
- `resources/js/pages/Booking/Step1.vue` — clone of Onboarding/Step1.vue minus prefill banner and "booking for other" field; submit button reads "Check my area"; inline minimal header (Onboarding's 6-step header was hard-coded for onboarding routes).
- `resources/js/pages/Booking/Step2.vue` — result page; reads `inArea` prop and shows one of two messages.

### Files edited
- `routes/web.php` — added imports (with class-name aliases since `StepOneController`/`StepTwoController` collide between namespaces) and a new `/booking` route group after the existing onboarding group. Existing onboarding routes untouched.

### Key decisions
- **Inline header instead of `OnboardingHeader`.** Onboarding's header hard-codes the 6 step labels and the `@/routes/onboarding` Wayfinder route imports. A two-step booking flow would look broken inside it. The inline header in the two Booking Vue pages is ~20 lines of markup — cheaper than a new shared component for two consumers.
- **`Enquiry` reuse** rather than a separate `BookingEnquiry` model. `Enquiry.data` is already a freeform JSON store; adding `data.source = 'booking'` is enough to distinguish records in the leads tooling.
- **Step 2 is a GET-only result page.** No "submit" — the postcode-sector check runs on page load, the outcome is persisted to the enquiry, and the message renders. Matches the user's spec ("show a page that says").
- **Parallel middleware (`ValidateBookingEnquiryUuid`, `ValidateBookingStepAccess`)** rather than editing the originals. Mirrors the user's "duplicate, don't edit" preference and is a 30-line mirror.
- **`exists()` query** instead of a full Eloquent fetch — the coverage answer is boolean, and we don't need the instructor's locations, calendars, or meta for the result page.

### Verification done
- `php artisan route:clear && php artisan route:list --name=booking` → shows the 4 new routes (start, step1 GET, step1 POST, step2 GET).
- `php -l` on all new backend files → no syntax errors.

### Out of scope (not built)
- Admin/instructor notification when a new in-area enquiry arrives — existing leads tooling already sees the `Enquiry` row.
- Calendar / availability display.
- Sharing state between `/onboarding` and `/booking` flows.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- The user explicitly preferred duplication over editing — the implementation honours that. Zero behavioural changes to `/onboarding`. Two new middleware files, three new controllers, one new FormRequest, two new Vue pages, one new config file, and a route group appended at the bottom of `routes/web.php`.
- Booking flow is a public landing page for a single instructor's marketing efforts. Heavy use of `Enquiry` model means leads still flow into the same place admins already look.

**Subtle decisions worth flagging:**
- `StepOneController` and `StepTwoController` exist in both `App\Http\Controllers\Onboarding` and `App\Http\Controllers\Booking`. In `routes/web.php` the Booking ones are imported with `as BookingStepOneController` / `as BookingStepTwoController` to avoid the collision.
- `Booking/StepOneController` normalises the postcode the same way onboarding does (`"sw1a1aa"` → `"SW1A 1AA"`). Important — the sector extraction in `StepTwoController` strips spaces again before extracting, so it's robust to either form, but consistency with onboarding is the safer default.
- The `data.source = 'booking'` discriminator on the `Enquiry` row is a soft marker — no migration, no enum. If the team later wants to filter "where did this lead come from?", this is the field. If you want it harder-typed, that's a future migration.
- The Result page (Step 2) persists the coverage outcome to `enquiry.data.steps.step2` before rendering. Means an admin reviewing the lead can see at a glance whether the system told the user "yes" or "no", which matters if a sales rep follows up and finds we're out of area.
- The `ValidateBookingStepAccess` middleware permits `current_step + 1`, so step 2 is reachable only after step 1 has been completed (matches onboarding's behaviour). Direct GETs to `/booking/{uuid}/step/2` from a fresh enquiry will redirect back to step 1.

**Operational notes for the user:**
- **`.env` must be set:** `BOOKING_INSTRUCTOR_ID=20` needs adding to the actual `.env` (not just `.env.example`) for the coverage check to find the instructor. Without it the page will always render "Sorry, we don't have lessons in your area."
- **Frontend rebuild required:** `npm run dev` (or `composer run dev`) must be running for Vite to pick up the new `Booking/Step1.vue` and `Booking/Step2.vue`. Wayfinder will also generate `@/routes/booking` route functions during the build — but the implementation doesn't import them (form submits via the raw URL string), so a rebuild is purely for the new component files.
- Config cache was cleared (`php artisan config:clear`) so the new `config/booking.php` is live. If the user runs `php artisan config:cache` later, that will also pick it up.

**Out of scope (carried forward from Phase 1, NOT done):**
- Admin notification when an in-area enquiry arrives.
- Lead-export / analytics surfacing of `data.source = 'booking'` enquiries.
- "Out of area" follow-up form (collect more detail / waitlist).
- Multiple instructor IDs per landing page.

**Technical debt / follow-up not done:**
- No tests added (project rule: user maintains tests manually).
- No `npm run dev` triggered (project rule: user runs build commands).
- No Pint formatting run (project rule: user handles code style).

---

**Status:** All phases complete.
**Last Updated:** 2026-05-14.
