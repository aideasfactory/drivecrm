# Task: Push Notifications for In-App Messages + Payment Reminders

## Overview

Add push notifications as an **additive** layer to two existing email/in-app flows:

1. **In-app messages** — when `MessageService::sendMessage()` creates a Message and emails the recipient via `NewMessageNotification`, also push-notify the recipient if their `User::expo_push_token` is set.
2. **Payment reminders** — when `SendLessonInvoiceAction::sendReminderNotification()` emails the student/contact via `LessonPaymentReminderNotification`, also push-notify the student's User if their token is set. Single edit point covers all four payment-reminder triggers (booking-time web onboarding, booking-time mobile API, lesson sign-off, manual fallback command) since they all funnel through the same Action.

**Critical contract (per user brief):**
- Push is **additive**, never a replacement. Email + in-app flow keeps working unchanged whether or not a push fires.
- A push only fires when the recipient has a **valid push token** (i.e. `expo_push_token` is non-null).
- A failure in push delivery does not block the email or message creation.

---

## Phase 1: Planning ✅

- [x] Confirmed: call `PushNotificationService` directly from the two send points; no custom Laravel notification channel.
- [x] Confirmed guard: push fires iff `$user !== null && $user->expo_push_token !== null`. No new opt-in flag.
- [x] Confirmed payload shapes (message + payment).
- [x] Decided: add `PushNotificationService::queueIfHasToken()` helper that owns the no-token guard + try/catch + log boilerplate, so call sites are single, intent-clear lines.
- [x] Confirmed scope: all four payment-reminder triggers funnel through `SendLessonInvoiceAction::sendReminderNotification()`, so a single edit there covers everything.
- [x] User confirmed defaults: "yes you just need to add a record to the push_notifications on these trigger points".

**Reflection:** Direct service call beats custom channel here — the project's push system is already a service-with-queue-table-and-cron, not a channel-shaped abstraction. Wrapping it as `via(['push'])` would just hide the simplest path. The call sites are explicit and easy to trace.

---

## Phase 2: Implementation ✅

### Backend wiring

- [x] Added `PushNotificationService::queueIfHasToken(?User $user, string $title, string $body, ?array $data = null): ?PushNotification`:
  - Returns null if `$user` is null OR has no `expo_push_token` (single source of truth for the "push enabled" guard).
  - Wraps `queue()` in try/catch + `Log::warning` so a push-system error never bubbles up to the caller.
