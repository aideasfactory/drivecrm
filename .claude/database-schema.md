# Database Schema Documentation

This document provides a comprehensive overview of the database structure for the Stripe Payment Learning Management System.

## 🔍 Quick Reference

**Core Models:**
- `Team` → Organizational unit (e.g., "Drive"). Users belong to a team via `current_team_id`
- `User` → `Instructor` or `Student` (polymorphic via `role`), belongs to `Team`
- `Instructor` → Creates `Packages`, Teaches `Lessons`, Receives `Payouts`
- `Student` → Purchases `Orders` → Contains `Lessons`
- `Order` = Student + Instructor + Package
- `ResourceFolder` → Nested folders (self-referencing) → Contains `Resource` items (videos, PDFs)
- `StudentPickupPoint` → Pickup/drop-off locations per student (geocoded)
- `StudentChecklistItem` → Progress tracking items per student (theory test, practical test, etc.)

**Key Relationships:**
```
Team (1) → (Many) Users

User (instructor) → Instructor → Packages
                              → Orders (assigned)
                              → Lessons (teaches)
                              → Payouts (receives)

User (student) → Student → Orders → Lessons → LessonPayments
                                            → Payouts
```

**Payment Modes:**
- `upfront`: Single Stripe charge → all lessons unlocked
- `weekly`: Stripe Subscription → lessons unlock per payment

## Overview

This is a Laravel-based application for managing instructor-student relationships, lesson packages, orders, and payments integrated with Stripe. Phase 1 of the onboarding flow adds new tables and fields to support a multi-step enquiry process for learners.

## Entity Relationship Diagram (Text)

```
Teams (1) ──── (Many) Users
                       │
Users (1) ──┬── (1) Instructors ──┬── (Many) Packages
            │                     │
            │                     ├── (Many) Orders
            │                     │
            │                     ├── (Many) Lessons ────┐
            │                     │                      │
            │                     ├── (Many) Payouts     │
            │                     │                      │
            │                     ├── (Many) Calendars ──┼── (Many) CalendarItems
            │                     │                      │
            │                     └── (Many) Contacts    │
            │                                            │
            └── (1) Students ──── (Many) Orders ──── (Many) Lessons ──┬── (1) LessonPayments
                      │                  │              │              │
                      │                  │              └──────────────┤
                      │                  │                             └── (1) Payouts
                      │                  │
                      ├── (Many) Contacts└── (1) Packages
                      │
                      └─────────────┐
                                    │
                          Instructors (Many)
```

---

## Key Tables & Relationships

### Core Entities

1. **teams** - Organizational teams
   - Users belong to a team via `current_team_id`
   - Has JSON `settings` column for team-specific configuration (lesson defaults, permissions, etc.)
   - Default team is "Drive" (id=1)

2. **users** - Central user table
   - Polymorphic based on `role` enum: `owner`, `instructor`, `student`
   - Has one-to-one relationship with either `instructors` or `students` table
   - Belongs to a `team` via `current_team_id` (nullable foreign key)

2. **instructors** - Instructor profiles
   - One-to-one with `users` (via `user_id`)
   - One-to-many with `packages` (creates bespoke packages)
   - One-to-many with `orders` (assigned to student orders)
   - One-to-many with `lessons` (conducts lessons)
   - One-to-many with `payouts` (receives payments)

3. **students** - Student profiles
   - One-to-one with `users` (via `user_id`)
   - Many-to-one with `instructors` (assigned instructor - nullable)
   - One-to-many with `orders` (purchases packages)

4. **packages** - Lesson packages (platform or instructor-created)
   - Many-to-one with `instructors` (nullable - null = platform package)
   - One-to-many with `orders` (purchased by students)

5. **orders** - Student enrollments
   - Many-to-one with `students` (who purchased)
   - Many-to-one with `instructors` (who teaches)
   - Many-to-one with `packages` (what was purchased)
   - One-to-many with `lessons` (lessons in the order)

