# Task: Emergency Contacts System (Morphable for Instructors & Students)

**Created:** 2026-02-12
**Last Updated:** 2026-02-12
**Status:** 🔄 Phase 1 - Planning

---

## Overview

### Goal
Implement a polymorphic emergency contacts system that allows storing emergency contact information for both instructors and students, with:
- Morphable `contacts` table (works for both Instructors and Students)
- Primary contact designation (only one primary per entity)
- Full CRUD operations (add, edit, delete, set as primary)
- Frontend UI matching wireframe layout (emergency contact section only)
- Sheet-based forms for add/edit using ShadCN components

### Requirements Summary
1. Polymorphic `contacts` table with emergency contact fields
2. Contact model with `is_primary` column
3. Migration with proper indexes
4. Update Instructor and Student models with `contacts()` morphMany relationship
5. CRUD Actions in `App\Actions\Shared\Contact\` folder
6. Controller methods on InstructorController and PupilController
7. API routes for contacts CRUD + set-primary
8. Frontend EmergencyContactSubTab.vue (self-loading pattern)
9. Add/Edit Sheet form with ShadCN components
10. Delete confirmation Dialog

### Reference
- Wireframe: `wireframes/instructor-Emergency-contact.html` (emergency section only, lines 264-376)
- Use ShadCN components for ALL UI elements
- Follow Controller -> Service -> Action pattern
- Follow self-loading sub-tab pattern (like CoverageSubTab / ActivitySubTab)

---

## Phase 1: Planning (**CURRENT**)

**Objective:** Design the database schema, model relationships, and implementation approach.

### Tasks

#### Database Design
- [ ] Design `contacts` table structure
  - [ ] Polymorphic columns (contactable_type, contactable_id)
  - [ ] name (string) - Full name of emergency contact
  - [ ] relationship (string) - Relationship type (Spouse, Parent, Child, Sibling, Friend, Doctor, Other)
  - [ ] phone (string) - Phone number
  - [ ] email (string, nullable) - Email address
  - [ ] is_primary (boolean, default false) - Primary contact flag
  - [ ] Timestamps
- [ ] Plan indexes for performance (contactable composite, is_primary)

#### Model Design
- [ ] Plan Contact model structure
  - [ ] Morphable relationship setup (`contactable` morphTo)
  - [ ] `$fillable` array
  - [ ] `$casts` array (is_primary as boolean)
  - [ ] Scopes: `primary()`, `forEntity()`
- [ ] Plan Instructor model updates (add `contacts()` morphMany)
- [ ] Plan Student model updates (add `contacts()` morphMany)

#### Action Design
- [ ] Design CRUD Actions in `App\Actions\Shared\Contact\`
  - [ ] `CreateContactAction` - Create new contact, handle primary logic
  - [ ] `UpdateContactAction` - Update contact details
  - [ ] `DeleteContactAction` - Delete a contact
  - [ ] `SetPrimaryContactAction` - Set a contact as primary (unset others)

#### Controller & Route Design
- [ ] Plan InstructorController methods:
  - [ ] `contacts(Instructor)` - GET list all contacts
  - [ ] `storeContact(Request, Instructor)` - POST create contact
  - [ ] `updateContact(Request, Instructor, Contact)` - PUT update contact
  - [ ] `deleteContact(Instructor, Contact)` - DELETE remove contact
  - [ ] `setPrimaryContact(Instructor, Contact)` - PATCH set as primary
- [ ] Plan PupilController methods (same pattern for Student)
- [ ] Plan routes for both instructor and student contacts

#### Frontend Design
- [ ] Analyze wireframe emergency section (lines 264-376)
- [ ] Layout structure:
  - [ ] Header: title + "Add Contact" button (flex, justify-between)
  - [ ] Contact cards list (space-y-4, max-h scrollable)
  - [ ] Each card: name + primary badge/button, relationship, phone, email, edit/delete buttons
- [ ] Plan ShadCN components:
  - [ ] Card for each contact
  - [ ] Badge for "Primary" indicator
  - [ ] Button for "Set as Primary", "Add Contact", edit, delete
  - [ ] Sheet for add/edit form (slides from right)
  - [ ] Dialog for delete confirmation
  - [ ] Input, Label, Select for form fields
  - [ ] Skeleton for loading states
- [ ] Plan Vue component structure:
  - [ ] `EmergencyContactSubTab.vue` - Main self-loading component
  - [ ] Inline Sheet for add/edit (like CoverageSubTab pattern)
  - [ ] Inline Dialog for delete confirmation

#### Documentation Plan
- [ ] Plan database-schema.md updates for contacts table
- [ ] Update relationship diagrams

### Reflection
**What went well:** (To be filled after phase completion)
**What could be improved:** (To be filled after phase completion)
**Blockers:** None currently

---

## Phase 2: Backend Implementation

**Objective:** Create database migration, model, actions, controller methods, and routes.

### Tasks

#### Database Migration
- [ ] Create migration: `create_contacts_table`
- [ ] Add polymorphic columns (contactable_type, contactable_id)
- [ ] Add name (string), relationship (string), phone (string), email (string nullable)
- [ ] Add is_primary (boolean, default false)
- [ ] Add timestamps
- [ ] Add composite index on (contactable_type, contactable_id)
- [ ] **IMMEDIATELY update `.claude/database-schema.md`**

#### Model Creation
- [ ] Create `App\Models\Contact` model
  - [ ] Define `contactable()` morphTo relationship
  - [ ] Add `$fillable` array
  - [ ] Add `$casts` array
  - [ ] Add `scopePrimary()` scope
- [ ] Update `App\Models\Instructor`
  - [ ] Add `contacts()` morphMany relationship
- [ ] Update `App\Models\Student`
  - [ ] Add `contacts()` morphMany relationship

#### Action Creation
- [ ] Create `App\Actions\Shared\Contact\CreateContactAction`
  - [ ] Parameters: Model $contactable, array $data
  - [ ] Handle primary logic (if is_primary, unset others first)
  - [ ] Return Contact
- [ ] Create `App\Actions\Shared\Contact\UpdateContactAction`
  - [ ] Parameters: Contact $contact, array $data
  - [ ] Handle primary logic
  - [ ] Return Contact
- [ ] Create `App\Actions\Shared\Contact\DeleteContactAction`
  - [ ] Parameters: Contact $contact
  - [ ] Return void
- [ ] Create `App\Actions\Shared\Contact\SetPrimaryContactAction`
  - [ ] Parameters: Contact $contact
  - [ ] Unset all other primary contacts for the same entity
  - [ ] Set this contact as primary
  - [ ] Return Contact

#### Controller Methods
- [ ] Add to `InstructorController`:
  - [ ] `contacts(Instructor)` - List contacts
  - [ ] `storeContact(Request, Instructor)` - Create contact
  - [ ] `updateContact(Request, Instructor, Contact)` - Update contact
  - [ ] `deleteContact(Instructor, Contact)` - Delete contact
  - [ ] `setPrimaryContact(Instructor, Contact)` - Set primary
- [ ] Add to `PupilController`:
  - [ ] Same 5 methods for Student

#### Routes
- [ ] Add instructor contact routes
- [ ] Add student contact routes

### Reflection
**What went well:** (To be filled after phase completion)
**What could be improved:** (To be filled after phase completion)
**Blockers:** None currently

---

## Phase 3: Frontend Implementation

**Objective:** Create Vue component matching wireframe layout using ShadCN components.

### Tasks

#### Component Creation
- [ ] Implement `EmergencyContactSubTab.vue`
  - [ ] Self-loading pattern (fetch contacts in onMounted)
  - [ ] Loading skeleton state
  - [ ] Header with title + "Add Contact" button
  - [ ] Contact cards list with scroll
  - [ ] Each card: name, primary badge/button, relationship, phone, email
  - [ ] Edit and delete action buttons per card
  - [ ] Empty state when no contacts
- [ ] Implement Add/Edit Sheet form
  - [ ] Sheet from right side
  - [ ] Form fields: name, relationship (Select), phone, email
  - [ ] Primary contact checkbox
  - [ ] Submit button with loading state
  - [ ] Validation error display
- [ ] Implement Delete confirmation Dialog
  - [ ] Confirmation message
  - [ ] Cancel / Confirm buttons

#### Integration
- [ ] Verify EmergencyContactSubTab is wired into DetailsTab.vue
- [ ] Pass `instructor` prop
- [ ] Test self-loading data flow

#### Styling Verification
- [ ] NO custom colors used (ShadCN defaults only)
- [ ] Layout matches wireframe structure
- [ ] All icons from lucide-vue-next
- [ ] Responsive design

### Reflection
**What went well:** (To be filled after phase completion)
**What could be improved:** (To be filled after phase completion)
**Blockers:** None currently

---

## Phase 4: Review & Documentation

**Objective:** Final review and documentation updates.

### Tasks
- [ ] Verify database-schema.md is fully updated
- [ ] Verify all CRUD operations work end-to-end
- [ ] Verify primary contact logic (only one primary per entity)
- [ ] Review code for pattern adherence
- [ ] Summary of changes and score

### Reflection
**What went well:** (To be filled after phase completion)
**What could be improved:** (To be filled after phase completion)
**Blockers:** None currently

---

## Database Schema Preview

### contacts Table (To Be Created)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique contact identifier |
| `contactable_type` | varchar(255) | NOT NULL | Model type (Instructor/Student) |
| `contactable_id` | bigint unsigned | NOT NULL | Model ID |
| `name` | varchar(255) | NOT NULL | Emergency contact full name |
| `relationship` | varchar(100) | NOT NULL | Relationship to the person |
| `phone` | varchar(50) | NOT NULL | Phone number |
| `email` | varchar(255) | NULLABLE | Email address |
| `is_primary` | boolean | DEFAULT false | Whether this is the primary contact |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(contactable_type, contactable_id)`

