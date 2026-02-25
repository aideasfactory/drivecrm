# Task: Student Actions Tab

**Created:** 2026-02-19
**Last Updated:** 2026-02-19
**Status:** ✅ Complete — All 8 phases finished

---

## Overview

### Goal
Build out the Student Actions tab (`ActionsSubTab.vue`) with five functional sections:
1. **Emergency Contacts** — Enhanced version with address field + auto-populate from student contact details
2. **Pickup Points** — CRUD for student pickup/drop-off addresses with postcode geocoding and default flag
3. **Student Status** — Update student status (active/inactive) with notes
4. **Remove Student** — Detach student from instructor's account
5. **Student Checklist** — Trackable checklist items with dates and notes (e.g., "Book theory test" → date booked)

### What Already Exists
- **Emergency Contacts**: Full CRUD — `Contact` model (polymorphic), `EmergencyContactManager.vue` (shared component), `PupilController` endpoints, routes. **Missing: `address` field on contacts table.**
- **Student Model**: Has `contact_first_name`, `contact_surname`, `contact_email`, `contact_phone` fields (third-party booker details) — these should auto-populate the first emergency contact.
- **ActionsSubTab.vue**: Currently a placeholder with "coming soon" message.
- **PupilController**: Already has contact CRUD, notes, messages, lessons endpoints.

### What Needs to Be Built

#### Database Changes
1. **Migration: Add `address` to `contacts` table** — text, nullable
2. **Migration: Add `status` and `inactive_reason` to `students` table** — enum + text
3. **Migration: Create `student_pickup_points` table** — address, postcode, lat/lng, is_default, label
4. **Migration: Create `student_checklist_items` table** — student_id, key (slug), checked, date, notes

#### Backend (Models, Actions, Services, Controller, Routes)
- `StudentPickupPoint` model + factory
- `StudentChecklistItem` model + factory
- Update `Contact` model ($fillable with address)
- Update `Student` model (add `status` to fillable, relationships for pickup points & checklist items)
- Actions: `GetStudentPickupPointsAction`, `CreatePickupPointAction`, `UpdatePickupPointAction`, `DeletePickupPointAction`, `SetDefaultPickupPointAction`
- Actions: `GetStudentChecklistAction`, `ToggleChecklistItemAction`
- Actions: `UpdateStudentStatusAction`, `RemoveStudentFromInstructorAction`
- Action: `AutoCreateEmergencyContactAction` (auto-populate from student contact fields)
- Service: Update existing `PupilController` or create dedicated service methods
- Routes: Pickup points CRUD, checklist toggle, status update, remove student

#### Frontend (Vue Components)
- **ActionsSubTab.vue** — Layout container (2-col grid from wireframe)
- **EmergencyContactSection** — Reuse existing `EmergencyContactManager` + auto-populate logic
- **PickupPointsSection.vue** — CRUD with postcode lookup, default toggle, edit/delete
- **StudentStatusSection.vue** — Status dropdown + notes textarea + update button
- **RemoveStudentSection.vue** — Remove button with confirmation dialog
- **StudentChecklistSection.vue** — Checklist grid with checkboxes, date picker modal, notes

### Wireframe Reference
`wireframes/student-actions.html` — 2-column grid layout:
- Row 1: [Transfer Student (SKIP)] | [Emergency Contacts]
- Row 2: [Pickup Points] | [Student Status]
- Row 3 (full-width): [Student Checklist]
- Row 4 (full-width): [General Actions — Remove Student only]

### Excluded (per user request)
- Transfer Student section
- Account Management section (Send Password Reset Link)

---

## Phase 1: Planning ✅

### Tasks
- [x] Read all instruction files and coding standards
- [x] Explore existing codebase (Contact model, Student model, EmergencyContactManager, PupilController, routes)
- [x] Analyze wireframe for layout structure
- [x] Identify what exists vs what needs to be built
- [x] Design database schema for new tables
- [x] Plan architecture and phased breakdown
- [x] Get approval on plan before proceeding

