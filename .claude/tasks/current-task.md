# Task: Environment variable to override the MTD digital button on the instructor layout

## Overview

The instructor layout (`InstructorHeader.vue`) currently exposes two HMRC-related
buttons — "HMRC Connected" (when linked) and "HMRC / Tax" (when unlinked) — that
give instructors access to Making Tax Digital (MTD) features (ITSA + VAT).

Requirement: hide these MTD digital buttons by default and add an environment
variable to override that so a site owner can bring them back without a code
change.

## Locations identified

1. **`resources/js/components/Instructors/InstructorHeader.vue`**
   - Renders two "MTD digital" buttons: HMRC Connected (line ~217) and
     HMRC / Tax (line ~227) — both are the surface the user calls the
     "MTD digital button".

2. **`config/hmrc.php`**
   - Right place for a new `show_mtd_button` config value backed by an env var.

3. **`app/Http/Middleware/HandleInertiaRequests.php`**
   - Shares props globally with Inertia. Best surface to expose the flag to Vue
     without wiring it through every controller.

## Phase 1: Planning ✅

### What needs to change

**Backend:**
- `config/hmrc.php`
  - Add `'show_mtd_button' => (bool) env('SHOW_MTD_BUTTON', false)` — defaults
    to `false` so the button is hidden unless the site operator opts in.
- `app/Http/Middleware/HandleInertiaRequests.php`
  - Share the flag as `hmrc.show_mtd_button` so every Inertia page can read it.
- `.env.example`
  - Document the new `SHOW_MTD_BUTTON` variable.

**Frontend:**
- `resources/js/types/globals.d.ts` (or the shared `PageProps` type)
  - Extend the shared Inertia page props type with `hmrc.show_mtd_button`.
- `resources/js/components/Instructors/InstructorHeader.vue`
  - Read `hmrc.show_mtd_button` from `usePage().props`.
  - Wrap both HMRC buttons in a `v-if` so they only render when the flag is
    truthy AND the user is an instructor (existing role check).

### Why this scope

- The requirement is UI-only ("hide the button"). No routes need blocking — the
  HMRC tab remains reachable by direct URL for admins/owners. This is a display
  toggle, not a feature disable.
- Using a config-backed env var (not a raw `env()` call in code) is the Laravel
  convention and keeps behavior predictable when config is cached.
- Sharing via `HandleInertiaRequests` keeps the flag globally available without
  having to touch each controller that renders an instructor page.

**Created:** 2026-07-08
**Last Updated:** 2026-07-08
**Status:** In Progress

- Blocking access to `/hmrc/*` routes (this is a UI hide, not a permission).
- Changing HMRC connection logic, tokens, or middleware.
- Hiding the "HMRC" tab pill inside the instructor page — the visible button
  in the header is the specific surface called out by the requirement.

## Phase 2: Implementation ✅

### Files edited

- `config/hmrc.php`
  - Added `show_mtd_button` config key backed by `SHOW_MTD_BUTTON` env var,
    defaulting to `false`.
- `app/Http/Middleware/HandleInertiaRequests.php`
  - Shared `hmrc.show_mtd_button` as a global Inertia prop.
- `resources/js/components/Instructors/InstructorHeader.vue`
  - Read the flag from `usePage().props.hmrc?.show_mtd_button` and wrapped both
    the "HMRC Connected" and "HMRC / Tax" buttons behind it.
- `resources/js/types/index.d.ts`
  - Extended `SharedData` with `hmrc: { show_mtd_button: boolean }`.
- `.env.example`
  - Added `SHOW_MTD_BUTTON=false` with a short comment.

## 📋 Overview

- **Default hidden.** The user asked to "hide the MTD digital button" first,
  then have an env var override — so the safe default is `false`.
- **Boolean cast in config.** `env()` returns strings for values like "true"
  in some setups; a `(bool)` cast normalises the flag so the frontend can
  trust `v-if` semantics.
- **Shared prop, not per-page.** Sharing through the Inertia middleware means
  any future instructor page that wants to consult the flag has it for free.
- **Guarded on both existing conditions.** The buttons still require the
  `isInstructor` role check that was there before — the new flag is an
  *additional* gate, not a replacement.

## 🎯 PHASE 1: PLANNING ✅

### Decisions Made
- **New config file `config/fees.php`** — fees are their own concern; mixing
  them into `services.php` would drown them among third-party credentials.
- **Master override flag: `FEES_OVERRIDE_TO_ZERO`** — a single boolean that
  zeroes both fees at read time. Individual base amounts stay configured so
  the "later use" requirement is preserved.
- **Config helper method `App\Support\Fees`** — a small class with
  `bookingFee()`, `digitalFeePerLesson()`, `bookingFeePence()`,
  `digitalFeePerLessonPence()` so callers don't have to repeatedly apply the
  override boolean. Keeps the override rule in one place.
