# Task: Folder visibility control for instructors and pupils

**Created:** 2026-09-10
**Last Updated:** 2026-09-10
**Status:** Complete

---

## Overview

Pupils could see instructor-only folders (e.g. VTS, Standards Check Success)
in the mobile library even when those folders contained no pupil resources.
Admin can now set folder visibility (instructor / pupil / both). The mobile
API hides folders the caller should not see and exposes `visibility` on
folder objects.

### Success Criteria
- [x] Admin can set folder visibility (instructor / pupil / both) on create and edit
- [x] Student resource tree and related student library endpoints omit hidden folders
- [x] Instructor resource tree omits folders hidden from instructors
- [x] Folder `visibility` is exposed on API folder objects
- [x] `.claude/api.md` and `.claude/database-schema.md` are updated
- [x] No tests added (HARD RULE)

---

## PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Trace folders, admin UI, and mobile resource APIs
- [x] Choose storage (`visibility` enum) and API shape

### Decisions Made
- Single `visibility` column (`student` | `instructor` | `both`), default `both`.
  API uses `student` (not `pupil`) to match existing resource `audience`.
- Server-side filter AND include `visibility` on folder objects for the app.
- Student tree also prunes empty folders after the audience filter.

### Reflection
Reusing the resource audience button-group and the instructor-tree prune
kept the change small. No mobile app work.

**Last Updated:** 2026-09-10.

---

## PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Currently working on
Complete.

### Tasks
- [x] Migration + enum + model scopes
- [x] Admin create/edit folder visibility + FolderCard badge
- [x] Student and instructor trees filter + prune
- [x] Student summary / my_resources / badges / published list respect folder visibility
- [x] Invalidate cached folder trees on folder write
- [x] Update api.md and database-schema.md

### Reflection
Folder visibility is independent of per-resource `audience`. Existing
folders stay `both` until staff toggle VTS / Standards Check Success to
instructor-only.

I've updated database-schema.md to reflect the migration changes.
I've updated api.md to reflect the new/changed endpoint.

**Last Updated:** 2026-09-10.

---

## PHASE 3: REFLECTION

**Status:** ✅ Complete

### Tasks
- [x] Document decisions and leftover risks

### Reflection
Leftover: staff must set instructor-only on existing folders after
migrate — default `both` is conservative. Student tree now also prunes
empty folders, so even untoggled instructor libraries disappear from
pupils if they contain no student-audience files. App consumption is
Sam's follow-up.

No tests added, per HARD RULE. I understand I must not run tests or
linting commands.

**Last Updated:** 2026-09-10.
