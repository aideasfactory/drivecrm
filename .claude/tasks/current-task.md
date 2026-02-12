# Task: Pupil Pages - ActivePupilsTab & Broadcast Messaging

**Created:** 2026-02-12
**Last Updated:** 2026-02-12
**Status:** 🔄 Phase 1 - Planning

---

## Overview

### Goal
Implement the pupils list within the ActivePupilsTab.vue (instructor show page) matching the wireframe layout, with:
- Table of all students belonging to the instructor (self-loading pattern)
- Search functionality (by name, email, phone)
- "Add Pupil" button (scaffolded, placeholder for now)
- "Broadcast Message" button with Sheet form
- New `messages` table (migration + model) for storing broadcast messages
- Activity log entry when broadcast message is sent
- All required backend: controllers, services, actions, routes

### Requirements Summary
1. Redesign `ActivePupilsTab.vue` matching wireframe `pupil-list.html` layout
2. Self-loading pattern: fetch instructor's students via axios
3. ShadCN Table with columns: Name, Lessons, Next Lesson, Package Time, Revenue, App, Status
4. Search input filtering students by name/email/phone
5. "Add Pupil" button (scaffold only - opens empty sheet)
6. "Broadcast Message" button -> Sheet form -> saves to `messages` table
7. `messages` table: id, message (text), to (user_id FK), from (user_id FK), soft_deletes, timestamps
8. Log activity using `LogActivityAction` when broadcast message is sent
9. Backend: Action(s), Service method(s), Controller endpoint(s), Route(s)

### Reference
- Wireframe: `wireframes/pupil-list.html` (table structure and controls)
- Pattern reference: `Instructors/Index.vue` (search + button + table layout)
- Self-loading pattern: `EmergencyContactSubTab.vue` / `ActivitySubTab.vue`
- ShadCN components for ALL UI elements
- Controller -> Service -> Action pattern
- Axios for self-loading tab data

---

## Phase 1: Planning (**CURRENT**) ✅

**Objective:** Design the database schema, endpoints, and implementation approach.

### Tasks

#### Database Design
- [x] Design `messages` table structure
  - `id` (bigint unsigned, PK, AUTO_INCREMENT)
  - `from` (bigint unsigned, FK -> users.id, NOT NULL) - sender user ID
  - `to` (bigint unsigned, FK -> users.id, NOT NULL) - recipient user ID
  - `message` (text, NOT NULL) - message content
  - `deleted_at` (timestamp, NULLABLE) - soft delete
  - `created_at` / `updated_at` (timestamps)

#### Backend Design
- [x] Plan Action: `App\Actions\Shared\Message\SendBroadcastMessageAction`
  - Parameters: User $from, array $recipientUserIds, string $message
  - Creates a message record for each recipient
  - Logs activity for the instructor
  - Returns Collection of created messages
- [x] Plan Action: `App\Actions\Instructor\GetInstructorPupilsAction`
  - Parameters: Instructor $instructor, ?string $search
  - Returns students with user data, order stats, lesson counts
- [x] Plan Service method: `InstructorService::getPupils()`
- [x] Plan Service method: `InstructorService::broadcastMessage()`
- [x] Plan Controller methods on `InstructorController`:
  - `pupils(Instructor)` - GET instructor's students list
  - `broadcastMessage(Request, Instructor)` - POST send broadcast message
- [x] Plan Routes:
  - `GET /instructors/{instructor}/pupils` -> `InstructorController@pupils`
  - `POST /instructors/{instructor}/broadcast-message` -> `InstructorController@broadcastMessage`

#### Frontend Design
- [x] ActivePupilsTab.vue layout:
  - Search input (left) + Add Pupil button + Broadcast Message button (right)
  - ShadCN Table with columns from wireframe
  - Self-loading pattern (axios GET on mount)
  - Search filters client-side on loaded data
  - Loading skeleton state
  - Empty state when no pupils
- [x] Broadcast Message Sheet:
  - Sheet from right side
  - Textarea for message
  - Info text showing "Message will be sent to X pupils"
  - Submit button with loading state
  - Toast on success
