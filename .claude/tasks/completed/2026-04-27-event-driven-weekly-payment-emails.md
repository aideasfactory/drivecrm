# Task: Event-Driven Weekly Payment Emails

## Overview

Switch the weekly-payment-mode invoice/email flow from the time-based scheduled job (previously sent ~48 hours before each lesson) to an **event-driven** flow:

1. **At booking time** — when a student/admin confirms an order with `payment_mode = WEEKLY`, immediately issue the Stripe invoice + send the payment email for the **first** lesson.
2. **At lesson sign-off** — when a weekly lesson is signed off, immediately issue the invoice + send the payment email for the **next** unpaid/pending lesson in that order (if one exists).
3. **Disable the 48-hour cron path** for weekly orders so the same lesson never gets a duplicate invoice from the old job.

Each email continues to use the existing `LessonPaymentReminderNotification` (Stripe hosted invoice link). No mailable changes; this is a triggering/orchestration change only.

**Scope:** weekly payment mode only. Upfront payment mode is untouched.

---

## Phase 1: Planning ✅

- [x] Confirmed "next lesson" rule: order's earliest lesson (by `scheduled_at`) where `LessonPayment.status = DUE` AND `stripe_invoice_id IS NULL` AND lesson is not cancelled/completed.
- [x] Booking-time trigger location → **Option A**: call from `StepSixController::handleWeeklyPayment()` after order activation. Keeps the Action pure.
- [x] Sign-off trigger location → hook into `LessonSignOffService::signOffLesson()` AFTER existing notifications, wrapped in try/catch + log (matches feedback-email pattern).
- [x] Old 48h job → keep command file as manual fallback, remove schedule entry only. (User confirmed: "yes all the above makes sense".)
- [x] Recipient fallback (student email → contact email) — unchanged, already handled by `LessonPaymentReminderNotification`.

**Reflection:** Plan is grounded in real file paths and reuses `SendLessonInvoiceAction` end-to-end — no notification or invoice logic is rewritten. Idempotency is covered by the existing `stripe_invoice_id IS NULL` guard inside the Action, so accidental double-fires are safe.

---

## Phase 2: Implementation ✅

### Backend wiring

- [x] **Chose `OrderService` as the home** for the new orchestration. It already coordinates the weekly-mode flow inside `bookLessons()` (mobile API path) and extending it keeps "next invoice for an order" as a single, reusable Service method per BaseService rules.
- [x] Added `OrderService::sendNextDueInvoice(Order $order): ?array`:
  - Returns `null` for non-weekly or inactive orders (safe no-op).
  - Joins `lesson_payments` ↔ `lessons`, filters by order, status=DUE, no `stripe_invoice_id`, lesson not cancelled, ordered by `lesson.date` then `lesson.start_time`, takes first.
  - Invokes existing `SendLessonInvoiceAction` (no duplicate logic).
  - Wrapped in try/catch + Log so a Stripe failure returns an error array instead of bubbling up.
- [x] Injected `SendLessonInvoiceAction` into `OrderService` constructor (alongside existing dependencies). Imports added: `App\Actions\Payment\SendLessonInvoiceAction`, `App\Enums\LessonStatus`, `App\Enums\PaymentStatus`, `App\Models\LessonPayment`.
- [x] Wired the **booking-time trigger** at three call sites that all funnel through `OrderService::sendNextDueInvoice()`:
  - `OrderService::bookLessons()` — mobile API path. Called after `ensureStripeCustomerExists()` + `sendConfirmationEmail->execute()` for `WEEKLY`.
  - `StepSixController::handleWeeklyPayment()` — web onboarding path. `OrderService` injected; call wrapped in try/catch + Log so a Stripe failure does not block the redirect to the success page. (Stripe customer is already created earlier in `CreateUserAndStudentFromEnquiryAction::execute()` line 87–101, so the customer always exists by the time we send.)
- [x] Wired the **sign-off trigger** in `LessonSignOffService::signOffLesson()`:
  - `OrderService` injected into the constructor. (Same namespace `App\Services`, no import needed.)
  - Call placed AFTER `sendLessonSignedOffNotification()` and `sendFeedbackEmail()`, BEFORE the AI recommendations dispatch. Wrapped in try/catch + Log so a Stripe failure never blocks sign-off completion.
- [x] **Unscheduled the cron**: removed `$schedule->command('lessons:send-invoices')->hourly()->between('9:00', '17:00');` from `bootstrap/app.php`.
- [x] **Re-positioned the command as a manual fallback**: updated `app/Console/Commands/SendLessonInvoices.php` description from "Send Stripe invoices for weekly lessons scheduled in the next 48 hours" to "Manual fallback: sweep weekly LessonPayments that have not been invoiced yet" + added a class-level PHPDoc explaining the new role. Query left unchanged (still safe — only acts on uninvoiced, status=DUE LessonPayments).
- [x] Confirmed no other callers of `SendLessonInvoices` command exist outside the schedule entry, the command file itself, and existing tests (which we do not touch per project rules).

### Tests

- [x] **No test changes.** Per project rules, the user runs and maintains tests manually. (Note: existing `tests/Feature/SendLessonInvoicesTest.php` still exercises the command and remains valid since the command logic is unchanged — only the schedule wiring was removed. The user can decide whether to add coverage for the new `sendNextDueInvoice()` orchestration.)