6. **lessons** - Individual lessons
   - Many-to-one with `orders` (parent order)
   - Many-to-one with `instructors` (who conducts)
   - Many-to-one with `calendar_items` (scheduled time slot)
   - One-to-one with `lesson_payments` (payment tracking for weekly mode)
   - One-to-one with `payouts` (instructor payout)
   - One-to-one with `reflective_logs` (student's reflective log)
   - Many-to-many with `resources` (via `lesson_resource` pivot)

7. **lesson_payments** - Payment tracking (weekly mode)
   - One-to-one with `lessons` (payment for specific lesson)

8. **payouts** - Instructor payments
   - One-to-one with `lessons` (payout for completed lesson)
   - Many-to-one with `instructors` (recipient)

9. **activity_logs** - Activity tracking (polymorphic)
   - Morphs to `instructors` or `students`
   - Tracks all significant activities with categories

10. **instructor_finances** - Instructor payments and expenses
    - Many-to-one with `instructors` (via `instructor_id`)
    - Tracks payments received and expenses incurred
    - Supports recurring entries (weekly, monthly, yearly)

### Support Tables

10. **webhook_events** - Stripe webhook logging
11. **password_reset_tokens** - Laravel password resets
12. **sessions** - Laravel session storage
13. **cache** - Laravel cache
14. **jobs** - Laravel queue jobs

### Relationship Summary

```
Team → Has many Users (via current_team_id)

User (role=instructor) → Instructor → Creates Packages
                                   → Assigned to Orders
                                   → Conducts Lessons
                                   → Receives Payouts
                                   → Has ActivityLogs (morphMany)
                                   → Has Contacts (morphMany)
                                   → Has Notes (morphMany)

User (role=student) → Student → Purchases Orders → Contains Lessons → Has LessonPayments
                             → Assigned to Instructor                → Has Payouts
                             → Has ActivityLogs (morphMany)
                             → Has Contacts (morphMany)
                             → Has Notes (morphMany)
                             → Has StudentPickupPoints (hasMany)
                             → Has StudentChecklistItems (hasMany)

Package → Used in Orders

Order = Student + Instructor + Package → Creates Lessons

ActivityLog → Morphs to Instructor or Student
Contact → Morphs to Instructor or Student
Note → Morphs to Instructor or Student

Message → Belongs to User (sender via 'from') + Belongs to User (recipient via 'to')

ResourceFolder → Self-referencing (parent/children) → Has many Resources
Resource → Belongs to ResourceFolder (videos, PDFs stored on S3)
       → Many-to-many with Lessons (via lesson_resource pivot)

ReflectiveLog → Belongs to Lesson (one-to-one, unique on lesson_id)
LessonResource (pivot) → Lesson + Resource (many-to-many)
```

---

## Tables

### 0. **teams**

Organizational teams for grouping users. The default team is "Drive" (id=1). The `settings` JSON column stores team-specific configuration such as default lesson duration, permissions, and rules.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique team identifier |
| `uuid` | char(36) | NOT NULL, UNIQUE | UUID for external references |
| `name` | varchar(255) | NOT NULL | Team name (e.g., "Drive") |
| `settings` | json | NULLABLE | Team settings (lesson defaults, permissions, rules) |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Relationships:**
- Has many `Users` (via `users.current_team_id`)

**Seeded Data:**
- id=1: "Drive" (default team for all new registrations)

---

### 1. **users**

Core user table storing all users in the system (owners, instructors, and students).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique user identifier |
| `name` | varchar(255) | NOT NULL | User's full name |
| `email` | varchar(255) | NOT NULL, UNIQUE | User's email address |
| `email_verified_at` | timestamp | NULLABLE | Email verification timestamp |
| `password` | varchar(255) | NOT NULL | Hashed password |
| `role` | enum('owner', 'instructor', 'student') | DEFAULT 'student' | User role in the system |
| `stripe_customer_id` | varchar(255) | NULLABLE, INDEXED | Stripe customer ID |
| `current_team_id` | bigint unsigned | NULLABLE, FK → teams.id (ON DELETE SET NULL) | Current team assignment |
| `remember_token` | varchar(100) | NULLABLE | Remember me token |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Relationships:**
- Belongs to `Team` (via `current_team_id`)
- Has one `Instructor` profile (if role is instructor)
- Has one `Student` profile (if role is student)
- Has many `Orders` (through Student)
- Has many `PersonalAccessTokens` (Sanctum API tokens)

**Enums:**
- Role: `owner`, `instructor`, `student`

---

### 2. **instructors**

Extended profile for users with instructor role.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique instructor identifier |
| `user_id` | bigint unsigned | FOREIGN KEY (users.id), UNIQUE, ON DELETE CASCADE | Reference to user |
| `stripe_account_id` | varchar(255) | NULLABLE, INDEXED | Stripe Connect account ID |
| `onboarding_complete` | boolean | DEFAULT false | Stripe onboarding completion status |
| `charges_enabled` | boolean | DEFAULT false | Stripe charges enabled status |
| `payouts_enabled` | boolean | DEFAULT false | Stripe payouts enabled status |
| `bio` | text | NULLABLE | Instructor biography for display |
| `rating` | float(3,2) | NULLABLE | Instructor rating (0.00-5.00) |
| `transmission_type` | enum('manual', 'automatic') | NOT NULL | Vehicle transmission type |
| `status` | varchar(50) | DEFAULT 'active' | Instructor status |
| `pdi_status` | varchar(50) | NULLABLE | PDI certification status |
| `priority` | boolean | DEFAULT false | Priority listing flag |
| `address` | text | NULLABLE | Instructor address (street/city) |
| `meta` | json | NULLABLE | Additional metadata in JSON format |
| `postcode` | varchar(10) | NULLABLE | Instructor postcode |
| `latitude` | decimal(10,8) | NULLABLE | Instructor location latitude |
| `longitude` | decimal(11,8) | NULLABLE | Instructor location longitude |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- `stripe_account_id`
- Composite index on `(latitude, longitude)` named `instructors_coordinates_index`

**Relationships:**
- Belongs to one `User`
- Has many `Packages` (bespoke packages)
- Has many `Orders`
- Has many `Lessons`
- Has many `Payouts`

**Business Logic:**
- Instructors must complete Stripe onboarding before receiving payouts
- Can create bespoke packages for their students

---

### 3. **students**

Extended profile for users with student role.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique student identifier |
| `user_id` | bigint unsigned | FOREIGN KEY (users.id), UNIQUE, ON DELETE CASCADE | Reference to user |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), NULLABLE, ON DELETE SET NULL, INDEXED | Assigned instructor |
| `first_name` | varchar(255) | NULLABLE | Learner's first name |
| `surname` | varchar(255) | NULLABLE | Learner's surname |
| `email` | varchar(255) | NULLABLE | Learner's email |
| `phone` | varchar(50) | NULLABLE | Learner's phone |
| `contact_first_name` | varchar(255) | NULLABLE | Booker's first name (if different) |
| `contact_surname` | varchar(255) | NULLABLE | Booker's surname |
| `contact_email` | varchar(255) | NULLABLE | Booker's email |
| `contact_phone` | varchar(50) | NULLABLE | Booker's phone |
| `terms_accepted` | boolean | DEFAULT false | Learner accepted terms |
| `allow_communications` | boolean | DEFAULT false | Learner marketing consent |
| `contact_terms` | boolean | NULLABLE | Booker accepted terms |
| `contact_communications` | boolean | NULLABLE | Booker marketing consent |
| `owns_account` | boolean | DEFAULT true | Learner owns this account |
| `status` | varchar(50) | DEFAULT 'active' | Student status (active, inactive, on_hold, passed, failed, completed) |
| `inactive_reason` | text | NULLABLE | Reason for status change (e.g., why student was made inactive) |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- `instructor_id`

**Relationships:**
- Belongs to one `User`
- Belongs to one `Instructor` (optional - assigned instructor)
- Has many `Orders`
- Has many `StudentPickupPoints`
- Has many `StudentChecklistItems`