### Database Design

#### 1. Migration: `add_address_to_contacts_table`
```sql
ALTER TABLE contacts ADD COLUMN address TEXT NULLABLE AFTER email;
```

#### 2. Migration: `add_status_fields_to_students_table`
```sql
ALTER TABLE students
  ADD COLUMN status VARCHAR(50) DEFAULT 'active' AFTER owns_account,
  ADD COLUMN inactive_reason TEXT NULLABLE AFTER status;
```
Status values: `active`, `inactive`, `on_hold`, `passed`, `failed`, `completed`

#### 3. Migration: `create_student_pickup_points_table`
```sql
CREATE TABLE student_pickup_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    label VARCHAR(255) NOT NULL,          -- e.g., "Home", "School", "Work"
    address TEXT NOT NULL,                  -- Full address line
    postcode VARCHAR(10) NOT NULL,          -- UK postcode
    latitude DECIMAL(10,8) NULLABLE,        -- From postcode geocoding
    longitude DECIMAL(11,8) NULLABLE,       -- From postcode geocoding
    is_default BOOLEAN DEFAULT FALSE,       -- One default per student
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (student_id),
    INDEX (student_id, is_default)
);
```

#### 4. Migration: `create_student_checklist_items_table`
```sql
CREATE TABLE student_checklist_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL REFERENCES students(id) ON DELETE CASCADE,
    key VARCHAR(100) NOT NULL,              -- e.g., "book_theory_test"
    label VARCHAR(255) NOT NULL,            -- e.g., "Book theory test"
    category VARCHAR(100) NOT NULL,         -- e.g., "Theory Test", "Practical Test", "General"
    is_checked BOOLEAN DEFAULT FALSE,
    date DATE NULLABLE,                     -- Date for the item (e.g., when theory test booked for)
    notes TEXT NULLABLE,                    -- Optional notes
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE (student_id, key),
    INDEX (student_id)
);
```

Default checklist items (seeded per student):
- **Theory Test**: Book theory test, Sit theory test
- **Practical Test**: Schedule mock test, Sit mock test, Book practical test, Sit practical test
- **General**: Agreed terms, Driving licence number, Eyesight checked

### Architecture Plan

**Phase 2: Database & Models** — Migrations, models, factories
**Phase 3: Backend — Emergency Contacts Enhancement** — Add address field, auto-populate action
**Phase 4: Backend — Pickup Points** — Full CRUD with postcode geocoding
**Phase 5: Backend — Student Status & Remove Student** — Status update, remove from instructor
**Phase 6: Backend — Student Checklist** — Checklist seeding, toggle with date/notes
**Phase 7: Frontend — ActionsSubTab.vue** — Build all 5 sections using ShadCN components
**Phase 8: Review & Reflection** — Code review, edge cases, final reflection

### Route Plan

```
# Pickup Points
GET    /students/{student}/pickup-points                  → PupilController@pickupPoints
POST   /students/{student}/pickup-points                  → PupilController@storePickupPoint
PUT    /students/{student}/pickup-points/{pickupPoint}     → PupilController@updatePickupPoint
DELETE /students/{student}/pickup-points/{pickupPoint}     → PupilController@deletePickupPoint
PATCH  /students/{student}/pickup-points/{pickupPoint}/default → PupilController@setDefaultPickupPoint

# Student Checklist
GET    /students/{student}/checklist                      → PupilController@checklist
PATCH  /students/{student}/checklist/{checklistItem}      → PupilController@toggleChecklistItem

# Student Status
PATCH  /students/{student}/status                         → PupilController@updateStatus

# Remove Student
DELETE /students/{student}/remove                         → PupilController@removeStudent

# Auto Emergency Contact (triggered on actions page load)
POST   /students/{student}/contacts/auto-create           → PupilController@autoCreateEmergencyContact
```