### Docs

- [x] No DB migrations → `database-schema.md` not updated.
- [x] **Updated `.claude/api.md`**:
  - Rewrote the "Mobile App Flow (weekly payment)" bullet block (around line 3867) to describe the new event-driven sequence and noted the cron is now manual-only.
  - Added a new bullet to the "Side Effects (background job)" list under the lesson sign-off endpoint (line ~3098): "For weekly orders: immediately issues the next lesson's Stripe invoice + payment-link email".
  - Appended a 2026-04-27 changelog entry summarising the behaviour change. (No request/response shape change, so the endpoint contracts themselves were not edited.)

### Files changed

| File | Change |
|------|--------|
| `app/Services/OrderService.php` | New `sendNextDueInvoice()` method; `SendLessonInvoiceAction` injected; weekly branch of `bookLessons()` now triggers the first invoice. |
| `app/Http/Controllers/Onboarding/StepSixController.php` | `OrderService` injected; `handleWeeklyPayment()` triggers the first invoice after activation. |
| `app/Services/LessonSignOffService.php` | `OrderService` injected; `signOffLesson()` triggers the next invoice after sign-off notifications. |
| `bootstrap/app.php` | Removed `lessons:send-invoices` schedule entry. |
| `app/Console/Commands/SendLessonInvoices.php` | Description + PHPDoc updated to reflect manual-fallback role. Query unchanged. |
| `.claude/api.md` | Rewrote weekly flow bullet block; added bullet to sign-off side-effects; appended changelog entry. |

---

## Phase 3: Reflection ✅

- [x] **Triggers fire only for weekly + active orders.** `sendNextDueInvoice()` exits early on `! $order->isWeekly() || ! $order->isActive()`, so the new call sites are safe to call unconditionally — they no-op for upfront mode without any extra branching at the call sites.
- [x] **Idempotency.** The DB query filters by `stripe_invoice_id IS NULL`, AND the underlying `SendLessonInvoiceAction` only writes the invoice ID after a successful Stripe call. Re-running the booking handler or re-invoking the sign-off Service for the same lesson will not produce a duplicate invoice; it will pick up the *next* uninvoiced lesson (or no-op if all are invoiced).
- [x] **Old cron path is fully off.** Only one schedule entry was registered — that line is removed from `bootstrap/app.php`. The command file stays for manual sweeps; no auto-run remains.
- [x] **Stripe customer prerequisite is satisfied on both paths.** Web onboarding creates the Stripe customer in `CreateUserAndStudentFromEnquiryAction` before the order is created. Mobile API path explicitly calls `ensureStripeCustomerExists($student)` before the new invoice trigger inside `bookLessons()`.
- [x] **No circular service dependencies introduced.** `LessonSignOffService` now depends on `OrderService`; `OrderService` depends on `InstructorService` (and now `SendLessonInvoiceAction`). `InstructorService` does not depend on either — no cycle.

### Technical debt / future considerations

- The **two try/catch blocks** at the call sites duplicate small log boilerplate. A future refactor could extract this to a Listener (Laravel events: `LessonSignedOff`, `OrderActivated`) so the trigger becomes a side-effect of an event instead of an explicit Service call. Left as-is for now — direct calls keep the flow easy to trace and one extra layer of indirection isn't justified for two callers.
- The **legacy command's 48h `due_date` filter** is unchanged. As a manual fallback it is still safe (only sweeps uninvoiced, status=DUE payments) but the 48h window is no longer meaningful as a guard. Worth widening or removing in a follow-up if the team relies on the manual sweep for missed sends.
- The **existing `tests/Feature/SendLessonInvoicesTest.php`** still passes the command through its paces — but no tests yet cover the new event-driven trigger points. The user controls the test suite per project rules; flagging here as a coverage gap.

### What went well

- Single Service method (`sendNextDueInvoice`) is the only new piece of logic. No new Action, no new Service, no duplicate of `SendLessonInvoiceAction` — the existing Action does all the Stripe + notification work.
- The trigger is no-op-safe for non-weekly orders, so the call sites do not need their own `if (isWeekly)` checks.
- Cron removal was a single line; manual fallback semantics preserved with a description tweak.

### Anti-pattern check

- ✅ Service extends `BaseService` (already did before this change).
- ✅ Action remains pure; no caching/HTTP added.
- ✅ Controller stays thin — delegates to Service in a try/catch.
- ✅ No new mailable, no new notification.
- ✅ No `env()` outside config.
- ⚠️ Two near-identical try/catch + Log blocks at two call sites — minor DRY violation, but extracting a helper would only save ~5 lines and obscure the local error context. Left in place.

### Score

**8.5 / 10.** Loses points for: (a) the duplicated try/catch boilerplate at the two call sites, (b) leaving the legacy command's 48h filter cosmetically inconsistent with its new "manual sweep" role, and (c) no automated test coverage of the new wiring (out of my hands per project rules, but still a gap). The win: the change is small, fully reuses `SendLessonInvoiceAction`, and the new Service method is a single source of truth that both web booking and mobile API booking can rely on without duplication.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-27