**Status Values:**
- `active` (default), `inactive`, `on_hold`, `passed`, `failed`, `completed`

**Business Logic:**
- Students can be assigned to a specific instructor
- Students inherit instructor assignments from their orders
- Status change to `inactive` should include a reason in `inactive_reason`
- Removing a student from an instructor sets `instructor_id = null` (soft-remove)

---

### 4. **packages**

Lesson packages (both platform defaults and instructor bespoke packages).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique package identifier |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), NULLABLE, ON DELETE CASCADE | Instructor who created this bespoke package (null for platform packages) |
| `name` | varchar(255) | NOT NULL | Package name |
| `total_price_pence` | integer | NOT NULL | Total package price in pence |
| `lessons_count` | integer | NOT NULL | Number of lessons included |
| `lesson_price_pence` | integer | NOT NULL | Price per lesson in pence (auto-calculated) |
| `stripe_product_id` | varchar(255) | NULLABLE | Stripe product ID |
| `stripe_price_id` | varchar(255) | NULLABLE | Stripe price ID |
| `active` | boolean | DEFAULT true | Whether package is active/available |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(active, instructor_id)`

**Relationships:**
- Belongs to one `Instructor` (nullable - if null, it's a platform package)
- Has many `Orders`

**Business Logic:**
- Platform packages have `instructor_id` = NULL
- Bespoke packages are created by instructors for specific students
- `lesson_price_pence` is automatically calculated on save: `total_price_pence / lessons_count`
- Prices are stored in pence (GBP smallest unit)

---

### 5. **orders**

Student enrollments/purchases of lesson packages.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique order identifier |
| `student_id` | bigint unsigned | FOREIGN KEY (students.id), ON DELETE CASCADE | Student who made the order |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), NULLABLE, ON DELETE CASCADE | Assigned instructor |
| `package_id` | bigint unsigned | FOREIGN KEY (packages.id), ON DELETE CASCADE | Purchased package (template reference) |
| `package_name` | varchar(255) | NULLABLE | Snapshot: package name at time of order |
| `package_total_price_pence` | integer | NULLABLE | Snapshot: total price in pence at time of order |
| `package_lesson_price_pence` | integer | NULLABLE | Snapshot: per-lesson price in pence at time of order |
| `package_lessons_count` | integer | NULLABLE | Snapshot: number of lessons at time of order |
| `booking_fee_pence` | integer unsigned | DEFAULT 0 | Booking fee in pence (e.g., £19.99 = 1999) |
| `digital_fee_pence` | integer unsigned | DEFAULT 0 | Total digital fee in pence (£3.99 × lessons) |
| `total_price_pence` | integer unsigned | NULLABLE | Total charge amount in pence (package + booking fee + digital fees - discounts). Sent to Stripe. |
| `payment_mode` | enum('upfront', 'weekly') | DEFAULT 'upfront' | Payment method chosen |
| `status` | enum('pending', 'active', 'completed', 'cancelled') | DEFAULT 'pending' | Order status |
| `stripe_payment_intent_id` | varchar(255) | NULLABLE | Stripe Payment Intent ID (for upfront payments) |
| `stripe_subscription_id` | varchar(255) | NULLABLE | Stripe Subscription ID (for weekly payments) |
| `stripe_checkout_session_id` | varchar(255) | NULLABLE | Stripe Checkout Session ID |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(student_id, status)`
- Composite index on `(instructor_id, status)`

**Relationships:**
- Belongs to one `Student`
- Belongs to one `Instructor`
- Belongs to one `Package` (template reference — pricing should be read from snapshot columns)
- Has many `Lessons`
- Has many `LessonPayments` (through Lessons)

**Enums:**
- Payment Mode: `upfront`, `weekly`
- Status: `pending`, `active`, `completed`, `cancelled`

**Business Logic:**
- Upfront payment: Single payment via Payment Intent
- Weekly payment: Recurring subscription for each lesson
- Order becomes active after successful payment
- Lessons are created after order activation
- **Price snapshot:** `package_name`, `package_total_price_pence`, `package_lesson_price_pence`, and `package_lessons_count` are copied from the package at order creation time. Always use these snapshot columns for pricing/display — never read live from `packages` table via the `package` relationship for pricing data.

---

### 6. **lessons**

Individual lessons within an order. Each lesson represents a scheduled session with date, time, and calendar slot information.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique lesson identifier |
| `order_id` | bigint unsigned | FOREIGN KEY (orders.id), ON DELETE CASCADE | Parent order |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), NULLABLE, ON DELETE CASCADE | Assigned instructor |
| `amount_pence` | integer | NOT NULL | Lesson price in pence |
| `date` | date | NULLABLE | Scheduled lesson date |
| `start_time` | time | NULLABLE | Scheduled start time |
| `end_time` | time | NULLABLE | Scheduled end time |
| `calendar_item_id` | bigint unsigned | FOREIGN KEY (calendar_items.id), NULLABLE, ON DELETE SET NULL | Associated calendar slot |
| `completed_at` | datetime | NULLABLE | When lesson was completed |
| `summary` | text | NULLABLE | Instructor's summary of the lesson (written at sign-off, used for AI resource matching) |
| `mileage` | unsigned integer | NULLABLE | Miles driven during this lesson |
| `status` | enum('draft', 'pending', 'completed', 'cancelled') | DEFAULT 'pending' | Lesson status |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(order_id, status)`
- Index on `instructor_id`
- Index on `calendar_item_id`

**Relationships:**
- Belongs to one `Order`
- Belongs to one `Instructor`
- Belongs to one `CalendarItem` (optional - links to specific calendar slot)
- Has one `LessonPayment`
- Has one `Payout`

**Enums:**
- Status: `draft`, `pending`, `completed`, `cancelled`

**Business Logic:**
- Created as `draft` for upfront orders (pre-payment) or `pending` for weekly orders
- Transitions from `draft` → `pending` when Stripe confirms payment (via ConfirmCalendarItemsAction)
- Draft lessons are filtered out of all API responses and cleaned up by the nightly `calendar:cleanup-drafts` command
- Number of lessons matches the package's `lessons_count`
- Scheduling information (date, start_time, end_time) can be set when booking lesson
- Links to calendar_item for slot availability tracking
- Instructor gets paid after lesson is completed
- `summary` is written by the instructor at sign-off time; used by AI (AWS Bedrock Nova) to match against resource tags and recommend relevant videos/PDFs to the student
- `mileage` is recorded by the instructor after the lesson is completed, via the schedule view

---

### 7. **lesson_payments**

Tracks payment status for individual lessons (used in weekly payment mode).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique payment identifier |
| `lesson_id` | bigint unsigned | FOREIGN KEY (lessons.id), ON DELETE CASCADE | Associated lesson |
| `amount_pence` | integer | NOT NULL | Payment amount in pence |
| `status` | enum('due', 'paid', 'refunded') | DEFAULT 'due' | Payment status |
| `due_date` | date | NULLABLE | When payment is due |
| `paid_at` | datetime | NULLABLE | When payment was received |
| `stripe_invoice_id` | varchar(255) | NULLABLE | Stripe Invoice ID |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(lesson_id, status)`

