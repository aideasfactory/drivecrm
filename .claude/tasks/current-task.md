# Task: Instructor screen: show active pupils only

**Created:** 2026-03-23
**Last Updated:** 2026-03-23T10:30:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Change the instructor view so the Pupils list only shows active pupils by default, with a toggle to show all pupils.

### Context
- Tile ID: 019ce7ac-e041-73be-a396-70738093366c
- Repository: drivecrm
- Branch: feature/019ce7ac-e041-73be-a396-70738093366c-instructor-screen-show-active-pupils-only
- Priority: HIGH
- Customer: Drive

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Review current pupils display on instructor screen
- [x] Identify Student status field and "active" definition
- [x] Trace data flow: Controller → Service → Action → Frontend
- [x] Identify where filtering should be applied
- [x] Check for existing tests
- [x] Plan implementation approach

### Reflection
The `status` column on the `students` table is the correct field to filter on. Filtering applied at the Action level with parameter passed through Controller → Service → Action. Frontend toggle allows staff to see all pupils when needed.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Add `?status` filter to `GetInstructorPupilsAction` (default: `'active'`)
- [x] Update `InstructorService::getPupils()` to accept and pass status filter
- [x] Update `InstructorController::pupils()` to read `?status` query param
- [x] Add "Show all pupils" toggle to `ActivePupilsTab.vue`
- [x] Write Pest feature tests for active-only and show-all filtering

### Reflection
Implementation was straightforward. The 3-layer pattern (Controller → Service → Action) made it clean to thread the new `status` parameter through. The frontend toggle uses a Checkbox component consistent with the existing UI patterns. Four test cases cover: default active-only, show-all, instructor scoping, and specific status filtering.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Final reflection on implementation
- [x] Update current-task.md with completion status
- [x] Write .phase_done sentinel file

### Reflection
Clean, minimal change that follows existing patterns. No new dependencies, no migrations, no breaking changes. The default behaviour changes from showing all pupils to showing only active ones, with an opt-in toggle for staff who need to see everyone.

### Files Changed
- `app/Actions/Instructor/GetInstructorPupilsAction.php` — Added `$status` parameter with `where('status', ...)` filter
- `app/Services/InstructorService.php` — Passed `$status` parameter through to action
- `app/Http/Controllers/InstructorController.php` — Read `?status` query param (defaults to `'active'`)
- `resources/js/components/Instructors/Tabs/ActivePupilsTab.vue` — Added "Show all pupils" checkbox toggle
- `tests/Feature/Instructor/InstructorPupilsFilterTest.php` — 4 new test cases

### Score: 8/10
Solid implementation following existing patterns. Minor consideration: the `status` query param is not validated against a whitelist of allowed values, but since it maps directly to a database column value comparison this is low risk. No anti-patterns introduced.
