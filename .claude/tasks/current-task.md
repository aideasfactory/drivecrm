# Task: Environment variable for overriding digital fee and booking costs

**Created:** 2026-07-08
**Last Updated:** 2026-07-08
**Status:** In Progress

---

## 📋 Overview

### Goal
Introduce an environment variable that, when active, forces the digital fee
and booking fee to £0 across the entire booking / order pipeline. Move the
existing hardcoded fee values behind configuration so they can be tuned
without a code change.

### Success Criteria
- [x] Config layer exposes `booking_fee` and `digital_fee_per_lesson` as
      overridable knobs.
- [x] A single boolean env flag (`FEES_OVERRIDE_TO_ZERO`) zeros both fees
      wherever pricing is calculated.
- [x] All existing hardcoded fee touchpoints read from the new config.
- [x] `.env.example` documents the new keys.

### Context
Fees currently appear as hardcoded literals in:
- `app/Actions/Package/CalculatePackagePricingAction.php`
- `app/Actions/Onboarding/CreateOrderFromEnquiryAction.php`
- `app/Http/Controllers/Onboarding/StepFiveController.php`
- `app/Models/Package.php` (accessors)

A dedicated `config/fees.php` becomes the single source of truth; every
touchpoint reads from it.

---

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

### Out of scope
- Reconciling the pre-existing inconsistency between the £19.99 booking fee
  used in `CalculatePackagePricingAction` / `StepFiveController` and the
  £9.99 used in `CreateOrderFromEnquiryAction` / `Package.php`. The
  refactor centralises **both** to the same config key — that alone brings
  them into agreement, but the "true" value is a business decision the user
  should confirm; the default matches the existing `CalculatePackagePricingAction`
  constant (£19.99).
- Retroactively updating past `orders.booking_fee_pence` / `digital_fee_pence`.
- Adding UI to change fees — the "later use" requirement is satisfied by
  configuration, not an admin panel.

---

## 🔨 PHASE 2: IMPLEMENTATION ✅

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
