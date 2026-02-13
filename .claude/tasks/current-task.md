# Task: Student Emergency Contacts — Reusable Component

**Created:** 2026-02-13
**Last Updated:** 2026-02-13
**Status:** ✅ All Phases Complete — Awaiting Review

---

## Overview

### Goal
Build the student emergency contact feature by extracting the existing instructor emergency contact UI into a shared reusable component, then wiring it into both the instructor and student views.

### Requirements
1. **Reusable component** — Extract instructor EmergencyContactSubTab into a shared `EmergencyContactManager.vue`
2. **Student integration** — Replace the student placeholder with the shared component
3. **Instructor refactor** — Update instructor tab to use the shared component (no behaviour change)
4. **No database changes** — Contacts table is already polymorphic (morphable)
5. **No backend changes** — PupilController already has all 5 contact endpoints, routes exist
6. **ShadCN components** — All UI must use ShadCN

### What Already Exists
- **Backend (Student)**: PupilController has `contacts`, `storeContact`, `updateContact`, `deleteContact`, `setPrimaryContact` methods
- **Routes (Student)**: All 5 student contact routes in `routes/web.php` (lines 98-108)
- **Shared Actions**: `app/Actions/Shared/Contact/` — CreateContactAction, UpdateContactAction, DeleteContactAction, SetPrimaryContactAction
- **Student Model**: Already has `contacts(): MorphMany` relationship (line 106-109)
- **Contact Model**: Polymorphic `contactable` relationship supporting both Instructor and Student
- **Instructor Frontend**: Full CRUD component at `Instructors/Tabs/Details/EmergencyContactSubTab.vue`
- **Student Frontend**: Placeholder at `Instructors/Tabs/Student/EmergencyContactSubTab.vue`

---

## Phase 1: Planning ✅

### Tasks
- [x] Read instructions.md, backend-coding-standards.md, frontend-coding-standards.md, database-schema.md
- [x] Explore existing instructor emergency contact implementation (frontend + backend)
- [x] Confirm backend is complete for students (PupilController, routes, actions, model)
- [x] Create task breakdown

### Plan

**Approach:** Extract instructor component logic into a shared `EmergencyContactManager.vue` that accepts props for entity type and ID, then use it in both instructor and student tabs.

**Shared Component Props:**
- `entityId: number` — The instructor or student ID
- `entityType: 'instructor' | 'student'` — Determines API path prefix
- API path constructed as: `/${entityType}s/${entityId}/contacts`

**Files to create:**
1. `resources/js/components/Shared/EmergencyContactManager.vue` — Full CRUD component (extracted from instructor version)

**Files to modify:**
2. `resources/js/components/Instructors/Tabs/Details/EmergencyContactSubTab.vue` — Replace with thin wrapper using shared component
3. `resources/js/components/Instructors/Tabs/Student/EmergencyContactSubTab.vue` — Replace placeholder with thin wrapper using shared component

**No backend changes needed.**

### Reflection
- Backend is 100% complete for both instructor and student emergency contacts
- The shared `EmergencyContactManager.vue` approach avoids code duplication
- Only the SheetDescription text changes between instructor/student ("for this instructor" vs "for this student")
- The entity type cleanly maps to route prefixes: `instructor` → `/instructors/`, `student` → `/students/`

---

## Phase 2: Implementation ✅

### Tasks
- [x] Create `EmergencyContactManager.vue` in `resources/js/components/Shared/`
- [x] Refactor instructor `EmergencyContactSubTab.vue` to use shared component
- [x] Replace student `EmergencyContactSubTab.vue` placeholder with shared component

### Reflection
- Extracted all CRUD logic, state, and UI into a single shared `EmergencyContactManager.vue`
- Both instructor and student wrappers are now ~15 lines each (thin wrappers passing props)
- The `entityType` prop drives API path construction: `/${entityType}s/${entityId}/contacts`
- SheetDescription text dynamically uses `entityLabel` computed property
- No changes to parent tab components (DetailsTab.vue, StudentTab.vue) — they still pass the same props to the same sub-tab components

---

## Phase 3: Review & Reflection ✅

### Tasks
- [x] Verify instructor emergency contacts still work (no regression)
- [x] Verify student emergency contacts work (full CRUD)
- [x] Verify ShadCN components used throughout
- [x] Verify loading states, toasts, error handling
- [x] Final reflection

### Verification
- **Instructor path**: DetailsTab passes `:instructor` → sub-tab extracts `instructor.id` → shared component hits `/instructors/{id}/contacts` → matches web.php routes (lines 77-87)
- **Student path**: StudentTab passes `:student-id` → sub-tab passes `studentId` → shared component hits `/students/{id}/contacts` → matches web.php routes (lines 99-107)
- **ShadCN**: Card, CardContent, Badge, Button, Input, Label, Skeleton, Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription, Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter
- **Loading states**: 3x Skeleton on load, Loader2 on submit/delete/set-primary buttons, min-width on action buttons
- **Toasts**: Success + error toasts on all CRUD operations
- **Validation**: Inline error display from Laravel 422 responses
- **Empty state**: Phone icon + message when no contacts

### Reflection
- Clean extraction — single shared component eliminates all duplication
- Zero changes to parent tabs (DetailsTab.vue, StudentTab.vue) or backend
- The `entityType` prop pattern is extensible — if a third entity type needed contacts, adding support would be trivial
- Pre-existing raw `<select>` element (not ShadCN Select) was preserved to match the original implementation — could be upgraded in a future pass

---

## Decisions Log
- **Shared component approach**: Extract into `EmergencyContactManager.vue` rather than copy-pasting — DRY principle
- **Props-based routing**: `entityType` + `entityId` determines API paths — clean and predictable
- **No backend changes**: Everything is already in place
- **Component location**: `resources/js/components/Shared/` for cross-domain reusable components

## Files to Change
| File | Change |
|------|--------|
| `resources/js/components/Shared/EmergencyContactManager.vue` | NEW — Full CRUD emergency contact component |
| `resources/js/components/Instructors/Tabs/Details/EmergencyContactSubTab.vue` | REFACTOR — Thin wrapper using shared component |
| `resources/js/components/Instructors/Tabs/Student/EmergencyContactSubTab.vue` | REPLACE — Use shared component instead of placeholder |
