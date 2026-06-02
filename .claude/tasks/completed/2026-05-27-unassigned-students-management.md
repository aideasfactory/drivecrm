# Task: Unassigned Students Management

**Created:** 2026-05-27
**Last Updated:** 2026-05-27
**Status:** Complete

---

## 📋 Overview

### Goal
When an instructor removes a student (or is removed themselves), the student stays in the system with `instructor_id = null`. Admins now have a way to see those orphaned students on the `/pupils` index, view a summary of their details, and re-assign them to a Stripe-onboarded instructor. Also: renamed "Pupils" → "Students" in nav + page title (route stays `/pupils`).

### Success Criteria
- [x] Sidebar nav label reads "Students"; index page title reads "Students" (URL stays `/pupils`)
- [x] Index has an All / Unassigned filter toggle with counts
- [x] Toggle filters list to `instructor_id IS NULL`
- [x] Clicking an unassigned student opens a summary sheet
- [x] Sheet shows student summary (status, phone, lessons, revenue)
- [x] Dropdown lists only Stripe-onboarded instructors (reuses `GetOnboardedInstructorsAction`)
- [x] Submit assigns instructor and fires email notification to the instructor

### Context
- Schema already supports this: `students.instructor_id` is `nullable` with `nullOnDelete()` — no migration needed.
- DB table is already `students`; the `/pupils` URL was the only "pupil" terminology left in the customer-facing UI.
- Page is gated admin-only at the route layer (`RestrictInstructor` middleware on the parent group).
- Existing transfer flow at `app/Actions/Student/Transfer/` was the reference pattern.
- Previous-instructor history panel: **deferred** — no log table for it today.

---

## 🎯 PHASE 1: PLANNING ✅

