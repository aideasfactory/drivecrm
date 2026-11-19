# Task: Day-before lesson reminder push (learner)

**Created:** 2026-11-19
**Last Updated:** 2026-11-19
**Status:** Complete
**Ticket:** 01a1f3fd-a1c6-726c-b1ee-24ed4d4e172e (CRM side; app follows using the contract in `api.md`)

---

## 📋 Overview

### Goal
Send each learner a push notification the evening before each upcoming lesson, reusing the existing Expo push plumbing (`users.expo_push_token`, `push_notifications`, `push:send-queued`).

### Success Criteria
- [x] Learner with a push token gets one push the evening before each open lesson (18:00–21:00 UK)
- [x] Cancelled, draft, provisional and banked lessons, and lessons on pending/cancelled/expired orders are skipped
- [x] Learners without a push token are skipped
- [x] Never sent twice for the same lesson date (recorded in `lesson_reminders`), even with overlapping runs
- [x] A failure for one lesson does not stop the others and is retried on the next run in the window
- [x] Message has date, time, instructor and pickup (when known); data payload deep-links to the lesson
- [x] Existing emails, pushes and API responses unchanged

---

## 🎯 PHASE 1: PLANNING ✅

### Findings
- Push: token on `users.expo_push_token` (`POST/DELETE /api/v1/push-token`); `PushNotificationService::queue()` writes `push_notifications` rows sent by `push:send-queued` (every minute).
- Scheduler: `bootstrap/app.php` `withSchedule`. `reminders:send` (every 5 min) runs miles + 48h payment reminders via `ReminderService`, using `lesson_reminders` (unique `lesson_id` + `type`) for at-most-once.
- Lessons: `date` + `start_time` are UK wall-clock (`BookingPayments::timezone()`), app timezone is UTC. Status `draft|pending|completed|cancelled`; banked = `banked_at` (no date); provisional = `provisional_at`.
- No existing email lesson reminder (only payment emails). No notification preferences beyond having/removing the push token.

### Decisions Made
- Send window 18:00–21:00 UK the evening before; command runs every 5 minutes so missed runs and failures retry, and late bookings for tomorrow are still reminded the same evening. Nothing after 21:00 (no late-night pushes).
- Separate command + `LessonReminderService` (not `reminders:send` / `ReminderService`), because `ReminderService` builds a Stripe client for payment reminders; this keeps the two jobs independent.
- New `ReminderType::LESSON_DAY_BEFORE`, reusing `lesson_reminders` — no migration. A row only counts when its `sent_at` is on the current UK day, so a lesson moved to a new date is reminded again.
- Reminder row (locked) + queued push in one transaction.
- Recipient is the student's own `user` regardless of who booked (contact details live on the student row).

---

## 🔨 PHASE 2: IMPLEMENTATION ✅

- [x] `app/Enums/ReminderType.php` — `LESSON_DAY_BEFORE`
- [x] `app/Actions/Reminder/GetLessonDayBeforeRemindersDueAction.php` — eligible lessons for tomorrow, window check
- [x] `app/Actions/Reminder/SendLessonDayBeforeReminderAction.php` — message, payload, transactional claim + queue, activity log
- [x] `app/Services/LessonReminderService.php`, `app/Console/Commands/SendLessonDayBeforeReminders.php` (`reminders:lesson-day-before`)
- [x] `bootstrap/app.php` — schedule every 5 minutes, `withoutOverlapping()`
- [x] `app/Models/Order.php` — `pickupLocationLine()` made public (visibility only)
- [x] `app/Support/PushNotificationCatalog.php` — new entry
- [x] `.claude/api.md` (push events table + changelog), `.claude/database-schema.md` (`lesson_reminders` type values)

### Reflection
Reusing `lesson_reminders` avoided a migration. The smoke run showed resolving `ReminderService` builds the Stripe client, which led to the separate service.

💳 Payment impact: NONE — no change to amounts, Stripe calls, webhooks, holds, invoices, payouts or order/lesson status. `Order::pickupLocationLine()` changed from protected to public only (display helper, no behaviour change). `reminders:send` / `ReminderService` untouched.