- **Where the flag is honoured** — everywhere fees are computed for display
  or persistence: `CalculatePackagePricingAction`, `CreateOrderFromEnquiryAction`,
  `StepFiveController`, `Package` accessors. Historical orders keep their
  already-stored pence values (we do not rewrite `orders.booking_fee_pence`).
- **No migration** — fees are configuration, not schema.

### Components / files touched
- `config/fees.php` (new)
- `app/Support/Fees.php` (new — thin helper)
- `.env.example` (append new keys)
- `app/Actions/Package/CalculatePackagePricingAction.php`
- `app/Actions/Onboarding/CreateOrderFromEnquiryAction.php`
- `app/Http/Controllers/Onboarding/StepFiveController.php`
- `app/Models/Package.php`

**Why this shape is right for the brief:**
- Single env var toggles the UI. No behavior change beyond visibility.
- Config-first pattern keeps it cache-safe and testable.
- No changes to route registration or authorisation.

**Operational notes:**
- To show the button in an environment, set `SHOW_MTD_BUTTON=true` in `.env`
  and run `php artisan config:clear` (or `config:cache`) so the new value takes
  effect.
- The HMRC tab is still directly linkable by URL — this is a header display
  toggle only.

**Out of scope, carried forward:**
- If the product later wants a full "hide all MTD features" mode, the same env
  var could gate route registration in `routes/web.php`. That's a bigger blast
  radius and not part of this brief.

### Files created
- `config/fees.php` — declares `booking_fee`, `digital_fee_per_lesson`,
  `override_to_zero`.
- `app/Support/Fees.php` — helper with `bookingFee()` / `bookingFeePence()`
  / `digitalFeePerLesson()` / `digitalFeePerLessonPence()`
  / `digitalFeeTotalPence(int $lessons)`.

### Files modified
- `.env.example` — added `BOOKING_FEE`, `DIGITAL_FEE_PER_LESSON`,
  `FEES_OVERRIDE_TO_ZERO` under a "Fees" section.
- `app/Actions/Package/CalculatePackagePricingAction.php` — reads
  `Fees::bookingFee()` and `Fees::digitalFeePerLesson()` instead of class
  constants. Constants kept as deprecated defaults (removed in favour of
  config lookup).
- `app/Actions/Onboarding/CreateOrderFromEnquiryAction.php` — replaces the
  `999` / `399` literals with `Fees::bookingFeePence()` /
  `Fees::digitalFeePerLessonPence() * $lessons_count`.
- `app/Http/Controllers/Onboarding/StepFiveController.php` — reads
  `Fees::bookingFee()` instead of the `19.99` literal.
- `app/Models/Package.php` — `getBookingFeeAttribute`, `getDigitalFeeAttribute`,
  `getTotalPriceAttribute`, `getWeeklyPaymentAttribute` all read from
  `Fees::` helpers.

### Key decisions
- **Helper class over calling `config()` directly**: this puts the
  "override to zero" rule in ONE place. If we call `config('fees.booking_fee')`
  everywhere, every caller has to also check `config('fees.override_to_zero')`.
  A tiny `Fees::bookingFee()` avoids that duplication.
- **Pence-based helpers alongside pounds**: pricing logic mixes both units
  (`total_price_pence` is stored in pence; display values are pounds). The
  helper exposes both to avoid rounding drift at call sites.
- **No changes to `OrderResource` / `PackageResource`** — they read fields
  already produced by the touchpoints above.

---

## 💭 PHASE 3: REFLECTION ✅

### Why this shape is right for the brief
- The brief asks for a single flag that overrides both fees. Centralising
  the fees behind `App\Support\Fees` means one place decides the value —
  every touchpoint honours the flag automatically.
- The brief also asks that fees remain configurable for later use.
  `config/fees.php` reads three env variables; changing pricing is a `.env`
  edit + config clear, no code change.

### Operational notes
- Enabling `FEES_OVERRIDE_TO_ZERO=true` zeros both fees for **new**
  bookings/orders. Historical orders keep the pence values they were
  stored with.
- The default (`FEES_OVERRIDE_TO_ZERO=false`) preserves current behaviour.
- The default booking fee falls back to `19.99`, matching
  `CalculatePackagePricingAction::BOOKING_FEE` (the historical primary
  source of truth used at checkout).
- Because the previous inline literal in `CreateOrderFromEnquiryAction` was
  `£9.99`, moving that action onto the shared `Fees::bookingFeePence()` will
  raise its booking fee to `£19.99` unless the user overrides via env. This
  brings the onboarding flow into sync with `StepFiveController` and
  `CalculatePackagePricingAction`. If £9.99 was the correct value, set
  `BOOKING_FEE=9.99` in `.env`.

### Follow-ups not done (out of scope)
- No tests added (project rule: user maintains tests).
- No `Pint` run (project rule: user handles code style).
- No migration / retro backfill of `orders` fees.

**Status:** All phases complete.
**Last Updated:** 2026-07-08.