**Relationships:**
- Belongs to one `Lesson`

**Enums:**
- Status: `due`, `paid`, `refunded`

**Business Logic:**
- Created for each lesson in weekly payment mode
- Weekly payments are charged via Stripe Subscriptions/Invoices
- Payment must be completed before lesson can be marked as completed

---

### 8. **payouts**

Tracks instructor payouts for completed lessons.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique payout identifier |
| `lesson_id` | bigint unsigned | FOREIGN KEY (lessons.id), UNIQUE, ON DELETE CASCADE | Associated lesson |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), ON DELETE CASCADE | Instructor receiving payout |
| `amount_pence` | integer | NOT NULL | Payout amount in pence |
| `status` | enum('pending', 'paid', 'failed') | DEFAULT 'pending' | Payout status |
| `stripe_transfer_id` | varchar(255) | NULLABLE | Stripe Transfer ID |
| `paid_at` | datetime | NULLABLE | When payout was sent |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(instructor_id, status)`

**Relationships:**
- Belongs to one `Lesson` (one-to-one)
- Belongs to one `Instructor`

**Enums:**
- Status: `pending`, `paid`, `failed`

**Business Logic:**
- Created after lesson is completed and payment received
- Transferred to instructor's Stripe Connect account
- One payout per lesson

---

### 9. **activity_logs**

Polymorphic activity logging for instructors and students. Tracks all significant activities with categorization.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique log identifier |
| `loggable_type` | varchar(255) | NOT NULL, INDEXED | Model type (App\Models\Instructor or App\Models\Student) |
| `loggable_id` | bigint unsigned | NOT NULL, INDEXED | Model ID |
| `category` | varchar(50) | NOT NULL, INDEXED | Activity category |
| `message` | text | NOT NULL | Human-readable activity message |
| `metadata` | json | NULLABLE | Additional context data in JSON format |
| `deleted_at` | timestamp | NULLABLE | Soft delete timestamp |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(loggable_type, loggable_id, deleted_at)` named `activity_logs_loggable_index`
- Index on `category`
- Index on `created_at`

**Relationships:**
- Morphs to one `Instructor` or `Student` (polymorphic)

**Categories:**
- `lesson` - Lesson-related activities (completed, cancelled, rescheduled)
- `booking` - Booking changes (new booking, confirmation, updates)
- `message` - Messages sent/received
- `payment` - Payment activities (received, refunded, failed)
- `profile` - Profile updates (details changed, onboarding)
- `package` - Package activities (created, updated, purchased)
- `student` - Student-related activities (for instructor logs)
- `instructor` - Instructor-related activities (for student logs)
- `system` - System-generated events

**Business Logic:**
- Soft deletes enabled for audit trail
- Queued creation via LogActivityJob for performance
- Searchable by message content
- Filterable by category
- Used for activity timelines in UI

---

### 10. **contacts**

Polymorphic emergency contacts for instructors and students. Supports a primary contact designation per entity.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique contact identifier |
| `contactable_type` | varchar(255) | NOT NULL | Model type (App\Models\Instructor or App\Models\Student) |
| `contactable_id` | bigint unsigned | NOT NULL | Model ID |
| `name` | varchar(255) | NOT NULL | Emergency contact full name |
| `relationship` | varchar(100) | NOT NULL | Relationship to the person |
| `phone` | varchar(50) | NOT NULL | Phone number |
| `email` | varchar(255) | NULLABLE | Email address |
| `address` | text | NULLABLE | Contact's address |
| `is_primary` | boolean | DEFAULT false | Whether this is the primary contact |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(contactable_type, contactable_id)` named `contacts_contactable_index`

**Relationships:**
- Morphs to one `Instructor` or `Student` (polymorphic)

**Relationship Values:**
- Spouse, Parent, Child, Sibling, Friend, Doctor, Other

**Business Logic:**
- Only one contact per entity can be `is_primary = true`
- When setting a new primary, all others for that entity are unset
- Used for emergency contact display in UI

---

### 11. **notes**

Polymorphic notes for instructors and students. Supports soft deletes for audit trail.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique note identifier |
| `noteable_type` | varchar(255) | NOT NULL | Model type (App\Models\Instructor or App\Models\Student) |
| `noteable_id` | bigint unsigned | NOT NULL | Model ID |
| `note` | text | NOT NULL | Note content |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |
| `deleted_at` | timestamp | NULLABLE | Soft delete timestamp |

**Indexes:**
- Composite index on `(noteable_type, noteable_id, deleted_at)` named `notes_noteable_index`

**Relationships:**
- Morphs to one `Instructor` or `Student` (polymorphic)

**Business Logic:**
- Soft deletes enabled for audit trail
- Creating a note triggers an activity log entry (category: `note`)
- Used for internal notes/comments on student or instructor profiles

---

### 12. **webhook_events**

Logs Stripe webhook events for debugging and idempotency.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique event identifier |
| `stripe_event_id` | varchar(255) | UNIQUE, NOT NULL | Stripe event ID |
| `type` | varchar(255) | NOT NULL, INDEXED | Event type (e.g., 'payment_intent.succeeded') |
| `payload` | json | NULLABLE | Full webhook payload |
| `processed_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | When event was processed |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `type`

