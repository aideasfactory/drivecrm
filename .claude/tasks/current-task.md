# Task: Create Pupils Listing Page

**Created:** 2026-03-05
**Last Updated:** 2026-03-05T14:00:00Z
**Status:** In Progress

---

## 📋 Overview

### Goal
Create a pupils listing page at `/pupils` that displays all students in the system in a searchable table with pupil name (avatar), associated instructor (avatar), and clickable navigation to detail pages.

### Success Criteria
- [ ] Page at `/pupils` lists all students in a ShadCN Table
- [ ] Columns: Pupil name (with avatar), Instructor (with avatar or "Unassigned")
- [ ] Search filters pupils by name, email, or instructor name
- [ ] Pupil names are clickable (link to `/students/{id}` detail page)
- [ ] Instructor names are clickable (link to `/instructors/{id}`)
- [ ] Handles pupils with no assigned instructor gracefully
- [ ] Feature test covers the listing endpoint

### Context
- **Existing**: PupilController with empty `index()`, Pupils/Index.vue placeholder, route `/pupils` registered, sidebar link exists
- **Missing**: GetAllStudentsAction, StudentService, data passing in controller, table UI in Vue page
- **Reference patterns**: Instructors/Index.vue, Packages/Index.vue, GetAllPackagesAction

---

## 🎯 PHASE 1: PLANNING
**Status:** ✅ Complete

### Architecture
- **Action**: `GetAllStudentsAction` in `app/Actions/Student/` — fetches all students with instructor + user relationships
- **Service**: `StudentService` in `app/Services/` — orchestrates actions, injected into PupilController
- **Controller**: Update `PupilController::index()` — map students to listing data, pass to Inertia
- **Frontend**: Update `Pupils/Index.vue` — ShadCN Table with search, avatars, clickable links
- **Types**: Update `resources/js/types/pupil.ts` — add `instructor_id` and `instructor_name` fields
- **Tests**: `tests/Feature/Pupils/PupilListingTest.php`

### Data Shape (Controller → Frontend)
```
{
  id: number
  name: string              // first_name + surname
  email: string | null
  instructor_id: number | null
  instructor_name: string | null
  status: string
}
```

### Key Decisions
1. No new migration needed — students table already has all required fields
2. Use existing `StudentFactory` (has instructor_id) for tests
3. Follow Instructors/Index.vue pattern exactly for UI consistency
4. Client-side search (same pattern as other listing pages)
5. Avatar uses initials fallback (no profile photo column exists)

### Reflection
Planning complete. Identified all existing infrastructure (controller, route, sidebar, placeholder page) and determined minimal changes needed.

---

## 🔨 PHASE 2: IMPLEMENTATION
**Status:** ⏸️ Not Started

### Tasks

**Backend:**
- [ ] Create `GetAllStudentsAction` in `app/Actions/Student/`
- [ ] Create `StudentService` in `app/Services/`
- [ ] Update `PupilController::index()` to pass student data via service
- [ ] Run Wayfinder generation

**Frontend:**
- [ ] Update `resources/js/types/pupil.ts` with listing fields
- [ ] Update `resources/js/pages/Pupils/Index.vue` with table, search, avatars, links

**Testing:**
- [ ] Create `tests/Feature/Pupils/PupilListingTest.php`

### Currently Working On
Not started yet

### Reflection
_To be filled after implementation_

---

## 💭 PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ⏸️ Not Started

### Summary
_To be filled after all phases complete_

### Files Changed
_To be filled_

### Potential Overhead / Anti-patterns
_To be filled_

### Score
_To be filled_