**Relationships:**
- Morphable to `Instructor` or `Student`

**Relationship Values:**
- Spouse, Parent, Child, Sibling, Friend, Doctor, Other

---

## Technical Architecture

### Backend
```
app/
├── Models/
│   ├── Contact.php (NEW)
│   ├── Instructor.php (UPDATE - add contacts relationship)
│   └── Student.php (UPDATE - add contacts relationship)
├── Actions/
│   └── Shared/
│       └── Contact/
│           ├── CreateContactAction.php (NEW)
│           ├── UpdateContactAction.php (NEW)
│           ├── DeleteContactAction.php (NEW)
│           └── SetPrimaryContactAction.php (NEW)

routes/web.php (UPDATE - add contact routes)
```

### Frontend
```
resources/js/
└── components/
    └── Instructors/
        └── Tabs/
            └── Details/
                └── EmergencyContactSubTab.vue (UPDATE - implement)
```

---

## Progress Summary

### Completion Status
- **Phase 1:** 🔄 In Progress (Planning)
- **Phase 2:** Not Started
- **Phase 3:** Not Started
- **Phase 4:** Not Started

### Currently Working On
- Phase 1: Planning the implementation

### Next Steps
1. Get approval for Phase 1 plan
2. Implement backend (Phase 2)
3. Implement frontend (Phase 3)
4. Review and document (Phase 4)
