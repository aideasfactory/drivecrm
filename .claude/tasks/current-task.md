# Task: Create a student record endpoint with access policy for students and linked instructors

**Created:** 2026-03-17
**Last Updated:** 2026-03-17T14:12:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Build a secure API endpoint (`GET /api/v1/students/{student}`) that returns an individual student record, protected by a policy that allows access only when:
1. The authenticated user IS the student (user_id match), OR
2. The authenticated user is an instructor who owns the student (instructor_id match)

### Context
- Tile ID: 019cfc05-9605-73dc-8367-42b22a2967d5
- Repository: drivecrm
- Branch: feature/019cfc05-9605-73dc-8367-42b22a2967d5-create-a-student-record-endpoint-with-access-policy-for-stud
- Priority: MEDIUM

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Review existing Student model, User model, and relationships
- [x] Review existing API structure (controllers, resources, services, routes)
- [x] Identify files to create/modify
- [x] Document the implementation plan

### Reflection
Clear codebase patterns to follow. First policy in the project. Laravel auto-discovers policies by convention.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create StudentPolicy with `view` method
- [x] Create GetStudentByIdAction
- [x] Add `getById()` to StudentService
- [x] Create StudentController with `show` method
- [x] Add route to api.php
- [x] Write feature tests (6 tests covering all access scenarios)
- [x] Update api.md with endpoint documentation

### Reflection
Implementation follows the Controller → Service → Action pattern. The policy is the first in the project and uses Laravel's auto-discovery convention. Tests cover: self-access, instructor-access, instructor-denied, student-cross-access-denied, unauthenticated, and 404 scenarios.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Final review of all created files
- [x] Update current-task.md with reflection
- [x] Write .phase_done sentinel

### Reflection
All files follow project conventions: `declare(strict_types=1)`, PHPDoc blocks, proper namespacing, and the Controller → Service → Action architecture. The StudentPolicy is clean and reusable — additional policy methods (update, delete, etc.) can be added later. The endpoint reuses the existing StudentResource. No anti-patterns or technical debt introduced.

### Score: 9/10
Solid implementation following all project patterns. Deducted one point because the `StudentService::getById()` doesn't use caching (following BaseService `remember()` pattern), but caching isn't needed for a single-record lookup that's policy-gated.