**Business Logic:**
- Prevents duplicate webhook processing
- Useful for debugging payment flows

---

### 11. **password_reset_tokens**

Laravel's password reset token storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `email` | varchar(255) | PRIMARY KEY | User's email |
| `token` | varchar(255) | NOT NULL | Reset token |
| `created_at` | timestamp | NULLABLE | Token creation time |

---

### 12. **enquiries** (New - Phase 1 Onboarding)

Stores all onboarding session data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | uuid | PRIMARY KEY | Unique identifier, used in URLs |
| `data` | json | NULLABLE | Complete onboarding payload |
| `current_step` | integer | NOT NULL, DEFAULT 1 | Last completed/active step (1-6) |
| `max_step_reached` | integer | NOT NULL, DEFAULT 1 | Furthest step user has reached |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Business Logic:**
- UUID is generated on creation (Step 1 completion)
- `data` field stores the JSON structure for all steps
- `current_step` is denormalised for quick queries

---

### 13. **locations** (New - Phase 1 Onboarding)

Links instructors to postcode sectors they cover.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique location identifier |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), ON DELETE CASCADE | Reference to instructor |
| `postcode_sector` | varchar(10) | NOT NULL, INDEXED | e.g., "TS7", "NE1" |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `postcode_sector`

**Relationships:**
- Belongs to one `Instructor`

**Business Logic:**
- An instructor can have multiple location records
- Postcode sector format: area + district, e.g., "TS7", "NE12"

---

### 14. **calendars** (New - Phase 1 Onboarding)

Defines available dates per instructor.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique calendar identifier |
| `instructor_id` | bigint unsigned | FOREIGN KEY (instructors.id), ON DELETE CASCADE | Reference to instructor |
| `date` | date | NOT NULL | Available date |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Unique Constraints:**
- Unique on (`instructor_id`, `date`)

**Relationships:**
- Belongs to one `Instructor`
- Has many `CalendarItems`

---

### 15. **calendar_items** (New - Phase 1 Onboarding)

Defines time slots within a calendar date.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique calendar item identifier |
| `calendar_id` | bigint unsigned | FOREIGN KEY (calendars.id), ON DELETE CASCADE | Reference to calendar |
| `start_time` | time | NOT NULL | Slot start time |
| `end_time` | time | NOT NULL | Slot end time |
| `is_available` | boolean | DEFAULT true | Availability flag |
| `status` | enum('draft', 'reserved', 'booked', 'completed') | NULLABLE | Booking lifecycle status |
| `item_type` | varchar(20) | DEFAULT 'slot', INDEXED | Calendar item type: 'slot' (lesson), 'travel' (travel time), or 'practical_test' (driving test slot) |
| `travel_time_minutes` | smallint unsigned | NULLABLE | Travel time in minutes (15, 30, or 45) set on the parent slot |
| `parent_item_id` | bigint unsigned | FOREIGN KEY (calendar_items.id), NULLABLE, ON DELETE SET NULL | Links travel blocks to their parent lesson slot |
| `notes` | text | NULLABLE | General notes about this calendar slot |
| `unavailability_reason` | text | NULLABLE | Reason for marking slot unavailable (only when is_available = false) |
| `recurrence_pattern` | varchar(20) | DEFAULT 'none' | Recurrence pattern: none, weekly, biweekly, monthly |
| `recurrence_end_date` | date | NULLABLE | End date for the recurrence series |
| `recurrence_group_id` | uuid | NULLABLE, INDEXED | Groups all instances of a recurring slot together |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Relationships:**
- Belongs to one `Calendar`

**Enums:**
- Status: `draft` (tentative hold during onboarding), `reserved` (weekly payment pending), `booked` (fully paid), `completed` (lesson finished)
- RecurrencePattern: `none` (single slot), `weekly` (every week), `biweekly` (every 2 weeks), `monthly` (every month)

**Business Logic:**
- Multiple time slots per calendar date
- `is_available` allows blocking slots without deletion
- `item_type = 'practical_test'`: blocks a 2.5hr window (1hr prep + 1hr test + 30min buffer), always `is_available = false`
- `status` tracks the booking lifecycle: `draft` → `reserved`/`booked` → `completed`
- Draft items are cleaned up by `calendar:cleanup-drafts` command if abandoned
- Recurring slots: materialized instances pattern — each occurrence is a separate row linked by `recurrence_group_id`
- Individual occurrences can be modified/deleted without affecting the rest of the series
- Deleting "this and all future" removes all items in the group from the selected date forward (excluding those with lessons)

---

### 16. **messages**

Stores broadcast and direct messages between users. Supports soft deletes for audit trail.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique message identifier |
| `from` | bigint unsigned | FOREIGN KEY (users.id), ON DELETE CASCADE, INDEXED | Sender user ID |
| `to` | bigint unsigned | FOREIGN KEY (users.id), ON DELETE CASCADE, INDEXED | Recipient user ID |
| `message` | text | NOT NULL | Message content |
| `deleted_at` | timestamp | NULLABLE | Soft delete timestamp |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `from`
- Index on `to`

**Relationships:**
- Belongs to one `User` as sender (via `from`)
- Belongs to one `User` as recipient (via `to`)

**Business Logic:**
- Used for broadcast messages from instructors to their students
- Soft deletes enabled for audit trail
- One record created per recipient in a broadcast

---

### 17. **resource_folders**