### Postcode Geocoding Strategy
Use the free **postcodes.io** API (no API key needed):
```
GET https://api.postcodes.io/postcodes/{postcode}
→ Returns: latitude, longitude, admin_district, etc.
```
Create a shared action: `App\Actions\Shared\LookupPostcodeAction` that calls postcodes.io and returns lat/lng.

---

## Phase 2: Database & Models ✅

### Tasks
- [x] Create migration: `add_address_to_contacts_table`
- [x] Create migration: `add_status_fields_to_students_table`
- [x] Create migration: `create_student_pickup_points_table`
- [x] Create migration: `create_student_checklist_items_table`
- [x] Create `StudentPickupPoint` model with factory
- [x] Create `StudentChecklistItem` model with factory
- [x] Update `Contact` model — add `address` to `$fillable`
- [x] Update `Student` model — add `status`, `inactive_reason` to `$fillable`, add relationships
- [x] Update `.claude/database-schema.md` with all new tables/columns
- [ ] User to run migrations

### Reflection
- 4 migrations created covering all schema changes
- 2 new models (`StudentPickupPoint`, `StudentChecklistItem`) with proper relationships, casts, and factories
- `StudentChecklistItem::defaultItems()` static method defines the 9 default checklist items matching the wireframe
- `Contact` model updated with `address` in `$fillable` (nullable, so backward compatible)
- `Student` model updated with `status` + `inactive_reason` in `$fillable`, plus `pickupPoints()` and `checklistItems()` relationships
- `database-schema.md` updated with all new tables, columns, indexes, and relationships
- All factories include useful states (`->default()` for pickup points, `->checked()` for checklist items)

---

## Phase 3: Backend — Emergency Contacts Enhancement ✅

### Tasks
- [x] Update `PupilController@storeContact` validation to include `address`
- [x] Update `PupilController@updateContact` validation to include `address`
- [x] Create `AutoCreateEmergencyContactAction` — checks if student has `contact_*` fields, auto-creates contact if no contacts exist
- [x] Add `autoCreateEmergencyContact` endpoint to `PupilController`
- [x] Add route for auto-create
- [x] Update `EmergencyContactManager.vue` — add address field to form
- [x] Update `InstructorController` validation to include `address` (shared `Contact` model)

### Reflection
- Added `address` validation to both `PupilController` and `InstructorController` (store + update) since the `Contact` model and `EmergencyContactManager.vue` component are shared
- `AutoCreateEmergencyContactAction` has 3 guard conditions: contacts already exist, no contact_first_name, no phone number — prevents duplicate/empty auto-creation
- Auto-create defaults relationship to "Parent" since contact fields represent a third-party booker (likely a parent)
- Frontend: Added `address` to the TS interface, form data, default form state, add/edit sheets, and contact card display
- Added `MapPin` icon for address display in contact cards
- Route placed after existing contacts routes: `POST /students/{student}/contacts/auto-create`

---

## Phase 4: Backend — Pickup Points ✅

### Tasks
- [x] Create `LookupPostcodeAction` in `App\Actions\Shared` — calls postcodes.io API
- [x] Create `GetStudentPickupPointsAction`
- [x] Create `CreatePickupPointAction` — validates, geocodes postcode, saves
- [x] Create `UpdatePickupPointAction` — re-geocodes only if postcode changed
- [x] Create `DeletePickupPointAction`
- [x] Create `SetDefaultPickupPointAction` — unsets other defaults, sets new one
- [x] Create Form Requests: `StorePickupPointRequest`, `UpdatePickupPointRequest`
- [x] Add pickup point methods to `PupilController`
- [x] Add pickup point routes

