# Task: Human-friendly notification system in the student app

## Overview

The activity log / notification system currently surfaces raw internal
messages ("Payment reminder push notification queued for user #23") with a
single icon per category. When an instructor drills into a student, the
"Log" sub-tab shows this stream. Meanwhile the app's top-right corner had
no notification indicator at all.

This task:
1. Adds a real notification bell to the top-right corner (both the
   sidebar-style admin header and the instructor's page header), showing
   the authenticated instructor's recent notification-category activity in
   a dropdown.
2. Rewrites how activity/notification items are presented so each
   notification is human-friendly, concise, and iconised by *type* rather
   than by broad category. "Payment reminder push notification queued for
   user #23" now shows as "Payment reminder pushed — to alex@example.com
   for lesson on 2026-07-15".
3. Categorises the wider activity log with fine-grained icons — using each
   log entry's `metadata.type` when present, with category fallback.

## Phase 1: Planning ✅

### What was decided

- **Single shared helper** — a new `resources/js/lib/notifications.ts`
  owns the mapping from raw activity log rows to `{icon, tone, title,
  summary, friendlyDate}`. Both the header bell and the student Log tab
  render through this helper, so future notification types are one
  switch-case update, not two.
- **No backend changes** — the raw activity log messages stay untouched
  (they're searchable and instructors sometimes want the raw form). The
  friendly title/summary is derived from `metadata.type` and structured
  `metadata.*` fields at render time.
- **No new endpoints** — the bell reuses
  `GET /instructors/{id}/activity-logs?category=notification` (with the
  current user's `auth.instructor_id` from Inertia shared props). The Log
  tab keeps `GET /students/{id}/activity-logs`.
- **No read/unread state** — there is no `read_at` column on activity_logs
  today. The bell shows a "recent" badge (count of items from the last
  7 days) instead. Adding real read-state would need a migration and is
  a separate ticket.

## Phase 2: Implementation ✅

### Files created

- `resources/js/lib/notifications.ts` — `toFriendlyNotification()`,
  `toneContainerClasses()`, `toneBadgeVariant()`, `iconForCategory()`,
  `labelForCategory()`, `relativeTime()`. Maps by `metadata.type` first
  (23 known types e.g. `lesson_signed_off`, `lesson_payment_reminder`,
  `order_confirmation`, `welcome_student`, `payment_link`,
  `instructor_welcome_failed`), then by category (`lesson`, `booking`,
  `payment`, `profile`, `message`, `note`, `notification`, `package`,
  `student`, `student_gained`, `student_lost`, `instructor_assigned`,
  `instructor_transfer`), then a neutral `Activity` fallback. Structured
  summary reads `recipient_email`, `lesson_date`, `order_id`, `reason`,
  `moved_count`, `cancelled_count` — never parses the raw message.
- `resources/js/components/NotificationBell.vue` — Bell icon with a
  count badge (last 7 days, capped at 9+), opens a `DropdownMenu`
  containing up to 10 friendly notifications, each with a tone-coloured
  icon container, title, summary, and relative time. Self-loading via
  axios; polls every 60s when the dropdown is closed. Empty state
  reads "You're all caught up." Silently no-ops if the user has no
  `instructor_id`.

### Files modified

- `resources/js/components/AppSidebarHeader.vue` — Mounts
  `NotificationBell` in the top-right of the admin sidebar header (the
  actual top-right corner for owners).
- `resources/js/components/AppHeader.vue` — Mounts `NotificationBell` in
  the top-right control cluster of the alternate marketing-style header.
- `resources/js/components/Instructors/InstructorHeader.vue` — Mounts
  `NotificationBell` at the head of the instructor's own action button
  row (the actual top-right corner instructors see, since the app hides
  the sidebar for the instructor role).
- `resources/js/components/Instructors/Tabs/Student/LogSubTab.vue` —
  Now uses the shared helper. Each row renders (a) a tone-coloured icon
  container (`bg-emerald-100 text-emerald-700` for success, amber for
  warning, rose for danger, sky for info, neutral otherwise), (b) a
  friendly per-type title, (c) a friendly per-type summary, and (d) a
  short relative time. Category filter buttons now share the same icon
  set and label formatter as the rest of the surface.

### Key decisions

- **Reused `DropdownMenu` for the bell** — the codebase's shadcn UI
  doesn't ship `Popover`, and `DropdownMenu` already implements the
  same open/close/anchoring model. No new UI dependency.
- **Tone-coloured *icon* only, not the row background** — keeps the log
  scannable without turning the timeline into a rainbow. Danger stays
  vivid (destructive Badge variant) so failed emails stand out.
- **Fallback title-per-category** — even if a future
  notification-category row has a `metadata.type` we haven't mapped, the
  helper degrades gracefully to "Notification" + trimmed raw message.
- **No parsing the raw `message` string** — the helper reads
  `metadata.*` structured fields only. The raw message strings are
  informal and change across features; parsing them would be brittle.

### Out of scope (deliberately)

- Read/unread persistence and a "mark all as read" button (requires a
  DB migration; separate ticket).
- A dedicated `/notifications` full-page view (the bell's dropdown was
  the ticket's remit — a full page would grow scope).
- Rewriting the instructor's own ActivitySubTab.vue to use the helper.
  The ticket is scoped to the student app; keeping the instructor tab
  as-is avoids collateral change. The helper is exported so the
  instructor tab can adopt it later with a one-line switch.
- Any backend message rewriting or new activity categories.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- The requirement had four asks: more human-friendly copy, selective
  info, tone-styled icons, and per-type categorisation in the activity
  log. All four are satisfied by one shared helper feeding two surfaces
  (bell + log). One place to edit copy = one place to keep them in sync.
- Not touching the backend keeps the change reversible and safe. The
  existing rows already carry the `metadata.type` this needs — the work
  is entirely presentation.

**Technical debt / follow-up:**
- Read-state is the obvious next iteration. A `read_at` (nullable
  timestamp) on `activity_logs` and a `POST /notifications/{id}/read`
  endpoint would unlock a real unread count. The helper is already
  designed to receive a pre-flagged item — only the badge computation
  changes.
- The header layout for the student role isn't yet handled here (the
  repo doesn't expose a student-facing Vue portal). When it does, the
  same `NotificationBell` component drops in — swap `auth.instructor_id`
  for `auth.student_id` and switch the endpoint to
  `/v1/student/activity-logs`.
- No tests added (project rule: user maintains tests manually).
- No Pint / Prettier run (project rule: user handles code style).

**Operational notes:**
- No DB migration. No API contract change. The bell polls at 60 s so it
  adds one extra request per authenticated instructor per minute.

---

**Status:** All phases complete.
**Last Updated:** 2026-07-08.