- [x] **Payment reminder push** wired inside `SendLessonInvoiceAction`:
  - Injected `PushNotificationService` into the constructor (alongside `StripeService` + `LogActivityAction`).
  - Added a private `sendReminderPush()` method that builds the push title/body/data and delegates to `queueIfHasToken()`. Logs an activity entry only when a push was actually queued.
  - Called from `sendReminderNotification()` AFTER the existing email + activity log, inside the existing try block (so the helper's internal error handling is double-belted by the outer catch).
  - Recipient: `$student->user`. For contact-booked accounts (`owns_account = false`), the student has no app login and thus no token → helper no-ops correctly.
  - Title: `"Time to pay for your lesson"`. Body: `"Check your email to pay for your upcoming lesson on {Day D Mon}."`. Data: `{ type: "lesson_payment", lesson_payment_id, lesson_id, hosted_invoice_url }`.
- [x] **Message push** wired inside `SendMessageAction`:
  - Injected `PushNotificationService` into the constructor (alongside `LogActivityAction`).
  - Called `queueIfHasToken($recipient, …)` AFTER the existing `$recipient->notify(new NewMessageNotification(…))`.
  - Title: `"New message from {sender->name}"` (User model uses `name`, not `first_name` — verified). Body: `Str::limit($messageText, 140)`. Data: `{ type: "message", message_id, from_user_id }`.
- [x] No changes to `NewMessageNotification` or `LessonPaymentReminderNotification`. Email path unchanged.
- [x] No changes to `bootstrap/app.php` schedule — every-minute `push:send-queued` cron already processes anything `queue()` writes.

### Tests

- [x] No test changes (per project rules).

### Docs

- [x] No DB migrations → `database-schema.md` not updated.
- [x] **Updated `.claude/api.md`**:
  - Added an "additive push notification" paragraph to the "Mobile App Flow (weekly payment)" block.
  - Extended the lesson sign-off endpoint's "Side Effects" bullet to mention the queued push for weekly orders with a registered token.
  - Added a new "Events that queue a push notification" table under the `POST /api/v1/push-token` endpoint section, listing both events with their title/body/data shapes.
  - Appended a 2026-04-27 changelog entry summarising the additive push behaviour.

### Files changed

| File | Change |
|------|--------|
| `app/Services/PushNotificationService.php` | New `queueIfHasToken()` helper (no-op + try/catch + log when token absent or push fails). |
| `app/Actions/Payment/SendLessonInvoiceAction.php` | `PushNotificationService` injected; new `sendReminderPush()` method; called from `sendReminderNotification()` after the email send. |
| `app/Actions/Shared/Message/SendMessageAction.php` | `PushNotificationService` injected; push queued after `NewMessageNotification` is sent. |
| `.claude/api.md` | Weekly flow + sign-off side-effects + push-token section updated; changelog entry added. |

---

## Phase 3: Reflection ✅

- [x] **Push fires only with a valid token.** Single guard lives inside `queueIfHasToken`: `if (! $user || ! $user->expo_push_token) return null;`. Both call sites delegate to it, so there is no way to bypass.
- [x] **Email/in-app flow is unchanged.** In both Actions, the push call is placed AFTER the existing notification/email send. The helper itself is try/catch'd internally; in `SendLessonInvoiceAction` it also sits inside the existing outer try/catch — so a push-side exception cannot affect the email or the upstream caller.
- [x] **All four payment-reminder triggers covered by one edit.** Booking-time (web onboarding via `StepSixController`), booking-time (mobile API via `OrderService::bookLessons`), sign-off (via `LessonSignOffService`), and the manual `lessons:send-invoices` fallback command all funnel through `SendLessonInvoiceAction::sendReminderNotification()`. Single call site → consistent behaviour.
- [x] **No new dependencies, models, or migrations.** The existing `push_notifications` table, the every-minute `push:send-queued` cron, and the existing Expo SDK integration handle delivery.
- [x] **Latency note.** Push uses the queue + cron path, not `queueAndSend()`, so delivery latency is up to ~60s (cron runs `everyMinute`). Acceptable for both use cases. If real-time delivery is needed later, swap `queue()` for `queueAndSend()` inside `queueIfHasToken()` — single-line change.

### Technical debt / future considerations

- The 140-char truncation on message bodies is hardcoded inline. If we add more push types, extracting a small `PushPayloadBuilder` (or a static config) would be tidier — premature for two events.
- No support yet for **muting per-conversation** or **quiet hours** — if requirements grow, the natural place is an opt-out field on `User` (e.g. `push_messages_enabled`, `push_payment_reminders_enabled`) and a check inside `queueIfHasToken` or a sibling helper.
- The two notification classes (`NewMessageNotification`, `LessonPaymentReminderNotification`) were intentionally not touched. If at some point we want push to be a Laravel notification channel (e.g. for symmetric handling with email), the migration path would be: build a `PushChannel` that wraps `PushNotificationService::queue()`, add `'push'` to `via()`, and remove the direct calls from the Actions. Documented here so the next person knows the option exists.

### What went well

- One helper, two callers, zero changes to mailables.
- The "no token = no-op" guard is owned by the Service, so the call sites are both single-line and unambiguous about intent (`queueIfHasToken`).
- Single edit in `SendLessonInvoiceAction` automatically covers all four payment-reminder triggers — a direct payoff of the previous task's "single source of truth" refactor.

### Anti-pattern check

- ✅ Service extends `BaseService` (already did).
- ✅ Action remains pure — no HTTP, no caching.
- ✅ No new mailable, no new notification.
- ✅ Email + in-app flow strictly unchanged.
- ✅ No `env()` outside config.
- ⚠️ `SendLessonInvoiceAction` now has 3 dependencies (Stripe + LogActivity + Push). Still reasonable; not yet at "split this Action" territory.

### Score

**9 / 10.** Loses a point for: (a) the inline 140-char truncation that should arguably live in a tiny helper if more push events arrive, and (b) no automated test coverage of the new wiring (out of my hands per project rules). Otherwise: minimal, additive, single-source-of-truth implementation that fully respects the "push must never block email" contract.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-27
