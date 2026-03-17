# Task: Expand Lessons API — Card Statuses, Reflective Logs, Resources

**Created:** 2026-03-17
**Last Updated:** 2026-03-17T16:30:00Z
**Status:** 🔄 In Progress

---

## Overview

### Goal
Expand the Lessons API with:
1. **Computed card statuses** — green (signed off), red (needs sign-off), orange (current/next), blue (upcoming)
2. **Reflective Log** relationship — lessons require a reflective log for sign-off; past lessons without one are red
3. **Resources** relationship — many-to-many between lessons and resources
4. **Fix authorize bug** in StudentLessonController (`$this->authorize()` → `Gate::authorize()`)

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Reflection
Clear plan. Authorize bug confirmed — Laravel 12's base Controller has no AuthorizesRequests trait.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Fix authorize bug in StudentLessonController → Gate::authorize()
- [x] Create reflective_logs migration
- [x] Create lesson_resource pivot migration
- [x] Create ReflectiveLog model
- [x] Add reflectiveLog + resources relationships to Lesson model
- [x] Add lessons relationship to Resource model
- [x] Create LessonCardStatus enum
- [x] Create ComputeLessonCardStatusAction
- [x] Update GetStudentLessonsAction with card_status, has_reflective_log, resources_count
- [x] Update GetStudentLessonDetailAction to eager-load reflectiveLog and resources
- [x] Update LessonSignOffService to compute card_status in getLessonDetail
- [x] Create ReflectiveLogResource
- [x] Create LessonResourceResource
- [x] Update LessonResource with card_status, has_reflective_log, resources_count, payment_status
- [x] Update LessonDetailResource with card_status, reflective_log, resources, has_reflective_log
- [x] Update .claude/api.md
- [x] Update .claude/database-schema.md

### Files Created
| File | Purpose |
|------|---------|
| `database/migrations/2026_03_17_170923_create_reflective_logs_table.php` | Reflective logs table |
| `database/migrations/2026_03_17_170923_create_lesson_resource_table.php` | Lesson-Resource pivot table |
| `app/Models/ReflectiveLog.php` | ReflectiveLog model |
| `app/Enums/LessonCardStatus.php` | Card status enum (signed_off, needs_sign_off, current, upcoming) |
| `app/Actions/Student/Lesson/ComputeLessonCardStatusAction.php` | Compute card status for single lesson |
| `app/Http/Resources/V1/ReflectiveLogResource.php` | API resource for reflective log |
| `app/Http/Resources/V1/LessonResourceResource.php` | API resource for lesson resources |

### Files Modified
| File | Change |
|------|--------|
| `app/Http/Controllers/Api/V1/StudentLessonController.php` | Fixed: `$this->authorize()` → `Gate::authorize()` |
| `app/Models/Lesson.php` | Added reflectiveLog() and resources() relationships |
| `app/Models/Resource.php` | Added lessons() relationship |
| `app/Actions/Student/Lesson/GetStudentLessonsAction.php` | Card status computation, reflective log & resources data |
| `app/Actions/Student/Lesson/GetStudentLessonDetailAction.php` | Eager-loads reflectiveLog and resources |
| `app/Services/LessonSignOffService.php` | Injects ComputeLessonCardStatusAction, computes card_status in getLessonDetail |
| `app/Http/Resources/V1/LessonResource.php` | Added card_status, has_reflective_log, resources_count, payment_status |
| `app/Http/Resources/V1/LessonDetailResource.php` | Added card_status, reflective_log, resources, has_reflective_log |
| `.claude/api.md` | Updated lesson list and detail docs, added card status docs |
| `.claude/database-schema.md` | Added reflective_logs and lesson_resource tables |

### Reflection
All files follow Controller → Service → Action pattern. Card status is computed at the Action level for list (efficient, single pass) and via a dedicated Action for detail (requires knowing the student's next lesson). The authorize bug fix is minimal — just swapping to Gate facade which works without the trait.

---

## PHASE 3: FINAL REVIEW & DOCUMENTATION
**Status:** 🔄 In Progress

### Tasks
- [ ] Verify all files follow project conventions
- [ ] Final check on api.md and database-schema.md
- [ ] Write .phase_done sentinel