- [x] Add Pupil Sheet (scaffold only):
  - Empty Sheet with "Coming soon" placeholder

### Reflection
**What went well:** Comprehensive analysis of existing patterns ensures consistency
**What could be improved:** N/A
**Blockers:** None

---

## Phase 2: Backend Implementation ✅

**Objective:** Create messages migration, model, actions, service methods, controller endpoints, and routes.

### Tasks

#### Database Migration
- [x] Create migration: `create_messages_table`
  - `id` bigint unsigned PK
  - `from` bigint unsigned FK (users.id) NOT NULL
  - `to` bigint unsigned FK (users.id) NOT NULL
  - `message` text NOT NULL
  - `deleted_at` timestamp NULLABLE (soft deletes)
  - `created_at` / `updated_at` timestamps
  - Index on `from`
  - Index on `to`
- [x] **IMMEDIATELY update `.claude/database-schema.md`**

#### Model Creation
- [x] Create `App\Models\Message` model
  - `$fillable`: from, to, message
  - SoftDeletes trait
  - `sender()` belongsTo User (foreignKey: 'from')
  - `recipient()` belongsTo User (foreignKey: 'to')
  - `$casts` array

#### Action Creation
- [x] Create `App\Actions\Instructor\GetInstructorPupilsAction`
  - Query students where instructor_id = instructor.id
  - Eager load `user`, `orders.package`, `orders.lessons`
  - Optional search parameter (filter by name, email, phone)
  - Return formatted collection with: id, name, email, lessons_completed, lessons_total, next_lesson, revenue, status
- [x] Create `App\Actions\Shared\Message\SendBroadcastMessageAction`
  - Parameters: User $sender, array $recipientUserIds, string $message
  - Create Message record for each recipient
  - Return Collection of created Messages

#### Service Methods
- [x] Add to `InstructorService`:
  - Inject `GetInstructorPupilsAction` in constructor
  - `getPupils(Instructor $instructor, ?string $search): Collection`
  - `broadcastMessage(Instructor $instructor, string $message): Collection`
    - Gets all students for instructor
    - Calls `SendBroadcastMessageAction` with student user IDs
    - Calls `LogActivityAction` with category 'message'
    - Returns created messages

#### Controller Methods
- [x] Add to `InstructorController`:
  - `pupils(Instructor $instructor): JsonResponse` - GET list students
  - `broadcastMessage(Instructor $instructor): JsonResponse` - POST send broadcast

#### Routes
- [x] Add routes to `routes/web.php`:
  - `GET /instructors/{instructor}/pupils`
  - `POST /instructors/{instructor}/broadcast-message`

### Reflection
**What went well:** Clean separation of concerns following Controller -> Service -> Action pattern. Activity logging integrated in service layer.
**What could be improved:** Could add FormRequest classes for validation instead of inline validation
**Blockers:** None

---

## Phase 3: Frontend Implementation ✅

**Objective:** Implement ActivePupilsTab.vue matching wireframe layout with ShadCN components.

### Tasks

#### TypeScript Types
- [x] Create pupil types in `resources/js/types/pupil.ts`
  - `Pupil` interface: id, name, email, phone, lessons_completed, lessons_total, next_lesson_date, next_lesson_time, revenue_pence, has_app, status

#### ActivePupilsTab.vue Redesign
- [x] Remove placeholder content
- [x] Implement self-loading pattern (axios GET `/instructors/{id}/pupils`)
- [x] Search input with Search icon (left side)
- [x] "Add Pupil" Button with Plus icon (right side)
- [x] "Broadcast Message" Button with Megaphone icon (right side)
- [x] ShadCN Table with columns:
  - Name (Avatar + name + email)
  - Lessons (completed/total)
  - Next Lesson (date + time with smart formatting)
  - Revenue (formatted from pence)
  - App (Check/X icon)
  - Status (Badge with variant)
- [x] Client-side search filtering (name, email, phone)
- [x] Loading skeleton state (5 rows)
- [x] Empty state with Users icon (search vs no data)

