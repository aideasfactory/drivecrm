# Task: Pass Your Test Guarantee add-on (booking form)
# Task: Staff bookings via the onboarding form

**Created:** 2026-09-24
**Last Updated:** 2026-09-24
**Status:** Complete

---

## 📋 Overview

### Goal
Offer "Pass Your Test Guarantee" on the public booking form (onboarding flow).
Bookings of 10+ hours paid in full get it free. Anyone else (weekly payers,
smaller packages) can tick it on the summary step and pay £50. Once paid, the
student is flagged so admins see it at a glance.

Terms: https://just-drive.co.uk/learner-terms-and-conditions/

### Success Criteria
- [x] Summary step (step 5) shows the add-on with a checkbox and pricing lines
- [x] 10+ booked hours + pay in full → included free, no charge
- [x] Weekly or < 10 hours → only included if ticked, charged £50
- [x] Upfront: £50 is a separate Stripe Checkout line item
- [x] Weekly: £50 is added to the first weekly payment and itemised on its invoice
- [x] Student flagged only after the covering payment is confirmed
- [x] Admin badge on the student header, instructor pupils tab, and pupils index

---

## 🎯 PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Trace onboarding steps 5 (summary) and 6 (payment), order creation, Stripe checkout, weekly invoices, webhooks
- [x] Decide how "hours" are measured
- [x] Decide how the weekly add-on is charged
- [x] Decide where the admin flag lives and where it's shown

### Decisions Made
- "Hours" = package lessons × slot length chosen at step 4 (slots are usually 2 hours, so 5 lessons = 10 hours). Falls back to 1 hour per lesson if times are missing.
- Rules live in `App\Support\TestPassGuarantee` (same pattern as `App\Support\Fees`), config in `config/test_pass_guarantee.php` (£50, 10 hours, terms URL).
- Opt-in is stored in the enquiry (`steps.step5.test_pass_guarantee`); resolution happens at order creation, because payment mode is only chosen at step 6.
- Upfront + 10+ hours → free even if ticked (no double-charging).
- Weekly add-on is charged in full on the first weekly payment (invoiced immediately at booking), tracked on `lesson_payments.test_pass_guarantee_pence`.
- Flag = `students.test_pass_guarantee_at` + `test_pass_guarantee_order_id`, granted by `GrantTestPassGuaranteeAction` (idempotent).
- Mobile API (`CreateOrderFromApiAction`) untouched: the add-on is booking-form only.

### Reflection
**What went well:**
- Existing fee/total pipeline made it easy to fold the add-on into `total_price_pence`.

**What could be improved:**
- Step 5/6 still show some legacy pricing quirks (UUID discount not reflected in the shown total); left as-is.

**Risks identified:**
- Refunds/cancellations of the first weekly lesson would include the £50 in `amount_pence`.

---

## 🎯 PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Tasks
- [x] Migrations: `orders.includes_test_pass_guarantee`, `orders.test_pass_guarantee_pence`, `lesson_payments.test_pass_guarantee_pence`, `students.test_pass_guarantee_at`, `students.test_pass_guarantee_order_id`
- [x] Updated `.claude/database-schema.md`
- [x] Config + `App\Support\TestPassGuarantee`
- [x] Model fillable/casts, `Student::hasTestPassGuarantee()`, `testPassGuaranteeOrder()`, factory state
- [x] `StepFiveRequest` validation + `StepFiveController` props
- [x] `StepSixController` pricing props + grant on checkout success
- [x] `CreateOrderFromEnquiryAction` totals + first weekly payment
- [x] `StripeService` checkout line item + invoice line item; `LessonPayment::weeklyBreakdown` guarantee split
- [x] Reminder + order confirmation emails list the guarantee
- [x] `WebhookController` grants on upfront activation and on the weekly `invoice.paid`
- [x] `GrantTestPassGuaranteeAction` (activity log entry)
- [x] Admin data + `TestPassGuaranteeBadge` in StudentTab, ActivePupilsTab, Pupils/Index
- [x] Step 5 + Step 6 Vue UI (ShadCN Card/Checkbox/Badge/Alert)
- [x] Pest tests: rules, booking flow, grant action, admin views, breakdown

### Reflection
**What went well:**
- Guarantee breakdown key is only added when non-zero, so existing breakdown shape and tests are unchanged.

**What could be improved:**
- Onboarding controllers still call Actions directly (existing pattern) rather than through a Service.

---

## 🎯 PHASE 3: REFLECTION

**Status:** ✅ Complete

- Technical debt: guarantee resolution happens in a Support class rather than a Service; consistent with `Fees`.
- Future: admin toggle to grant/revoke manually; show the guarantee in the mobile API if instructors need it.
- No API endpoints changed, so `.claude/api.md` needed no update.
Admin/bookings team can open the public onboarding form in "staff booking"
mode and book lessons for a student without being sent to Stripe.

## Phase 1: Planning ✅
- ✓ Traced Step 6: upfront redirects to Stripe Checkout; weekly activates the
  order, emails the first Stripe invoice and a confirmation to the student.
- ✓ Found existing payment-link email pipeline (OrderService /
  SendPaymentLinkEmailAction / PaymentLinkCheckoutController).

## Phase 2: Implementation ✅
- ✓ `/onboarding?staff_booking=1` flags the enquiry (`data.staff_booking`),
  only for signed-in owner users.
- ✓ Step 6 upfront + staff booking → `OrderService::sendPaymentLink()` emails
  the Stripe Checkout link; staff browser goes to the completion page.
- ✓ Weekly staff bookings use the existing flow unchanged.
- ✓ Staff-specific copy on Step 6, Complete page and payment-link email.
- ✓ "Book lessons for a student" button on Students page; "Purchase lessons"
  link on a student's Lessons tab passes the staff flag.
- ✓ Feature tests in `tests/Feature/Onboarding/StaffBookingTest.php`.

## Phase 3: Reflection ✅
- Unpaid upfront orders keep draft calendar items only until the nightly
  `calendar:cleanup-drafts` run (same as instructor payment links).
- No migrations or API endpoints changed.
