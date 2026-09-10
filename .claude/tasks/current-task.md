# Task: Admin Schedule Weekly calendar view

## Overview

Instructors in admin Schedule (`/instructors/{id}?tab=schedule`) can see
Today and Monthly calendar modes. They need a Weekly view so they can
see that week's diary layout. Web admin only — do not change the mobile
API or app.

## Phase 1: Planning ✅

### Current state
- `ScheduleTab.vue` loads calendar items via
  `GET /instructors/{id}/calendar?start_date&end_date`.
- `useCalendarNavigation` supports `week` | `month`.
- `WeeklyCalendarGrid` is a Mon–Sun time-grid diary.
- `MonthlyCalendarGrid` is a month overview.
- Nav has a Today jump button plus Week / Month toggles.
  The ticket describes Today + Monthly and asks for Weekly
  alongside them.

### Approach
1. Add a first-class `day` view (labeled Today) using the existing
   time-grid with a single column.
2. Relabel the view toggle to **Today | Weekly | Monthly**.
3. Keep prev / next / Today jump working for all three views.
4. Reuse the existing range loader — no backend or mobile API change.

### Tasks
- [x] Trace Schedule tab, navigation composable, and calendar grids
- [x] Confirm calendar range endpoint already supports a single day
- [x] Choose Today | Weekly | Monthly toggle matching existing patterns

### Reflection
Weekly already existed as "Week". The reporter asked for Weekly
alongside Today and Monthly, so expose three explicit views and
generalise the time-grid for 1 or 7 days. No migration. No mobile API.

**Last Updated:** 2026-09-10.

## Phase 2: Implementation ✅

### Currently working on
Complete.

### Tasks
- [x] Extend `useCalendarNavigation` with day view + setView
- [x] Generalise `WeeklyCalendarGrid` for a variable number of days
- [x] Update `ScheduleTab` toggle, labels, and prev/next/today
- [x] No tests (HARD RULE). No mobile app changes.

### Reflection
Today is a single-day time-grid, Weekly is the existing 7-day diary
(relabelled), Monthly is unchanged. Prev/next/Today jump work in all
three views. Calendar range API already accepted a single day.

No database-schema.md update — no migration.
No api.md update — web admin UI only, not a mobile API endpoint.

## Phase 3: Reflection ⏸️

### Tasks
- [ ] Document decisions

### Reflection
(pending)