#### Broadcast Message Sheet
- [x] Sheet component (slides from right, max-w-md)
- [x] SheetTitle with Megaphone icon
- [x] Textarea for message content with validation
- [x] Info text: "This message will be sent to X pupils"
- [x] Submit button with Loader2 spinner + min-width
- [x] Axios POST to `/instructors/{id}/broadcast-message`
- [x] Toast notification on success (with recipient count) / error
- [x] Close sheet and reset form on success

#### Add Pupil Sheet (Scaffold)
- [x] Sheet with "Add Pupil" title + UserPlus icon
- [x] Placeholder content "Coming Soon"

#### Styling Verification
- [x] NO custom colors (ShadCN defaults only)
- [x] Layout matches wireframe structure
- [x] All icons from lucide-vue-next
- [x] Button preloaders on async actions (broadcast send)
- [x] Toast notifications on all API calls (load error, broadcast success/error)

### Reflection
**What went well:** Clean implementation following all existing patterns (self-loading, Sheet forms, toast feedback). Consistent with Instructors/Index.vue table layout.
**What could be improved:** Could add row click navigation to pupil detail page when it exists
**Blockers:** None

---

## Phase 4: Review & Documentation ✅

**Objective:** Final review and documentation updates.

### Tasks
- [x] Verify database-schema.md is fully updated (messages table #16, indexes, relationships)
- [x] Verify pupils endpoint wired: Route -> Controller -> Service -> Action
- [x] Verify broadcast endpoint wired: Route -> Controller -> Service -> Action + LogActivityAction
- [x] Verify activity log is created on broadcast (via InstructorService.broadcastMessage)
- [x] Verify search filtering works (client-side in Vue + server-side in Action)
- [x] Review code for pattern adherence (Controller -> Service -> Action)
- [x] Summary of changes and score

### Reflection
**What went well:** Full end-to-end implementation with clean pattern adherence. All ShadCN components used correctly. Self-loading pattern consistent with existing tabs.
**What could be improved:** Could extract FormRequest for broadcast validation. Row click navigation to pupil detail page when that page exists.
**Blockers:** None

---

## Database Schema Preview

### messages Table (To Be Created)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique message identifier |
| `from` | bigint unsigned | FK -> users.id, NOT NULL | Sender user ID |
| `to` | bigint unsigned | FK -> users.id, NOT NULL | Recipient user ID |
| `message` | text | NOT NULL | Message content |
| `deleted_at` | timestamp | NULLABLE | Soft delete timestamp |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `from`
- Index on `to`

**Relationships:**
- `from` belongs to `User` (sender)
- `to` belongs to `User` (recipient)

---

## Technical Architecture

### Backend
```
app/
├── Models/
│   └── Message.php (NEW)
├── Actions/
│   ├── Instructor/
│   │   └── GetInstructorPupilsAction.php (NEW)
│   └── Shared/
│       └── Message/
│           └── SendBroadcastMessageAction.php (NEW)
├── Services/
│   └── InstructorService.php (UPDATE - add getPupils, broadcastMessage)
├── Http/
│   └── Controllers/
│       └── InstructorController.php (UPDATE - add pupils, broadcastMessage)

routes/web.php (UPDATE - add 2 new routes)
database/migrations/xxxx_create_messages_table.php (NEW)
```

### Frontend
```
resources/js/
├── types/
│   └── pupil.ts (NEW)
└── components/
    └── Instructors/
        └── Tabs/
            └── ActivePupilsTab.vue (REDESIGN)
```

---

## Progress Summary

### Completion Status
- **Phase 1:** ✅ Complete (Planning)
- **Phase 2:** ✅ Complete (Backend)
- **Phase 3:** ✅ Complete (Frontend)
- **Phase 4:** ✅ Complete (Review)

### Currently Working On
- All phases complete

### Next Steps
1. Get approval for Phase 1 plan
2. Implement backend (Phase 2)
3. Implement frontend (Phase 3)
4. Review and document (Phase 4)
