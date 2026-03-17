# Task: Create instructor API endpoints for grouped student lists and recent activity

**Created:** 2026-03-17
**Last Updated:** 2026-03-17T13:10:00Z
**Status:** Complete

---

## Overview

### Goal
Build an instructor-facing API endpoint that returns students grouped into 4 objects: `active`, `passed`, `inactive`, and `recent_activity` (5 most recently updated students). Scoped to the authenticated instructor via token.

### Context
- Tile ID: 019cfbd9-0500-7381-b554-34ee999619a4
- Repository: drivecrm
- Branch: feature/019cfbd9-0500-7381-b554-34ee999619a4-create-instructor-api-endpoints-for-grouped-student-lists-an
- Priority: MEDIUM
- Customer: Drive

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Explore existing codebase patterns (Student model, Instructor model, API routes, Services, Actions, Resources)
- [x] Identify student status field values: active, inactive, on_hold, passed, failed, completed
- [x] Confirm architecture: Controller → Service → Action pattern
- [x] Plan file structure for new endpoint

### Reflection
Codebase is well-structured with clear patterns. The Student model has a `status` field with the exact values needed. The existing `GetInstructorPupilsAction` provides a good reference for querying instructor-scoped students.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create `GetGroupedStudentsAction` in `app/Actions/Instructor/`
- [x] Create `StudentResource` in `app/Http/Resources/V1/`
- [x] Add `getGroupedStudents()` method to `InstructorService`
- [x] Create `InstructorStudentController` in `app/Http/Controllers/Api/V1/`
- [x] Add route to `routes/api.php`
- [x] Update `.claude/api.md` with endpoint documentation
- [x] Write Pest tests

### Reflection
Implementation follows the established Controller → Service → Action pattern exactly. Reused the existing `InstructorService` rather than creating a duplicate. The `StudentResource` is a new Eloquent API Resource that can be reused for future student-related endpoints. Tests cover grouping logic, scoping, max 5 recent activity, empty states, auth, and response structure.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Files Created
- `app/Actions/Instructor/GetGroupedStudentsAction.php` — Business logic for grouping students by status
- `app/Http/Resources/V1/StudentResource.php` — Eloquent API Resource for student data
- `app/Http/Controllers/Api/V1/InstructorStudentController.php` — API controller
- `tests/Feature/Api/V1/InstructorStudentControllerTest.php` — 6 Pest feature tests

### Files Modified
- `app/Services/InstructorService.php` — Added `getGroupedStudents()` method
- `routes/api.php` — Added `GET /api/v1/instructor/students` route
- `.claude/api.md` — Documented new endpoint with full request/response examples

### Architecture Decisions
- Single query fetches all instructor's students, then groups in-memory (efficient for typical instructor student counts)
- `recent_activity` pulls from all statuses sorted by `updated_at`, not just a single status group
- Used `StudentResource` (not `StudentProfileResource`) to keep API and auth profile resources separate — different use cases, different field sets

### Potential Considerations
- If instructors accumulate hundreds of students, the single-query approach may benefit from pagination per group. For now, the typical instructor student count makes this unnecessary.
- No additional status filtering (e.g., `on_hold`, `failed`, `completed`) was requested. The structure is easy to extend by adding new keys to the Action's return array.
