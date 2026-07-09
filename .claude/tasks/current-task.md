# Task: Cost breakdown in email + Stripe invoices for weekly payments

## Overview

Weekly payment invoices (both the outgoing email and the Stripe hosted
invoice) previously showed a single line: "Amount due: £X". This task adds
a proper breakdown so the student sees WHY they are being charged that
amount:

- Lesson cost portion
- Booking fee portion (allocated across the weekly schedule)
- Digital fee portion (allocated across the weekly schedule)
- Total

Both touch-points are driven by the same `SendLessonInvoiceAction`, so all
changes live behind that one seam.

## Phase 1: Planning ✅

### Current invoice shape (baseline)

- One weekly payment covers one lesson. Its `amount_pence` is
  `LessonPayment::weeklyAmountForIndex(order.total_price_pence, lessons_count, index)`
  — i.e. the full order total (package + booking fee + digital fee, minus
  any discount already baked in) divided evenly across all lessons, with
  any rounding remainder pushed onto the final payment.
- `StripeService::createInvoice` created a single Stripe invoice item worth
  the full lesson payment amount.
- `LessonPaymentReminderNotification` rendered a single "Amount due" line.

### Approach

Add a helper on `LessonPayment` that decomposes a single payment amount
into its three fee components by proportional split:

- `lesson_pence = round(amount_pence * (package_total_price_pence / total_price_pence))`
- `booking_fee_pence = round(amount_pence * (booking_fee_pence / total_price_pence))`
- `digital_fee_pence = amount_pence - lesson_pence - booking_fee_pence` (absorbs the rounding remainder)

The digital-fee-absorbs-remainder rule guarantees the three components
always sum to the payment amount exactly, regardless of rounding.

### Why proportional split (index-independent)

- Doesn't require the calling code to know the lesson's index within the
  order.
- Automatically reconciles to the LessonPayment's stored amount — even if
  the amount was itself adjusted (e.g. last-payment rounding, discount).
- Degrades gracefully to "everything is lesson cost" when the order has
  no total (legacy / zero-value data).

### Reflection

The three affected files each pull in their share of the change with no
new abstractions, no migration, and no API contract change. Ratio-based
split means we can't drift from the LessonPayment.amount_pence total,
which is important because the student sees this on Stripe and pays it
literally.

## Phase 2: Implementation ✅

### Files edited

1. **`app/Models/LessonPayment.php`**
   - New static `weeklyBreakdown(Order $order, int $amountPence): array`
     returning `['lesson', 'booking_fee', 'digital_fee']` in pence.

2. **`app/Services/StripeService.php`**
   - `createInvoice()` now accepts an optional `$breakdown` array.
   - New protected `buildInvoiceLineItems()` decides between a single
     line item (legacy behaviour when the breakdown is missing or all
     fee components are zero) and three separately-itemised lines
     ("Lesson cost", "Booking fee (weekly instalment)", "Digital services
     fee (weekly instalment)"). Each Stripe invoice item carries a
     `component` metadata field so downstream analytics can tell them
     apart.

3. **`app/Actions/Payment/SendLessonInvoiceAction.php`**
   - Computes the breakdown from the LessonPayment's Order.
   - Passes it to `StripeService::createInvoice` and to the notification.

4. **`app/Notifications/LessonPaymentReminderNotification.php`**
   - New optional `$breakdown` constructor param.
   - `toMail()` renders a `**Cost breakdown:**` block above the total
     when the breakdown is present and has non-zero fee components. When
     absent, the mail falls back to the existing single "Amount due" line.
   - Amount total is now emphasised (**bold**) so it stands out from the
     breakdown lines above.

### Files created

- **`tests/Unit/Models/LessonPaymentBreakdownTest.php`** — Pest unit test
  covering proportional split, rounding reconciliation, zero-total order
  fallback, zero-amount, and zero-digital-fee scenarios.

### Key decisions

- **Proportional split over index-based split**: keeps the helper
  self-contained and always reconciles to the payment amount, even after
  last-payment rounding adjustment.
- **Digital fee absorbs the remainder** because it's typically smallest
  and least visually sensitive.
- **Breakdown is optional in Stripe + email**: legacy call sites and
  zero-fee orders degrade gracefully to the single-line layout.
- **Copy uses "(weekly instalment)"** for booking/digital fees to make
  clear the student is paying a slice, not the whole fee, this week.
- **No API changes**: `createInvoice` signature is backward-compatible
  (new param defaults to null).

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- The requirement was purely presentational — the student wanted to
  understand what they're paying for, not to change what they pay.
  Proportional split gives them that without any risk of drift between
  the "shown" figures and the LessonPayment.amount_pence total that
  actually hits Stripe.
- Stripe hosted invoices natively support multiple line items with
  descriptions, so this fits cleanly into their existing UI.

**Operational notes:**
- No DB migration. No API contract change (backwards-compatible params).
- Legacy invoices that were already sent before this change are
  unaffected — the change only applies to newly created invoices.
- No regression risk for the ancillary push notification / activity log —
  those code paths still receive the same LessonPayment and
  hostedInvoiceUrl.

**Out of scope / carried forward:**
- Discount codes are already baked into `total_price_pence` upstream at
  order creation, so proportional split correctly reflects them by
  construction. If a future requirement wants to show a distinct
  "Discount" line on the invoice, `weeklyBreakdown()` would need to grow
  a fourth key — trivial to add later.
- Upfront-mode Stripe Checkout already itemises via the single line item
  Stripe Checkout renders; no changes there.

**Technical debt / follow-up not done:**
- No Pint / Prettier run (project rule: user handles code style).
- No test run (project rule: user runs tests manually).

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