### Decisions Made
- **No virtual "Unassigned" instructor user.** Nullable FK is already in place — a sentinel user would pollute the users table (login, billing, settings it'd never use) and force every query to special-case it.
- **Reuse `GetOnboardedInstructorsAction`** for the dropdown — same filtering rule as the transfer flow.
- **New `StudentAssignedByAdminNotification`** rather than reusing `StudentGainedNotification`. The transfer notification hardcodes "transferred from {source}" and a lesson-clash block, neither of which applies here.
- **New `AssignStudentToInstructorAction`** rather than extending `AttachStudentToInstructorAction`. The latter is PIN-flow specific (activity log hardcodes "via PIN", no notification). Two ~30-line single-purpose Actions are clearer than parameterising a shared one.
- **Action refuses re-assignment of an already-assigned student.** Re-assignment is the Transfer flow's job — it also migrates future lessons. Admin assignment is for orphans only.
- **Mail-only notification.** Matches the project convention — every existing notification in `app/Notifications/` is mail-only.
- **Client-side filter, not server-side.** Search is already client-side; the unassigned toggle just hides rows. Matches existing style; will revisit if the list grows large.
- **Sheet pattern, not a dedicated student profile page.** The codebase has no standalone student profile route (the assigned-student "profile" lives inside the instructor's detail page). Building a whole new page would scope-creep. Sheet matches the project's "Forms use Sheet" convention.

---

## 🔨 PHASE 2: IMPLEMENTATION ✅

### Files created
- `app/Actions/Student/AssignStudentToInstructorAction.php` — Sets `instructor_id`, writes audit-trail activity logs against student and instructor, fires the email notification. Refuses to run if student already has an instructor (caller should use the Transfer flow).
- `app/Notifications/StudentAssignedByAdminNotification.php` — `ShouldQueue` mail notification, single channel (mail). Subject + body name the student and link to `/pupils`.

### Files modified
- `routes/web.php` — added `POST /pupils/{student}/assign-instructor` (named `pupils.assign-instructor`) under the same `auth + verified + RestrictInstructor` group as the existing pupils route.
- `app/Http/Controllers/PupilController.php`
  - `index()` now also passes `onboardedInstructors` to the Inertia view, pulled via `GetOnboardedInstructorsAction`.
  - New `assignInstructor()` method — validates `instructor_id`, calls the Action, returns JSON. Catches the Action's `RuntimeException` and returns 422 with the message.
- `resources/js/pages/Pupils/Index.vue` — full rewrite preserving original behaviour and adding:
  - Title / breadcrumb / heading renamed to "Students".
  - All / Unassigned filter toggle with counts.
  - Click handler routes to existing instructor page when assigned, opens Sheet when unassigned.
  - Sheet fetches summary via `axios.get('/students/{id}')` (existing endpoint) and submits via `axios.post('/pupils/{id}/assign-instructor')`. On success, fires toast and reloads the `pupils` prop only.
- `resources/js/components/AppSidebar.vue` — renamed "Pupils" label to "Students" (`pupilsIndex` route helper unchanged).

### Wayfinder
- `php artisan wayfinder:generate` ran clean — actions and routes regenerated. (Frontend uses raw axios paths for the new endpoint, matching the existing axios-call style in the file. Wayfinder regen done for completeness.)

### Skipped per project rules
- `vendor/bin/pint` (user handles code style).
- Tests (user handles tests).
- `npm run build` (user handles compiling).

### Verification done
- `php -l` clean on all four changed PHP files.
- `php artisan route:clear && php artisan route:list --name=pupils` shows new route registered.

### Out of scope (carried forward from Phase 1)
- Previous-instructor history in the summary panel (no log table for it).
- Filter visibility differentiated by role (page is already admin-gated, so any user reaching it is owner).

---

## 💭 PHASE 3: FINAL REFLECTION ✅

### What worked well
- **Schema was already right** — nullable FK + `nullOnDelete()` meant zero migration work. Spotting that up front prevented suggesting an "Unassigned instructor" user, which would have been over-engineering.
- **Reused `GetOnboardedInstructorsAction` verbatim** — single source of truth for the "Stripe-onboarded only" rule. If that rule changes (e.g., requires `details_submitted` too), both the Transfer flow and the admin-assign flow update together.
- **Sheet over dedicated page** — kept the change small. The user's "small summary of their history" phrasing fit a Sheet better than a full profile page, and the codebase doesn't have a standalone student profile route to extend anyway.

### Subtle decisions worth flagging
- **`AssignStudentToInstructorAction` refuses re-assignment.** If `instructor_id` is already set, it throws. This is a guard, not a feature: re-assignment lives in the Transfer flow because that flow also migrates future un-paid-out lessons. Silently overwriting `instructor_id` here would leave lessons attached to the old instructor — almost certainly a bug.
- **Notification subject reuses the "New student assigned" phrasing** from the transfer flow's `StudentGainedNotification`. Different body (no "transferred from" or lesson-clash block) but consistent subject so it groups well in inboxes.
- **No `database` channel.** Project convention is mail-only across all 19 existing notifications. Adding `database` here just because we could would diverge.
- **Filter is client-side.** Consistent with existing search filtering on the same page. If lists grow into thousands, both should move server-side together.

### Operational notes for the user
- **Run `npm run build` (or `npm run dev`)** to pick up the Vue page changes.
- **`MAIL_MAILER` must be set to something real** (e.g., `mandrill`) for the notification to actually deliver — `log` will only write the message to `storage/logs/laravel.log`.
- **Test path:** load `/pupils`, click the "Unassigned" toggle, click any unassigned student, pick a Stripe-onboarded instructor, hit Assign. Expected: toast, student moves out of the unassigned list, instructor receives an email.

### Out of scope (NOT done)
- Previous-instructor history in the summary panel.
- Bulk assignment (assigning multiple unassigned students at once).
- Webhook / event for assign (e.g., for analytics) — only the email notification fires.
- API documentation update — endpoint is a web admin endpoint, not part of the mobile `/api/v1/*` surface that `api.md` covers.

---

**Status:** All phases complete.
**Last Updated:** 2026-05-27.
