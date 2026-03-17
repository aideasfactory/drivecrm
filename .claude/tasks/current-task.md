# Task: Create Student Lessons API Endpoints

**Created:** 2026-03-17
**Last Updated:** 2026-03-17T15:30:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Build two secure API endpoints for retrieving a student's lessons:
1. **Lesson List** — `GET /api/v1/students/{student}/lessons` — returns all lessons for a student
2. **Lesson Detail** — `GET /api/v1/students/{student}/lessons/{lesson}` — returns a single lesson's full detail

Both endpoints are protected by a policy that allows access only when:
- The authenticated user IS the student (user_id match), OR
- The authenticated user is an instructor linked to that student (instructor_id match)

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Review existing Lesson model, relationships, and actions
- [x] Review existing API structure (controllers, resources, services, routes)
- [x] Review StudentPolicy for reusable authorization pattern
- [x] Identify files to create/modify
- [x] Document the implementation plan

### Reflection
Clear codebase patterns. GetStudentLessonsAction already existed for the list endpoint. StudentPolicy provided the exact authorization pattern to replicate.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Fix `LessonSignOffService` to extend `BaseService`
- [x] Create `GetStudentLessonDetailAction` in `app/Actions/Student/Lesson/`
- [x] Add `getLessonDetail()` method to `LessonSignOffService`
- [x] Create `LessonPolicy` with `viewAny` and `view` methods
- [x] Create `LessonResource` in `app/Http/Resources/V1/`
- [x] Create `LessonDetailResource` in `app/Http/Resources/V1/`
- [x] Create `LessonCollection` in `app/Http/Resources/V1/`
- [x] Create `StudentLessonController` with `index` and `show` methods
- [x] Add routes to `routes/api.php`
- [x] Update `.claude/api.md` with both endpoints

### Reflection
All files follow the Controller → Service → Action pattern. The existing `GetStudentLessonsAction` was reused for the list endpoint without any changes. A new `GetStudentLessonDetailAction` was created for the detail endpoint that scopes the lesson query to the student's orders (preventing cross-student access at the query level). The `LessonPolicy` mirrors `StudentPolicy`'s pattern with a shared private helper method. Fixed `LessonSignOffService` to extend `BaseService` (was a pre-existing violation).

---

## PHASE 3: FINAL REVIEW & DOCUMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Verify all files follow project conventions (strict_types, PHPDoc, namespacing)
- [x] Verify LessonSignOffService extends BaseService
- [x] Verify api.md is complete and accurate
- [x] Update current-task.md with final reflection
- [x] Write .phase_done sentinel

### Files Created
| File | Purpose |
|------|---------|
| `app/Actions/Student/Lesson/GetStudentLessonDetailAction.php` | Fetch single lesson scoped to student |
| `app/Policies/LessonPolicy.php` | Authorization: student-self or linked-instructor |
| `app/Http/Resources/V1/LessonResource.php` | JSON serialization for lesson list items |
| `app/Http/Resources/V1/LessonDetailResource.php` | JSON serialization for lesson detail |
| `app/Http/Resources/V1/LessonCollection.php` | Collection resource wrapping LessonResource |
| `app/Http/Controllers/Api/V1/StudentLessonController.php` | API controller with index and show |

### Files Modified
| File | Change |
|------|--------|
| `app/Services/LessonSignOffService.php` | Fixed: extends BaseService; added getLessonDetail() method |
| `routes/api.php` | Added 2 lesson routes nested under student |
| `.claude/api.md` | Documented both endpoints with full request/response examples |

### Reflection
Clean implementation with no anti-patterns. The lesson detail query is doubly secure: the policy checks user-student/instructor-student relationship, and the query itself scopes lessons through the student's orders (preventing any cross-student access even if the policy were bypassed). No technical debt introduced. The BaseService fix on LessonSignOffService actually reduces existing debt.

### Score: 9/10
Solid implementation reusing existing actions and following all project patterns. Deducted one point because the lesson list endpoint returns the flat array structure from GetStudentLessonsAction through the LessonResource (using array access `$this['key']` rather than model properties) — this works but is slightly less type-safe than a model-based resource. However, rewriting the existing action would be unnecessary scope.