### Reflection
- `LookupPostcodeAction` uses `Http::timeout(5)` with try/catch — fails gracefully (returns null) if API is unreachable
- `CreatePickupPointAction` geocodes on every create; `UpdatePickupPointAction` only re-geocodes when postcode actually changes (normalized comparison)
- Both form requests use a regex for UK postcode validation: `/^[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}$/i`
- Default management follows same pattern as contacts: unset all others before setting new default
- Controller ownership checks use `$pickupPoint->student_id !== $student->id`
- 5 routes added: GET (list), POST (create), PUT (update), DELETE, PATCH (set default)

---

## Phase 5: Backend — Student Status & Remove Student ✅

### Tasks
- [x] Create `UpdateStudentStatusAction` — updates status, saves inactive_reason, logs activity
- [x] Create `RemoveStudentFromInstructorAction` — sets instructor_id to null, logs activity
- [x] Create Form Request: `UpdateStudentStatusRequest`
- [x] Add `updateStatus` method to `PupilController`
- [x] Add `removeStudent` method to `PupilController`
- [x] Add routes for status update and remove student

### Reflection
- `UpdateStudentStatusAction` logs the status change with previous/new values + reason in activity log metadata
- `RemoveStudentFromInstructorAction` captures instructor name before nullifying, logs with instructor_name in metadata
- Controller `removeStudent` has a guard: returns 422 if student has no instructor assigned
- Status values validated via `Rule::in()`: active, inactive, on_hold, passed, failed, completed
- `inactive_reason` is nullable — only relevant when status changes to inactive/on_hold but stored for any status change

---

## Phase 6: Backend — Student Checklist ✅

### Tasks
- [x] Create `GetStudentChecklistAction` — returns checklist items, seeds defaults if empty
- [x] Create `ToggleChecklistItemAction` — toggle checked, update date and notes
- [x] Add `checklist` and `toggleChecklistItem` methods to `PupilController`
- [x] Add checklist routes
- [x] Define default checklist items constant (on model via `defaultItems()` static method)

### Reflection
- `GetStudentChecklistAction` lazy-seeds defaults: on first access, if student has 0 checklist items, it bulk-inserts the 9 default items via `StudentChecklistItem::insert()` for efficiency
- `ToggleChecklistItemAction` handles two states: checking (sets date + notes) and unchecking (clears both)
- Controller `toggleChecklistItem` validates `is_checked` (required boolean), `date` (nullable date), `notes` (nullable string max 1000)
- Ownership check: `$checklistItem->student_id !== $student->id` returns 404
- 2 routes: `GET /students/{student}/checklist`, `PATCH /students/{student}/checklist/{checklistItem}`

---

## Phase 7: Frontend — ActionsSubTab.vue ✅

### Tasks
- [x] Build `ActionsSubTab.vue` — 2-column grid layout container with student data loading + auto-create emergency contact
- [x] Build Emergency Contacts section — reuses `EmergencyContactManager` with key remount for auto-populate
- [x] Build `PickupPointsSection.vue` — list with default badge, add/edit sheet, delete dialog, set default
- [x] Build `StudentStatusSection.vue` — status dropdown, notes textarea, update button, confirmation dialog
- [x] Build `RemoveStudentSection.vue` — remove button with confirmation dialog, redirects to pupils list
- [x] Build `StudentChecklistSection.vue` — 3-column grid of checkboxes, date picker dialog on check, uncheck inline
- [x] Ensure all sections use ShadCN components (no wireframe styling)
- [x] Add loading skeletons for all sections
- [x] Add toast notifications for all actions
- [x] Add button preloaders for all async actions
- [x] Add `student_status` and `inactive_reason` to `GetStudentDetailAction` response

