# Task: Pupil Detail Page - Layout & Sub-Navigation Scaffolding

**Created:** 2026-02-12
**Last Updated:** 2026-02-12
**Status:** ✅ All Phases Complete

---

## Overview

### Goal
Create the pupil detail page layout when clicking on a pupil row in ActivePupilsTab. This includes:
- Pupil header (back button, avatar, name, phone, email)
- Sub-navigation tabs (Overview, Lessons, Payments, Transfer, Emergency Contact, Messages, Actions)
- Placeholder Vue components for each sub-tab
- Working navigation between all sub-tabs
- All components live in the `Instructors/Tabs/Student/` domain

### Requirements Summary
1. Header layout matching wireframe `pupil-page.html` (structure only, ShadCN styling)
2. Back button to return to pupils list
3. Sub-navigation with 7 tabs: Overview, Lessons, Payments, Transfer, Emergency Contact, Messages, Actions
4. Placeholder content in each sub-tab
5. Working click-through from ActivePupilsTab pupil rows
6. Self-loading pattern for student data
7. All files under `resources/js/components/Instructors/Tabs/Student/`
8. Backend endpoint to fetch single student data

### Reference
- Wireframe: `wireframes/pupil-page.html` (header + tab structure)
- Tab navigation pattern: `Instructors/Show.vue` (main tabs)
- Sub-tab pattern: `Instructors/Tabs/DetailsTab.vue` (sub-tab navigation)
- Header pattern: `Instructors/InstructorHeader.vue` (avatar + info layout)
- Self-loading pattern: `ActivePupilsTab.vue` (axios + loading state)

---

## Phase 1: Planning (**CURRENT**)

**Objective:** Design the component architecture and navigation approach.

### Tasks

#### Architecture Design
- [x] Analyse wireframe `pupil-page.html` for layout structure
- [x] Analyse existing tab/sub-tab navigation pattern
- [x] Plan component file structure
- [x] Plan backend endpoint for student data
- [x] Plan navigation flow (pupil click -> student tab -> sub-tabs)

#### Component Plan

**Navigation Flow:**
1. User clicks pupil row in `ActivePupilsTab`
2. `router.visit()` to instructor show page with `tab=student&student={id}&subtab=overview`
3. `Show.vue` renders `StudentTab.vue` when `tab === 'student'`
4. `StudentTab.vue` self-loads student data, shows header + sub-navigation
5. Sub-tabs render based on `subtab` query parameter

**File Structure:**
```
resources/js/components/Instructors/Tabs/
├── StudentTab.vue (header + sub-nav wrapper, self-loads student data)
└── Student/
    ├── OverviewSubTab.vue (placeholder)
    ├── LessonsSubTab.vue (placeholder)
    ├── PaymentsSubTab.vue (placeholder)
    ├── TransferSubTab.vue (placeholder)
    ├── EmergencyContactSubTab.vue (placeholder)
    ├── MessagesSubTab.vue (placeholder)
    └── ActionsSubTab.vue (placeholder)
```

**Backend:**
- New route: `GET /students/{student}` -> `PupilController@show` (returns student detail JSON)
- New action: `GetStudentDetailAction` (fetches student with user data)
- New service method: `PupilService::getStudentDetail()`

**Show.vue Changes:**
- Add `student` prop from query params
- Add `'student'` to TabType union
- Conditionally render `StudentTab` (NOT in main tab navigation bar - accessed only via pupil click)
- Pass `student` ID and `subtab` to `StudentTab`

**ActivePupilsTab.vue Changes:**
- Add `@click` on pupil TableRow to navigate to student tab

### Reflection
**What went well:** Clear architecture following established patterns
**What could be improved:** N/A
**Blockers:** None

---

## Phase 2: Backend Implementation ✅

**Objective:** Create the endpoint to fetch single student data for the header.

### Tasks

#### Route & Controller
- [x] Add `GET /students/{student}` route to `routes/web.php`
- [x] Add `show()` method to `PupilController`

#### Action
- [x] Create `App\Actions\Student\GetStudentDetailAction`
  - Load student with `user`, `instructor`, `orders.package`, `orders.lessons` relations
  - Return formatted data: id, name, first_name, surname, email, phone, user_id, status, instructor_id, has_app, lessons stats, revenue

#### Service
- [x] Skipped separate service layer - action called directly from controller (follows existing PupilController pattern)

---

## Phase 3: Frontend Implementation ✅

**Objective:** Create all Vue components with working navigation.

### Tasks