Hierarchical folder structure for organising resources (videos, PDFs). Self-referencing `parent_id` enables unlimited nesting depth. Owner-only feature.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique folder identifier |
| `parent_id` | bigint unsigned | FOREIGN KEY (self), NULLABLE, ON DELETE CASCADE | Parent folder (NULL = root level) |
| `name` | varchar(255) | NOT NULL | Folder display name |
| `slug` | varchar(255) | NOT NULL | URL-friendly name (auto-generated) |
| `sort_order` | integer | DEFAULT 0 | Display ordering within parent |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `parent_id`
- Unique constraint on `(parent_id, slug)`

**Relationships:**
- Belongs to one `ResourceFolder` as parent (optional — NULL = root)
- Has many `ResourceFolder` children (self-referencing)
- Has many `Resource` items (videos, PDFs)

**Business Logic:**
- Root folders have `parent_id = NULL`
- Deleting a folder cascades to all sub-folders and resources within
- Slug is auto-generated from name on create/update
- Example hierarchy: `Roundabouts` → `Turning right at roundabout`, `Turning left at roundabout`

---

### 18. **resources**

Stores uploaded files (videos, PDFs) or video links (Vimeo/YouTube) with metadata, descriptions, and tags for AI-powered search. Files stored on S3; video links store only the URL. Owner-only feature.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique resource identifier |
| `resource_folder_id` | bigint unsigned | FOREIGN KEY (resource_folders.id), ON DELETE CASCADE | Parent folder |
| `title` | varchar(255) | NOT NULL | Resource display title |
| `description` | text | NULLABLE | Text description (used for AI search) |
| `tags` | json | NULLABLE | Array of tag strings (used for AI search) |
| `resource_type` | varchar(20) | NOT NULL, DEFAULT 'file' | Type of resource: 'file' or 'video_link' |
| `video_url` | varchar(500) | NULLABLE | Vimeo/YouTube URL (only for video_link type) |
| `file_path` | varchar(500) | NULLABLE | S3 storage path (only for file type) |
| `file_name` | varchar(255) | NULLABLE | Original filename at upload (only for file type) |
| `file_size` | bigint unsigned | NULLABLE | File size in bytes (only for file type) |
| `mime_type` | varchar(100) | NULLABLE | File MIME type (only for file type) |
| `thumbnail_path` | varchar(500) | NULLABLE | S3 thumbnail path (optional) |
| `thumbnail_url` | varchar(500) | NULLABLE | External thumbnail URL (for video_link resources) |
| `sort_order` | integer | DEFAULT 0 | Display ordering within folder |
| `status` | varchar(255) | DEFAULT 'published' | Resource visibility: 'published' or 'draft' |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `resource_folder_id`

**Relationships:**
- Belongs to one `ResourceFolder`

**Business Logic:**
- Two resource types: `file` (uploaded to S3) and `video_link` (Vimeo/YouTube URL stored in `video_url`)
- For `file` type: supports video files (video/mp4, video/webm, etc.) and PDFs (application/pdf), stored on S3
- For `video_link` type: file columns (`file_path`, `file_name`, `file_size`, `mime_type`) are NULL
- Tags stored as JSON array of strings, e.g. `["roundabout", "right turn", "signalling"]`
- Description and tags will be used for AI-powered video/document suggestions at a later date
- `resource_type` + `mime_type` determines rendering: embedded player for video links, video player for uploaded videos, PDF viewer/download for PDFs
- Deleting a file-type resource also removes the file from S3; deleting a video_link resource only removes the DB record
- `thumbnail_url` stores an external image URL for video link resources (e.g. YouTube thumbnail)

---

### 19. **student_pickup_points**

Stores pickup/drop-off locations for students. Each student can have multiple pickup points with one designated as default. Postcode is geocoded to lat/lng via postcodes.io API.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique pickup point identifier |
| `student_id` | bigint unsigned | FOREIGN KEY (students.id), ON DELETE CASCADE | Reference to student |
| `label` | varchar(255) | NOT NULL | Location name (e.g., "Home", "School", "Work") |
| `address` | text | NOT NULL | Full address line |
| `postcode` | varchar(10) | NOT NULL | UK postcode |
| `latitude` | decimal(10,8) | NULLABLE | Geocoded latitude from postcode |
| `longitude` | decimal(11,8) | NULLABLE | Geocoded longitude from postcode |
| `is_default` | boolean | DEFAULT false | Whether this is the default pickup point |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `student_id`

**Relationships:**
- Belongs to one `Student`

**Business Logic:**
- Only one pickup point per student can be `is_default = true`
- When setting a new default, all others for that student are unset
- Postcode is geocoded on create/update via postcodes.io API (free, no API key)
- Used for lesson scheduling and instructor route planning

---

### 20. **student_checklist_items**

Tracks progress through standard checklist items for each student (e.g., theory test booking, practical test, licence checks). Items are lazy-seeded from a default list on first access.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique checklist item identifier |
| `student_id` | bigint unsigned | FOREIGN KEY (students.id), ON DELETE CASCADE | Reference to student |
| `key` | varchar(100) | NOT NULL | Unique slug identifier (e.g., "book_theory_test") |
| `label` | varchar(255) | NOT NULL | Display label (e.g., "Book theory test") |
| `category` | varchar(100) | NOT NULL | Category grouping (e.g., "Theory Test", "Practical Test", "General") |
| `is_checked` | boolean | DEFAULT false | Whether the item has been completed |
| `date` | date | NULLABLE | Associated date (e.g., when theory test is booked for) |
| `notes` | text | NULLABLE | Optional notes about this item |
| `sort_order` | integer | DEFAULT 0 | Display ordering |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Unique constraint on `(student_id, key)`
- Index on `student_id`

**Relationships:**
- Belongs to one `Student`

**Default Items:**
- **Theory Test**: Book theory test, Sit theory test
- **Practical Test**: Schedule mock test, Sit mock test, Book practical test, Sit practical test
- **General**: Agreed terms, Driving licence number, Eyesight checked

**Business Logic:**
- Default items are seeded lazily on first access (if student has no checklist items)
- Checking an item opens a date picker modal; instructor adds a date and optional notes
- Unchecking an item clears the date and notes
- Items are grouped by category in the UI (3-column grid)