### Reflection
- **ActionsSubTab.vue** — Loads student data and calls auto-create in parallel via `Promise.allSettled`. If auto-create succeeds, forces EmergencyContactManager remount via key increment
- **PickupPointsSection** — Follows identical pattern to EmergencyContactManager: Card list, Sheet for add/edit, Dialog for delete, optimistic local state updates, sort by default first
- **StudentStatusSection** — Receives current status as prop, uses native `<select>` (following codebase convention), confirmation Dialog before updating
- **RemoveStudentSection** — Destructive action with confirmation Dialog, uses `router.visit('/pupils')` after removal
- **StudentChecklistSection** — Groups items by category using computed. Checking opens Dialog for date+notes (date pre-filled with today). Unchecking calls API directly. Shows date Badge and notes on checked items. Uses native checkbox with `@click.prevent` for controlled behavior
- **GetStudentDetailAction** — Added `student_status` (model field) and `inactive_reason` alongside existing `status` (booking progress) to avoid naming conflict
- All sections follow self-loading pattern with Skeleton states
- All async operations have Button preloaders (Loader2 + disabled state)
- All mutations trigger toast notifications (success and error)
- Native form elements (`<select>`, `<textarea>`, `<input type="checkbox">`, `<input type="date">`) used following codebase convention

---

## Phase 8: Review & Reflection ✅

### Tasks
- [x] Review all migrations, models, and factories
- [x] Review all actions and form requests
- [x] Review controller methods and routes
- [x] Review all Vue section components
- [x] Review ActionsSubTab layout and integration
- [x] Check edge cases and potential issues
- [x] Final reflection and summary

### Code Review Findings

**Backend — All Clean:**
- 4 migrations: clean schema design with proper FKs, indexes, cascadeOnDelete, down() methods
- 2 new models: proper $fillable, casts(), relationships, factory with useful states
- Student model: correctly updated with status/inactive_reason in $fillable, new relationships, isActive() helper
- 10 actions: single-responsibility, proper constructor injection, PHPDoc annotations
- 3 form requests: UK postcode regex, Rule::in for status, custom error messages
- PupilController: consistent ownership checks, proper HTTP status codes (201, 404, 422)
- Routes: RESTful naming convention, all within auth middleware group

**Frontend — All Clean:**
- 5 section components follow identical patterns to existing codebase (EmergencyContactManager as reference)
- Self-loading pattern with Skeleton states for all data-dependent sections
- Sheet for CRUD forms, Dialog for confirmations and checklist date entry
- Toast notifications on all success/error paths
- Button preloaders (Loader2 + disabled) on all async actions
- Native form elements (select, textarea, checkbox, date input) matching existing conventions
- Controlled checkbox behavior via `@click.prevent` for checklist (prevents visual toggle before API confirmation)

**Edge Cases Reviewed:**
- Auto-create race condition: `Promise.allSettled` + key-based remount handles timing gracefully
- Postcode geocoding failure: graceful — lat/lng stay null, pickup point still saves
- Status naming conflict: `student_status` (model field) vs `status` (booking progress) avoided collision
- Checklist date serialization: Carbon `date` cast → ISO 8601 → JS `new Date()` parses correctly
- Student data load failure: StudentStatusSection and RemoveStudentSection won't render (acceptable — page requires valid student)
- Unchecking checklist: sends `null` for date/notes, passes `nullable` validation correctly
- Boolean `false` with `required` rule: PHP validation accepts `false` as a value (not empty), works correctly

### Final Reflection

**Scope:** 5 fully functional sections built from scratch — Emergency Contacts (enhanced), Pickup Points (CRUD + geocoding), Student Status (with activity logging), Remove Student (with confirmation), Student Checklist (with date tracking). All integrated into a 2-column responsive grid layout.

**Architecture Quality:**
- Consistent Controller → Action pattern throughout
- Single-responsibility actions with constructor-injected dependencies
- Form Requests for all validation (no inline validation in controllers)
- Polymorphic Contact model reused cleanly across instructors and students
- Lazy-seeding pattern for checklist avoids separate migration data or seeders

**Files Created:** 23 new files (4 migrations, 2 models, 2 factories, 10 actions, 3 form requests, 4 Vue components)
**Files Modified:** 8 files (Student model, Contact model, PupilController, InstructorController, routes, EmergencyContactManager, GetStudentDetailAction, database-schema.md)

