# Task: Pay at booking with short slot holds

**Created:** 2026-09-28
**Last Updated:** 2026-09-29
**Status:** Complete

---

## 📋 Overview

### Goal
Nothing is confirmed (and no new learner gets an app login) until the first
payment lands. Both pay in full and pay weekly take a first payment at booking
through Stripe Checkout, and the slots are only held for a limited time.

Signed-off scenarios:

| Who books | How the learner pays | Hold |
|---|---|---|
| Learner, website booking form | Checkout straight away | 10 minutes, starting when they press Pay |
| Learner, mobile app | Checkout in the in-app browser | 10 minutes |
| Instructor (mobile app / admin diary) | Payment link emailed | Until 48h before the first lesson (min 15 minutes) |
| Bookings team (staff booking form) | Payment link emailed | Until midnight UK time (min 15 minutes) |

- Pay in full → the full amount. Pay weekly → the first week (+ guarantee if chosen).
- Unpaid at the deadline → lessons deleted, slots freed, order cancelled, link dead.
- Resending a link never extends the hold.
- Later weekly payments stay as emailed invoices, now due 48h before each lesson.

### Success Criteria
- [x] Weekly bookings start as draft (not reserved/active) on every booking path
- [x] First weekly payment taken through Stripe Checkout; confirms the order on payment
- [x] Confirmation / welcome email only after payment
- [x] Holds computed per booking source, stored on the order
- [x] Every-minute job releases expired holds (and closes the Stripe session first)
- [x] Midnight draft cleanup leaves live holds alone
- [x] Emailed links go through our own signed URL, so long holds outlive Stripe's 24h session cap
- [x] Weekly payments due 48h before the lesson

---

## 🎯 PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Trace onboarding step 6, mobile/admin `OrderService::bookLessons`, payment links, webhook, verify flows
- [x] Decide first weekly payment mechanism
- [x] Decide where the hold lives and how it is released
- [x] Identify copy/doc/test changes

### Decisions Made
- First weekly payment uses Stripe Checkout (`StripeService::createCheckoutSession` branches on weekly) rather than the hosted invoice page, because Checkout redirects back to our success pages. The charge id is stored on the first `lesson_payments` row so payouts keep working.
- `orders.payment_hold_expires_at` stores the deadline. Rules live in `App\Support\BookingPayments` + `config/booking_payments.php` (same pattern as `Fees`).
- Emailed links point at a signed `/orders/{order}/payment-link/pay` route that reuses or creates a Checkout session on click (Stripe caps sessions at 24h).
- `orders:release-expired-holds` runs every minute. It expires the open Checkout session first; if Stripe says it already completed, the release is skipped so the payment webhook can confirm it.
- Weekly confirmation: paid week → BOOKED, remaining weeks → RESERVED (same as the old paid-invoice behaviour).
- Onboarding resubmission: a still-pending order for the enquiry is released first; a released order sends the learner back to step 4.

---

## 🎯 PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Tasks
- [x] Migration + model + schema docs
- [x] Config + `BookingPayments` support class
- [x] Order creation (enquiry + API) → drafts for weekly, 48h due dates
- [x] Stripe checkout for weekly first payment, `expires_at`, session expiry helper
- [x] Confirm weekly first payment action + webhook / step 6 success / verify wiring
- [x] Release action + command + schedule; midnight cleanup guard
- [x] Payment links via signed route; resend for weekly; email copy
- [x] Step 6, Complete + PaymentLink pages copy (new `PaymentLink/Unavailable`)
- [x] API docs
- [~] Tests — skipped at the user's request (user tests manually)

---

## 🎯 PHASE 3: REFLECTION

**Status:** ✅ Complete

### What went well
- One hold column plus one every-minute job covers every booking source.
- Emailing our own signed URL gets around Stripe's 24h session cap and makes the link stop working the moment the hold ends.

### Watch-outs
- **App change needed:** student-initiated weekly bookings (orders store and slot-offer accept) now return `checkout_url`, which the app must open.
- Existing tests that expect weekly instructor bookings to be `RESERVED` now get `DRAFT`: `InstructorDiarySlotTest` (~line 138) and `DiarySlotOfferTest` (~line 98).
- Late payment on a released order is logged `critical` for a manual refund. There is no auto-refund.
- The first weekly payment has no Stripe invoice (a Checkout payment), so `has_stripe_invoice` is false for it.
- Stored `learner.payment_link` templates are patched by `2026_09_29_100000_update_payment_link_template_for_pay_at_booking` where the default wording is intact. Staff-edited copy keeps its own text until edited. New placeholders are `{{amount_label}}` and `{{pay_by_line}}`.
- The 48h "payment due soon" reminder now fires around the due time. Consider moving it earlier.
- Nothing was run (no PHP on the VM). Run the migration before deploying the scheduler change.

---

## 🎯 FOLLOW-UP: Client feedback on step 4 holds

**Status:** ✅ Complete

Feedback: picking a time at step 4 and going back made that time unavailable
(even to the same learner), and 15 minutes was too long a hold.

- [x] Step 4 no longer writes to the diary. It checks the slot is still free and remembers the choice on the enquiry only.
- [x] The hold starts at step 6 when the learner presses Pay. `CreateOrderFromEnquiryAction` uses the same `CreateDraftCalendarItemsAction` as the mobile app, which locks the chosen slot, so if two learners pay for the same time only the first gets it. The other is sent back to step 4 with "no longer available".
- [x] Learner hold cut from 15 to 10 minutes (`BOOKING_LEARNER_HOLD_MINUTES`). The 15-minute minimum now only applies to emailed-link holds.
- [x] Old step 4 holds (`calendar_item_ids` on the enquiry) are released when the learner revisits step 4 or pays (`ReleaseLegacyStepFourHoldsAction`), so learners mid-flow at deploy aren't blocked by their own hold.
- [x] Step 4 copy updated and it now shows the "please choose another time" error.