---

### 22. **instructor_finances**

Tracks payments received and expenses incurred by instructors. Supports recurring entries.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Finance record ID |
| `instructor_id` | bigint unsigned | FOREIGN KEY → instructors.id, CASCADE DELETE, INDEXED | Owning instructor |
| `type` | enum('payment','expense') | NOT NULL | Whether this is income or an expense |
| `description` | varchar(255) | NOT NULL | Description of the payment/expense |
| `amount_pence` | integer | NOT NULL | Amount in pence (e.g., 3500 = £35.00) |
| `is_recurring` | boolean | NOT NULL, DEFAULT false | Whether this is a recurring entry |
| `recurrence_frequency` | varchar(255) | NULLABLE | Frequency: `weekly`, `monthly`, `yearly` |
| `date` | date | NOT NULL | Date of the payment/expense |
| `notes` | text | NULLABLE | Additional notes |
| `created_at` | timestamp | NULLABLE | Created timestamp |
| `updated_at` | timestamp | NULLABLE | Updated timestamp |

**Indexes:** `(instructor_id, type)`, `(instructor_id, date)`

**Relationships:**
- `instructor_finances.instructor_id` → `instructors.id` (CASCADE DELETE)

---

### 21. **sessions**

Laravel's session storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | varchar(255) | PRIMARY KEY | Session ID |
| `user_id` | bigint unsigned | NULLABLE, INDEXED | Associated user |
| `ip_address` | varchar(45) | NULLABLE | Client IP |
| `user_agent` | text | NULLABLE | Client user agent |
| `payload` | longtext | NOT NULL | Session data |
| `last_activity` | integer | INDEXED | Last activity timestamp |

---

### 20. **cache**

Laravel's cache storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `key` | varchar(255) | PRIMARY KEY | Cache key |
| `value` | mediumtext | NOT NULL | Cached value |
| `expiration` | integer | NOT NULL | Expiration timestamp |

---

### 21. **cache_locks**

Laravel's cache locking mechanism.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `key` | varchar(255) | PRIMARY KEY | Lock key |
| `owner` | varchar(255) | NOT NULL | Lock owner identifier |
| `expiration` | integer | NOT NULL | Lock expiration timestamp |

---

### 22. **jobs**

Laravel's queue system for background job processing.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique job identifier |
| `queue` | varchar(255) | INDEXED | Queue name |
| `payload` | longtext | NOT NULL | Job payload data |
| `attempts` | unsigned tinyint | NOT NULL | Number of attempts |
| `reserved_at` | unsigned integer | NULLABLE | When job was reserved |
| `available_at` | unsigned integer | NOT NULL | When job becomes available |
| `created_at` | unsigned integer | NOT NULL | Job creation timestamp |

---

### 23. **job_batches**

Laravel's job batching system.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | varchar(255) | PRIMARY KEY | Batch identifier |
| `name` | varchar(255) | NOT NULL | Batch name |
| `total_jobs` | integer | NOT NULL | Total jobs in batch |
| `pending_jobs` | integer | NOT NULL | Pending jobs count |
| `failed_jobs` | integer | NOT NULL | Failed jobs count |
| `failed_job_ids` | longtext | NOT NULL | IDs of failed jobs |
| `options` | mediumtext | NULLABLE | Batch options |
| `cancelled_at` | integer | NULLABLE | When batch was cancelled |
| `created_at` | integer | NOT NULL | Batch creation timestamp |
| `finished_at` | integer | NULLABLE | When batch finished |

---

### 24. **failed_jobs**

Laravel's failed jobs storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique failed job identifier |
| `uuid` | varchar(255) | UNIQUE, NOT NULL | Job UUID |
| `connection` | text | NOT NULL | Queue connection |
| `queue` | text | NOT NULL | Queue name |
| `payload` | longtext | NOT NULL | Job payload |
| `exception` | longtext | NOT NULL | Exception details |
| `failed_at` | timestamp | DEFAULT CURRENT_TIMESTAMP | When job failed |

---

### 25. **personal_access_tokens**

Laravel Sanctum's API token storage. Each row is a single API token issued to a user (typically one per mobile device).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique token identifier |
| `tokenable_type` | varchar(255) | NOT NULL | Polymorphic model type (e.g., `App\Models\User`) |
| `tokenable_id` | bigint unsigned | NOT NULL | Polymorphic model ID (user ID) |
| `name` | varchar(255) | NOT NULL | Token name (device name, e.g., "iPhone 15") |
| `token` | varchar(64) | UNIQUE, NOT NULL | SHA-256 hash of the plain-text token |
| `abilities` | text | NULLABLE | JSON array of token abilities/scopes |
| `last_used_at` | timestamp | NULLABLE | When the token was last used |
| `expires_at` | timestamp | NULLABLE | Optional token expiration |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Unique on `token`
- Index on `(tokenable_type, tokenable_id)`

**Relationships:**
- Belongs to a `User` (via polymorphic `tokenable`)

---

### 26. **reflective_logs**

Student reflective logs for lessons. Each lesson can have at most one reflective log. A past lesson without a reflective log cannot be signed off (displays as "needs sign-off" / red card in the mobile app).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique reflective log identifier |
| `lesson_id` | bigint unsigned | FOREIGN KEY → lessons(id) ON DELETE CASCADE, UNIQUE | The lesson this log belongs to |
| `what_i_learned` | text | NULLABLE | What the student learned |
| `what_went_well` | text | NULLABLE | What went well during the lesson |
| `what_to_improve` | text | NULLABLE | Areas to improve |
| `additional_notes` | text | NULLABLE | Any additional notes |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Unique on `lesson_id` (enforces one-to-one)

**Relationships:**
- Belongs to `Lesson` (one-to-one)

---

### 27. **lesson_resource** (pivot)

