# Task: Show lesson cost in signed-off lesson summary

## Overview

When an instructor signs off a student's lesson, the lesson is marked complete
and a summary becomes viewable. Currently the summary view shows the lesson
date, time, and the instructor's written summary — but not the lesson cost.
This task adds the lesson cost (formatted as GBP) to every place a signed-off
lesson summary is shown.

## Locations identified

1. **Primary — "View Summary" Dialog** (`LessonsSubTab.vue`)
   - Button: `View Summary` appears for `status === 'completed' && summary` lessons.
   - Dialog opens via `openViewSummary(lesson)` and shows date, time and summary text.
   - `lesson.amount_pence` is already in the data model — only the UI needs updating.

2. **Secondary — Schedule "Completed lesson view"** (`ScheduleTab.vue`)
   - When an instructor opens a completed calendar item, a side sheet shows
     "Lesson Summary" inline. This is the same signed-off summary in a different
     surface. The user asked for cost to be visible "wherever View summary is
     shown" — same intent applies here.
   - The calendar item payload from `GetInstructorCalendarAction` does NOT
     currently include `amount_pence`, so the backend action + TS interface
     also need a one-field addition.

## Phase 1: Planning ✅

### What needs to change

**Frontend:**
- `resources/js/components/Instructors/Tabs/Student/LessonsSubTab.vue`
  - Render `formatCurrency(viewSummaryTarget.amount_pence)` in the View Summary
    Dialog as a labelled "Cost" row above the summary body.
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue`
  - In the "Completed lesson view" panel, render the lesson cost using the
    `amount_pence` field that we'll add to `CalendarItemResponse`.
- `resources/js/types/instructor.ts`
  - Add `amount_pence?: number | null` to `CalendarItemResponse`.

**Backend:**
- `app/Actions/Instructor/GetInstructorCalendarAction.php`
  - In the mapped item array (within the `BOOKED || COMPLETED` lesson branch),
    expose `amount_pence` from `$lesson->amount_pence`.

### Why this scope

- `LessonsSubTab.vue` is the page the requirement explicitly references
  ("click View summary"). Cost is already in the payload — UI-only change.
- `ScheduleTab.vue` shows the same signed-off summary on a different surface,
  so the requirement carries over. Backend exposes a single field; no new
  endpoints, no migrations.

### Out of scope

- Multi-currency formatting (formatter is GBP-only — matches the existing
  `formatCurrency()` helper in `LessonsSubTab.vue`).
- VAT or tax breakdown (the lesson cost stored on the lesson is the customer
  price; payout breakdowns live elsewhere).
- New permissions / RBAC checks (the user reaching this dialog already passed
  the instructor scoping middleware).

## Phase 2: Implementation ✅

### Files edited

- `resources/js/components/Instructors/Tabs/Student/LessonsSubTab.vue`
  - Added a "Cost" line inside the `View Summary` Dialog, rendered with the
    existing `formatCurrency(viewSummaryTarget.amount_pence)` helper.
- `resources/js/components/Instructors/Tabs/ScheduleTab.vue`
  - Added a "Cost" line in the "Completed lesson view" panel, rendered with the
    existing `formatCurrency()` helper, guarded on `amount_pence != null`.
- `resources/js/types/instructor.ts`
  - Added `amount_pence: number | null` to `CalendarItemResponse`.
- `app/Actions/Instructor/GetInstructorCalendarAction.php`
  - Captured `$lesson->amount_pence` and included it in the mapped item payload
    so the Schedule view has the value to render.

### Key decisions

- **Reused `formatCurrency`** in both files — these are existing local helpers
  already used elsewhere in the same component for the lessons table and
  sign-off sheet. Consistency over adding a new shared util.
- **Labelled "Cost"** rather than "Price" or "Amount" — matches user wording.
- **Cost row placed at the top of the dialog body**, before the summary text,
  so it reads naturally with the date/time already in the DialogDescription.
- **Backend change is one line in one mapper** — no new resource class, no new
  endpoint. The existing payload shape is the right place to surface this
  because `summary` already lives on the same response.

### Files created
- `results.md` — client-facing summary of what was delivered with a confidence
  score.

## Phase 3: Reflection ✅

**Why this shape is right for the brief:**
- The cost is already on `Lesson::amount_pence` and already in the lessons list
  response. The View Summary dialog was the one surface that omitted it.
- For the Schedule surface, the smallest change is one field in one mapper +
  one line in the Vue template. No new model, action, route, or resource.

**Operational notes:**
- No DB migration needed. No API contract change beyond a single optional
  field on the calendar item payload.
- No regression risk for callers that don't read `amount_pence` — adding a
  field to an object payload is backward compatible.

**Out of scope, carried forward:**
- A long-term improvement would be to consolidate the two "completed lesson
  detail" surfaces (Schedule sheet + Lessons dialog) into a single component
  that both call sites mount. That's a refactor, not part of this brief.

**Technical debt / follow-up not done:**
- No tests added (project rule: user maintains tests manually).
- No Pint / Prettier run (project rule: user handles code style).

---

**Status:** All phases complete.
**Last Updated:** 2026-06-17.