#### StudentTab.vue (Main Wrapper)
- [x] Create `StudentTab.vue` at `Instructors/Tabs/`
- [x] Props: `instructor`, `studentId`, `subtab`
- [x] Self-loading: fetch student data via axios `GET /students/{studentId}`
- [x] Back button: navigate to `?tab=active-pupils`
- [x] Header: Avatar + Name + Phone + Email + Status Badge (matching wireframe structure)
- [x] Sub-navigation: 7 tabs following DetailsTab pattern
- [x] Loading skeleton for header
- [x] Sub-tab content rendering via `v-if`
- [x] "Add Note" button in header (matching wireframe)

#### Placeholder Sub-Tab Components (7 files)
- [x] `Student/OverviewSubTab.vue` - placeholder with LayoutDashboard icon
- [x] `Student/LessonsSubTab.vue` - placeholder with BookOpen icon
- [x] `Student/PaymentsSubTab.vue` - placeholder with CreditCard icon
- [x] `Student/TransferSubTab.vue` - placeholder with ArrowRightLeft icon
- [x] `Student/EmergencyContactSubTab.vue` - placeholder with ShieldAlert icon
- [x] `Student/MessagesSubTab.vue` - placeholder with MessageSquare icon
- [x] `Student/ActionsSubTab.vue` - placeholder with Settings icon

#### Show.vue Updates
- [x] Import `StudentTab`
- [x] Add `student` to Props interface (optional number)
- [x] Add `'student'` to TabType
- [x] Hide main tab bar when viewing student (`activeTab !== 'student'`)
- [x] Add conditional render for `StudentTab` (NOT in tab bar buttons)
- [x] Pass `studentId` and `subtab` to `StudentTab`

#### ActivePupilsTab.vue Updates
- [x] Import `router` from `@inertiajs/vue3`
- [x] Add `viewPupil()` function using `router.visit()`
- [x] Add `@click="viewPupil(pupil.id)"` on pupil `TableRow`

#### InstructorController Update
- [x] Pass `student` query param (cast to int) to Inertia render

### Reflection
**What went well:** Clean implementation following DetailsTab sub-tab pattern. All ShadCN components used correctly. Self-loading with skeleton state for header.
**What could be improved:** "Add Note" button is scaffolded but not functional yet (future task).
**Blockers:** None

---

## Phase 4: Review & Documentation ✅

**Objective:** Final review of navigation and component structure.

### Tasks
- [x] Verify pupil row click navigates to student tab (ActivePupilsTab.vue:329 @click -> viewPupil -> router.visit)
- [x] Verify back button returns to pupils list (StudentTab.vue:76-82 goBack -> tab: 'active-pupils')
- [x] Verify all 7 sub-tabs render and navigate correctly (StudentTab.vue:52-59 + v-if at 211-238)
- [x] Verify ShadCN components used throughout - Card, Button, Avatar, Badge, Skeleton, toast - no custom styling
- [x] Verify self-loading pattern with skeleton states (StudentTab.vue:93-104 axios + loading + skeleton)
- [x] Verify main tab bar hidden when viewing student (Show.vue:71)
- [x] Verify student query param passed from backend (InstructorController cast to int)

### Reflection
**What went well:** All wiring verified. Pattern is consistent with DetailsTab sub-tabs. ShadCN-only components. Self-loading with proper error handling.
**What could be improved:** Future tasks will implement actual content in each sub-tab.
**Blockers:** None

---

## Technical Architecture

### Frontend
```
resources/js/
├── pages/Instructors/
│   └── Show.vue (UPDATE - add student tab handling)
└── components/Instructors/Tabs/
    ├── ActivePupilsTab.vue (UPDATE - add row click)
    ├── StudentTab.vue (NEW - header + sub-nav wrapper)
    └── Student/ (NEW directory)
        ├── OverviewSubTab.vue (NEW - placeholder)
        ├── LessonsSubTab.vue (NEW - placeholder)
        ├── PaymentsSubTab.vue (NEW - placeholder)
        ├── TransferSubTab.vue (NEW - placeholder)
        ├── EmergencyContactSubTab.vue (NEW - placeholder)
        ├── MessagesSubTab.vue (NEW - placeholder)
        └── ActionsSubTab.vue (NEW - placeholder)
```

### Backend
```
app/
├── Actions/Student/
│   └── GetStudentDetailAction.php (NEW)
├── Http/Controllers/
│   └── PupilController.php (UPDATE - add show method)
routes/web.php (UPDATE - add GET /students/{student})
```

---

## Progress Summary

### Completion Status
- **Phase 1:** ✅ Complete (Planning)
- **Phase 2:** ✅ Complete (Backend)
- **Phase 3:** ✅ Complete (Frontend)
- **Phase 4:** ✅ Complete (Review)

### Currently Working On
- Phase 1: Planning complete, awaiting approval

### Next Steps
1. Get approval for Phase 1 plan
2. Implement backend (Phase 2)
3. Implement frontend (Phase 3)
4. Review (Phase 4)