Many-to-many pivot table linking lessons to resources. Allows attaching multiple resources to a lesson.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique pivot identifier |
| `lesson_id` | bigint unsigned | FOREIGN KEY → lessons(id) ON DELETE CASCADE | The lesson |
| `resource_id` | bigint unsigned | FOREIGN KEY → resources(id) ON DELETE CASCADE | The resource |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Unique on `(lesson_id, resource_id)` (prevents duplicates)

**Relationships:**
- Belongs to `Lesson`
- Belongs to `Resource`

---

### 28. **discount_codes**

UUID-based discount codes for the onboarding flow. Each code maps to a percentage tier (5%, 10%, 15%, or 20%) and can be shared via URL.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | uuid | PRIMARY KEY | Unique identifier, used in onboarding URLs |
| `label` | varchar(255) | NOT NULL | Human-readable label |
| `percentage` | unsigned tinyint | NOT NULL | Discount percentage (5, 10, 15, or 20) |
| `active` | boolean | DEFAULT true | Whether the code is currently usable |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Relationships:**
- Has many `Orders` (via `orders.discount_code_id`)

---

### Orders Table — Discount Fields (added)

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `discount_code_id` | uuid | NULLABLE, FK → discount_codes.id (ON DELETE SET NULL) | Discount code used |
| `discount_percentage` | unsigned tinyint | NULLABLE | Snapshot of discount percentage at time of order |

---

## Key Business Flows

### 1. Student Purchase Flow (Upfront Payment)

1. Student selects a package
2. Creates order with `payment_mode = 'upfront'` and `status = 'pending'`
3. Redirects to Stripe Checkout
4. Webhook `checkout.session.completed` updates order to `status = 'active'`
5. Creates N lessons (where N = package's `lessons_count`)
6. Assigns instructor to order and lessons

### 2. Student Purchase Flow (Weekly Payment)

1. Student selects a package and weekly payment
2. Creates order with `payment_mode = 'weekly'` and `status = 'pending'`
3. Creates Stripe Subscription
4. Webhook `checkout.session.completed` updates order to `status = 'active'`
5. Creates N lessons with corresponding `LessonPayment` records
6. Each week, Stripe charges the student and fires `invoice.paid` webhook
7. Webhook updates `LessonPayment.status = 'paid'`

### 3. Lesson Completion & Payout Flow

1. Instructor marks lesson as completed (`status = 'completed'`, sets `completed_at`)
2. System verifies lesson payment is received
3. Creates `Payout` record with `status = 'pending'`
4. Transfers funds to instructor's Stripe Connect account
5. Webhook `transfer.created` updates `Payout.status = 'paid'`

### 4. Instructor Onboarding Flow

1. Instructor registers (creates User with `role = 'instructor'`)
2. Creates Instructor record with default flags (all false)
3. Redirects to Stripe Connect onboarding
4. Webhook `account.updated` updates Instructor fields:
   - `onboarding_complete = true`
   - `charges_enabled = true`
   - `payouts_enabled = true`

---

## Data Integrity Rules

1. **Cascading Deletes:**
   - Deleting a User cascades to Instructor/Student profiles
   - Deleting an Order cascades to all Lessons
   - Deleting a Lesson cascades to LessonPayments and Payouts

2. **Nullable Foreign Keys:**
   - `packages.instructor_id` - NULL = platform package
   - `students.instructor_id` - Student may not have assigned instructor yet

3. **Price Storage:**
   - All prices stored in pence (integer) to avoid floating-point precision issues

4. **Uniqueness Constraints:**
   - `users.email` - Each email can only have one account
   - `instructors.user_id` - One instructor profile per user
   - `students.user_id` - One student profile per user
   - `payouts.lesson_id` - One payout per lesson
   - `webhook_events.stripe_event_id` - Prevent duplicate webhook processing

---

## Indexes for Performance

- **users:** `email` (unique), `stripe_customer_id`
- **instructors:** `stripe_account_id`, `(latitude, longitude)` composite named `instructors_coordinates_index`
- **students:** `instructor_id`
- **packages:** `(active, instructor_id)` composite
- **orders:** `(student_id, status)`, `(instructor_id, status)` composites
- **lessons:** `(order_id, status)`, `instructor_id`, `calendar_item_id`
- **lesson_payments:** `(lesson_id, status)` composite
- **payouts:** `(instructor_id, status)` composite
- **activity_logs:** `(loggable_type, loggable_id, deleted_at)` composite named `activity_logs_loggable_index`, `category`, `created_at`
- **contacts:** `(contactable_type, contactable_id)` composite named `contacts_contactable_index`
- **notes:** `(noteable_type, noteable_id, deleted_at)` composite named `notes_noteable_index`
- **webhook_events:** `type`
- **enquiries:** None (uses UUID primary key)
- **locations:** `postcode_sector`
- **calendars:** Unique constraint on `(instructor_id, date)`
- **calendar_items:** `recurrence_group_id`
- **messages:** `from`, `to`
- **resource_folders:** `parent_id`, unique on `(parent_id, slug)`
- **resources:** `resource_folder_id`
- **student_pickup_points:** `student_id`
- **student_checklist_items:** `student_id`, unique on `(student_id, key)`
- **sessions:** `user_id`, `last_activity`
- **jobs:** `queue`

---

## Notes for LLM Understanding

1. **Multi-tenancy:** The system supports multiple instructors, each with their own students and packages.

2. **Payment Flexibility:** Students can choose between upfront payment (single charge) or weekly payments (subscription).

3. **Stripe Integration:** Heavy integration with Stripe for:
   - Customer management (`stripe_customer_id`)
   - Connect accounts (`stripe_account_id`)
   - Payment Intents (upfront)
   - Subscriptions (weekly)
   - Transfers (payouts)

4. **Status Tracking:** Multiple status enums track the lifecycle of orders, lessons, payments, and payouts.

5. **Money Handling:** All monetary values stored in pence (smallest currency unit) as integers to prevent precision errors.

6. **Webhook Idempotency:** `webhook_events` table ensures each Stripe event is processed exactly once.