---

## Decisions Log
- **Emergency Contact address**: Add `address` column to existing polymorphic `contacts` table (affects both instructor and student contacts — acceptable since it's nullable)
- **Postcode geocoding**: Use free postcodes.io API (no API key needed, UK postcodes only)
- **Student checklist**: Separate table with per-student rows. Default items seeded on first access (lazy seeding). Items defined as a constant, not a separate config table.
- **Student status**: Stored as varchar on students table (not enum to allow future extension). Values: active, inactive, on_hold, passed, failed, completed.
- **Remove student**: Soft-remove by setting `instructor_id = null` (doesn't delete the student record, just detaches from instructor)
- **Layout**: 2-column grid as per wireframe, with checklist and general actions spanning full width
- **Excluded**: Transfer Student, Account Management (Send Password Reset Link)

## Files to Create
| File | Purpose |
|------|---------|
| `database/migrations/..._add_address_to_contacts_table.php` | Add address column |
| `database/migrations/..._add_status_fields_to_students_table.php` | Add status + inactive_reason |
| `database/migrations/..._create_student_pickup_points_table.php` | Pickup points table |
| `database/migrations/..._create_student_checklist_items_table.php` | Checklist items table |
| `app/Models/StudentPickupPoint.php` | Pickup point model |
| `app/Models/StudentChecklistItem.php` | Checklist item model |
| `app/Actions/Shared/LookupPostcodeAction.php` | Postcode geocoding via postcodes.io |
| `app/Actions/Student/PickupPoint/GetStudentPickupPointsAction.php` | Get all pickup points |
| `app/Actions/Student/PickupPoint/CreatePickupPointAction.php` | Create pickup point |
| `app/Actions/Student/PickupPoint/UpdatePickupPointAction.php` | Update pickup point |
| `app/Actions/Student/PickupPoint/DeletePickupPointAction.php` | Delete pickup point |
| `app/Actions/Student/PickupPoint/SetDefaultPickupPointAction.php` | Set default |
| `app/Actions/Student/Checklist/GetStudentChecklistAction.php` | Get/seed checklist |
| `app/Actions/Student/Checklist/ToggleChecklistItemAction.php` | Toggle item |
| `app/Actions/Student/Status/UpdateStudentStatusAction.php` | Update status |
| `app/Actions/Student/Status/RemoveStudentFromInstructorAction.php` | Remove from instructor |
| `app/Actions/Student/Contact/AutoCreateEmergencyContactAction.php` | Auto-create from student fields |
| `app/Http/Requests/StorePickupPointRequest.php` | Store validation |
| `app/Http/Requests/UpdatePickupPointRequest.php` | Update validation |
| `app/Http/Requests/UpdateStudentStatusRequest.php` | Status validation |
| `resources/js/components/Instructors/Tabs/Student/Actions/PickupPointsSection.vue` | Pickup points UI |
| `resources/js/components/Instructors/Tabs/Student/Actions/StudentStatusSection.vue` | Status UI |
| `resources/js/components/Instructors/Tabs/Student/Actions/RemoveStudentSection.vue` | Remove UI |
| `resources/js/components/Instructors/Tabs/Student/Actions/StudentChecklistSection.vue` | Checklist UI |

## Files to Modify
| File | Change |
|------|--------|
| `app/Models/Contact.php` | Add `address` to `$fillable` |
| `app/Models/Student.php` | Add `status`, `inactive_reason` to `$fillable`, add relationships |
| `app/Http/Controllers/PupilController.php` | Add pickup points, checklist, status, remove methods |
| `routes/web.php` | Add new routes |
| `resources/js/components/Instructors/Tabs/Student/ActionsSubTab.vue` | Complete rebuild |
| `resources/js/components/Shared/EmergencyContactManager.vue` | Add address field |
| `.claude/database-schema.md` | Document all new tables and columns |
