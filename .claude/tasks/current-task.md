# Task: Create student create, update, and delete API endpoints with student-or-linked-instructor policy enforcement

**Created:** 2026-03-19
**Last Updated:** 2026-03-19T09:45:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Build API endpoints for creating, updating, and deleting student records with student-or-linked-instructor policy enforcement.

### Context
- Tile ID: 019d01ca-ec12-7391-aa9c-a4af8e7eaa2d
- Repository: drivecrm
- Branch: feature/019d01ca-ec12-7391-aa9c-a4af8e7eaa2d-create-student-create-update-and-delete-api-endpoints-with-s

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Reflection
Explored all existing patterns thoroughly. Codebase follows strict Controller → Service → Action with BaseService, FormRequests, Eloquent Resources, and Gate-based StudentPolicy.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create CreateStudentAction
- [x] Create UpdateStudentAction
- [x] Create DeleteStudentAction
- [x] Add create/update/delete methods to StudentPolicy
- [x] Add create/update/delete methods to StudentService
- [x] Create StoreStudentRequest FormRequest
- [x] Create UpdateStudentRequest FormRequest
- [x] Add store/update/destroy to StudentController
- [x] Add routes to api.php
- [x] Update api.md documentation
- [x] Write Pest tests (17 tests across create/update/delete)

### Reflection
All endpoints follow existing patterns exactly. Policy uses shared helper method for DRY authorization. Service invalidates instructor grouped_students cache on mutations. Tests cover all permission boundaries.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Reflection
- All three endpoints (POST, PUT, DELETE) follow the existing Controller → Service → Action architecture
- StudentPolicy uses a shared `isStudentOrLinkedInstructor` helper to keep authorization DRY
- Cache invalidation for instructor grouped_students is handled on create, update, and delete
- api.md fully documented with request/response examples
- 17 Pest tests written covering success, auth, validation, and permission boundaries
- No anti-patterns or technical debt introduced
- Score: 9/10 — clean implementation following all existing conventions
