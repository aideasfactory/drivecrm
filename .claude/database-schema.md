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
- `ResourceWatch` → Tracks which resources a user has watched (user_id + resource_id, unique)
- `StudentPickupPoint` → Pickup/drop-off locations per student (geocoded)
- `StudentChecklistItem` → Progress tracking items per student (theory test, practical test, etc.)
- `MockTestQuestion` → Theory test question bank (~2,923 questions across 4 categories)
- `MockTest` → Student test session (score, pass/fail, timestamps)
- `MockTestAnswer` → Individual answers per test (right/wrong, for category performance tracking)
- `HazardPerceptionVideo` → Hazard perception video clips with hazard timing windows and categorisation
- `HazardPerceptionAttempt` → Student attempt scores per video (response times, per-hazard scores)

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
   - `password_change_required` (boolean, default false) — set to true when a temporary password is issued (instructor-created pupils, onboarding, admin reset); cleared when user changes password via API
   - `welcome_email_pending` (boolean, default false) — set to true ONLY when a brand-new user is created during web onboarding (`CreateUserAndStudentFromEnquiryAction`). Cleared atomically by `SendOrderConfirmationEmailAction` once the welcome email (with a freshly generated temporary password) has been dispatched. Guarantees the temp-password welcome email is sent at most once and never to returning pupils.

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
    - Config-backed `category` + `payment_method` slugs; optional receipt file on private S3

11. **mileage_logs** - Instructor mileage records (business/personal)
    - Many-to-one with `instructors` (via `instructor_id`)
    - Separate ledger from `instructor_finances`; fuel expenses are NOT linked here

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
| `password_change_required` | boolean | DEFAULT false | Forces password reset on next login (set when a temp password is issued) |
| `welcome_email_pending` | boolean | DEFAULT false | True only between new-user creation in web onboarding and the welcome email being dispatched by `SendOrderConfirmationEmailAction`. One-shot flag — cleared atomically when the welcome email goes out. |
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
| `phone` | varchar(20) | NULLABLE | Instructor phone number |
| `pin` | varchar(10) | NULLABLE, UNIQUE | Instructor PIN code for student attachment |
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
| `business_type` | varchar(32) | NULLABLE | HMRC tax-profile business type (`sole_trader`, `partnership`, `limited_company`) |
| `vat_registered` | boolean | DEFAULT false | Instructor / business is VAT-registered |
| `vrn` | varchar(9) | NULLABLE, UNIQUE | VAT Registration Number (9 digits, unique where present) |
| `utr` | varchar(10) | NULLABLE | Unique Taxpayer Reference (10 digits) — sole trader / partnership |
| `nino` | text | NULLABLE, ENCRYPTED | National Insurance Number — encrypted at rest (PII); plaintext is 9 chars |
| `companies_house_number` | varchar(8) | NULLABLE | Companies House registration number — limited company only |
| `tax_profile_completed_at` | timestamp | NULLABLE | When the instructor first completed their HMRC tax profile |
| `mtd_itsa_status` | varchar(32) | DEFAULT 'unknown' | MTD ITSA enrolment state machine: `unknown`, `not_signed_up`, `income_source_missing`, `signed_up_voluntary`, `mandated` |
| `mtd_itsa_status_checked_at` | timestamp | NULLABLE | When `mtd_itsa_status` was last refreshed against HMRC |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- `stripe_account_id`
- Composite index on `(latitude, longitude)` named `instructors_coordinates_index`
- `pin` (unique)
- `vrn` (unique — MySQL allows multiple NULLs)

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
| `student_lesson_number` | unsigned integer | NOT NULL, INDEX | Per-student running lesson number — starts at 1 for each student and increments across all their orders. Used as the user-facing lesson reference for support queries. Stable for life (cancelled / cleaned drafts keep their number; gaps are allowed). |
| `status` | enum('draft', 'pending', 'completed', 'cancelled') | DEFAULT 'pending' | Lesson status |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Composite index on `(order_id, status)`
- Index on `instructor_id`
- Index on `calendar_item_id`
- Index on `student_lesson_number`

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
- `student_lesson_number` is assigned at lesson creation time inside the order's transaction. Computed as `MAX(student_lesson_number) + 1` over the student's existing lessons (across all orders), with `lockForUpdate()` on the existing rows to serialise concurrent same-student order creations. Numbers are immutable after assignment — a cancelled or cleaned-up draft lesson keeps its number, so gaps in the sequence are expected and intentional

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
- **Support inbox:** the admin's "Support Messages" page is simply the logged-in admin's messages inbox — the same `MessageService::getConversations()` path used by every other user. From the mobile app, students/instructors send to the admin's user id (1 in the default seeded data) via the existing `POST /api/v1/messages` endpoint. No sentinel id, no virtual participant, no schema change.

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
| `audience` | varchar(20) | NOT NULL, DEFAULT 'student' | Who this resource is for: 'student' or 'instructor' |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- Index on `resource_folder_id`
- Index on `audience`

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
- `audience` partitions resources between the student and instructor mobile apps. Admins must pick one on create/edit (and in the CSV import). The generic `GET /api/v1/resources` endpoint accepts `?audience=student|instructor`; omit to return all. Existing rows default to `student`.

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

Tracks payments received and expenses incurred by instructors. Supports recurring entries, category classification, payment method, and an optional receipt attachment (private S3).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Finance record ID |
| `instructor_id` | bigint unsigned | FOREIGN KEY → instructors.id, CASCADE DELETE, INDEXED | Owning instructor |
| `type` | enum('payment','expense') | NOT NULL | Whether this is income or an expense |
| `category` | varchar(64) | NOT NULL, DEFAULT 'none' | Slug from `config/finances.php` — `expense_categories` when type=expense, `payment_categories` when type=payment |
| `payment_method` | varchar(32) | NULLABLE | Slug from `config('finances.payment_methods')` |
| `description` | varchar(255) | NOT NULL | Description of the payment/expense |
| `amount_pence` | integer | NOT NULL | Amount in pence (e.g., 3500 = £35.00) |
| `is_recurring` | boolean | NOT NULL, DEFAULT false | Whether this is a recurring entry |
| `recurrence_frequency` | varchar(255) | NULLABLE | Frequency: `weekly`, `monthly`, `yearly` |
| `date` | date | NOT NULL | Date of the payment/expense |
| `notes` | text | NULLABLE | Additional notes |
| `receipt_path` | varchar(255) | NULLABLE | Private S3 path — viewed via temporary signed URLs |
| `receipt_original_name` | varchar(255) | NULLABLE | Original filename for display/download |
| `receipt_mime_type` | varchar(64) | NULLABLE | Upload MIME (PDF/JPG/PNG) |
| `receipt_size_bytes` | int unsigned | NULLABLE | Upload size, bytes |
| `created_at` | timestamp | NULLABLE | Created timestamp |
| `updated_at` | timestamp | NULLABLE | Updated timestamp |

**Indexes:** `(instructor_id, type)`, `(instructor_id, date)`, `(instructor_id, category)`

**Relationships:**
- `instructor_finances.instructor_id` → `instructors.id` (CASCADE DELETE)

**Notes:**
- Category slugs are config-backed (`config/finances.php`), not enum'd in the DB — lists can grow without migration. Validation at controller level gates the slug by `type`.
- Receipts live on the private S3 disk. The `receipt_url` accessor returns a time-limited signed URL (TTL from `config('finances.receipt.signed_url_ttl_minutes')`).
- Existing pre-migration rows were backfilled to `category = 'none'`.

---

### 23. **mileage_logs**

Separate ledger for instructor mileage — not a sub-type of `instructor_finances`. Business vs personal classification for HMRC tax reporting. Fuel expenses are logged independently in `instructor_finances` with `category = 'fuel'`; there is no foreign-key link between the two.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Mileage log ID |
| `instructor_id` | bigint unsigned | FOREIGN KEY → instructors.id, CASCADE DELETE, INDEXED | Owning instructor |
| `date` | date | NOT NULL | Date of the trip |
| `start_mileage` | int unsigned | NOT NULL | Starting odometer reading |
| `end_mileage` | int unsigned | NOT NULL | Ending odometer reading (must be ≥ start_mileage — enforced in controller) |
| `miles` | int unsigned | NOT NULL | Denormalised `end - start`, set server-side |
| `type` | enum('business','personal') | NOT NULL | Trip classification |
| `notes` | text | NULLABLE | Optional notes |
| `created_at` | timestamp | NULLABLE | Created timestamp |
| `updated_at` | timestamp | NULLABLE | Updated timestamp |

**Indexes:** `(instructor_id, date)`, `(instructor_id, type)`

**Relationships:**
- `mileage_logs.instructor_id` → `instructors.id` (CASCADE DELETE)

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

### 28. **resource_watches**

Tracks which resources a user has marked as watched. Used to display "Watched" badges in the student mobile app resource library.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| `user_id` | bigint unsigned | FOREIGN KEY → users(id) ON DELETE CASCADE | The user who watched |
| `resource_id` | bigint unsigned | FOREIGN KEY → resources(id) ON DELETE CASCADE | The resource that was watched |
| `created_at` | timestamp | NULLABLE | When the resource was marked as watched |

**Indexes:**
- Unique on `(user_id, resource_id)` (prevents duplicates, enables idempotent marking)

**Relationships:**
- Belongs to `User`
- Belongs to `Resource`

---

### 29. **discount_codes**

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

### 5. Student Transfer Flow (Owner Only)

Moves a student from one instructor to another. No money is moved at transfer time — Stripe routing happens later when each lesson is signed off, based on the lesson row's current `instructor_id`. Past completed lessons and their `Payout` records stay attached to the original instructor (immutable financial history).

1. Owner picks a Student and a destination Instructor on `/student-transfers`. Destination dropdown is filtered to instructors with `payouts_enabled = true AND stripe_account_id IS NOT NULL` so the next lesson sign-off can complete a Stripe Transfer.
2. In a single DB transaction:
   - All future lessons (`date >= today AND no Payout record`) currently with the source instructor are re-pointed: `lessons.instructor_id` → destination.
   - `students.instructor_id` → destination.
3. **`orders.instructor_id` is intentionally NOT updated** — it records who originated the sale (sales attribution) and stays as historical fact. Money attribution is handled per-lesson via `Payout.instructor_id`.
4. Three `activity_logs` rows are written (polymorphic) with shared metadata `{from_instructor_id, to_instructor_id, transferred_by_user_id, affected_lesson_ids, clashing_lesson_ids}`:
   - On the Student (`category = 'instructor_transfer'`)
   - On the source Instructor (`category = 'student_lost'`)
   - On the destination Instructor (`category = 'student_gained'`)
5. Three queued email notifications fire (student, source instructor, destination instructor). The destination instructor's email lists any lessons that clash with their existing diary so they can rebook.
6. **Sign-off mechanics enforce correctness automatically:**
   - Weekly lessons cannot be signed off until paid → no orphaned earnings on moved lessons.
   - `CreateLessonPayoutAction` reads `$lesson->instructor` at the moment of sign-off → the source instructor cannot draw down on a lesson now owned by the destination.

No new tables and no schema changes — the feature reuses `lessons.instructor_id`, `students.instructor_id`, and `activity_logs`.

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

---

## Mock Test System Tables

### mock_test_questions

Theory test question bank imported from DVSA-style Excel files. ~2,923 questions across 4 categories.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| item_code | varchar(20) | No | Original question code (e.g. AB2001). Unique index. |
| category | varchar(50) | No | Top-level category: Car, ADI, Motorcycle, LGV-PCV. Indexed. |
| topic | varchar(100) | No | Sub-category/topic (e.g. "Alertness", "Road and traffic signs"). Indexed. |
| stem | text | No | The question text |
| option_a | text | Yes | Option A text (null if image-only option) |
| option_b | text | Yes | Option B text |
| option_c | text | Yes | Option C text |
| option_d | text | Yes | Option D text |
| correct_answer | char(1) | No | A, B, C, or D |
| explanation | text | Yes | Explanation shown after answering |
| stem_image | varchar(255) | Yes | Image filename for the question |
| option_a_image | varchar(255) | Yes | Image filename for option A |
| option_b_image | varchar(255) | Yes | Image filename for option B |
| option_c_image | varchar(255) | Yes | Image filename for option C |
| option_d_image | varchar(255) | Yes | Image filename for option D |
| created_at | timestamp | Yes | |
| updated_at | timestamp | Yes | |

**Relationships:** HasMany → MockTestAnswer

### mock_tests

One row per test attempt by a student. Stores the final score and pass/fail result.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| student_id | bigint FK | No | References students.id. Cascade on delete. |
| category | varchar(50) | No | Which question bank was used. Indexed. |
| topic | varchar(100) | Yes | If filtered to a specific topic (null = mixed). Indexed. |
| total_questions | smallint unsigned | No | Number of questions (default 50) |
| correct_answers | smallint unsigned | No | Final correct count (default 0) |
| passed | boolean | No | Whether score >= pass mark (default false) |
| started_at | timestamp | No | When the test was started |
| completed_at | timestamp | Yes | When submitted (null = in progress) |
| created_at | timestamp | Yes | |
| updated_at | timestamp | Yes | |

**Indexes:** (student_id, completed_at) composite index for summary queries.
**Relationships:** BelongsTo → Student, HasMany → MockTestAnswer

### mock_test_answers

Individual answers per test. Enables category performance tracking and test review.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| mock_test_id | bigint FK | No | References mock_tests.id. Cascade on delete. |
| mock_test_question_id | bigint FK | No | References mock_test_questions.id. Cascade on delete. |
| selected_answer | char(1) | No | What the student chose (A/B/C/D) |
| is_correct | boolean | No | Whether the answer was correct |
| created_at | timestamp | Yes | |
| updated_at | timestamp | Yes | |

**Unique constraint:** (mock_test_id, mock_test_question_id) — one answer per question per test.
**Relationships:** BelongsTo → MockTest, BelongsTo → MockTestQuestion

---

### hazard_perception_videos

Hazard perception video clips. Each clip has 1 or 2 developing hazards with scored timing windows. Videos are categorised by category (Car, ADI, Motorcycle, LGV-PCV) and topic for filtered browsing.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| title | varchar(255) | No | Video title |
| description | text | Yes | Brief description of the clip scenario |
| category | varchar(50) | No | Category: Car, ADI, Motorcycle, LGV-PCV |
| topic | varchar(100) | No | Topic within category (e.g., Junctions, Roundabouts) |
| video_url | varchar(255) | No | Path/URL to the video file |
| duration_seconds | int unsigned | No | Video length in seconds |
| hazard_1_start | decimal(6,2) | No | Seconds when hazard 1 scoring window opens |
| hazard_1_end | decimal(6,2) | No | Seconds when hazard 1 scoring window closes |
| hazard_2_start | decimal(6,2) | Yes | Seconds when hazard 2 scoring window opens (double hazard only) |
| hazard_2_end | decimal(6,2) | Yes | Seconds when hazard 2 scoring window closes (double hazard only) |
| is_double_hazard | boolean | No | Whether this clip has two hazards (default false) |
| thumbnail_url | varchar(255) | Yes | Optional thumbnail image URL |
| created_at | timestamp | Yes | |
| updated_at | timestamp | Yes | |

**Indexes:** category, topic, is_double_hazard
**Relationships:** HasMany → HazardPerceptionAttempt

### hazard_perception_attempts

Records each student attempt at a hazard perception video. Stores response times and calculated scores per hazard.

Scoring: Each hazard's timing window is divided into 5 equal bands. Responding in band 1 (earliest) = 5 points, band 5 (latest) = 1 point, outside window = 0 points. Single hazard clips max 5 points, double hazard clips max 10 points.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| student_id | bigint FK | No | References students.id. Cascade on delete. |
| hazard_perception_video_id | bigint FK | No | References hazard_perception_videos.id. Cascade on delete. |
| hazard_1_response_time | decimal(6,2) | Yes | Seconds into video when student flagged hazard 1 (null = missed) |
| hazard_1_score | tinyint unsigned | No | Score 0-5 for hazard 1 (default 0) |
| hazard_2_response_time | decimal(6,2) | Yes | Seconds into video when student flagged hazard 2 (null = missed or single hazard) |
| hazard_2_score | tinyint unsigned | Yes | Score 0-5 for hazard 2 (null if single hazard clip) |
| total_score | tinyint unsigned | No | Combined score: h1 + h2 (max 5 single, max 10 double) |
| completed_at | timestamp | Yes | When attempt was completed |
| created_at | timestamp | Yes | |
| updated_at | timestamp | Yes | |

**Indexes:** (student_id, hazard_perception_video_id) composite index
**Relationships:** BelongsTo → Student, BelongsTo → HazardPerceptionVideo

## Progress Tracker Tables

Per-instructor progress tracking framework. Each instructor owns their own editable copy of a default template (seeded from `config/progress_tracker.php` on instructor creation and via `php artisan progress-tracker:backfill`). Students are scored 1–5 on each subcategory. Soft deletes on categories/subcategories preserve historical student scores while removing the items from the instructor's editable framework.

### progress_categories

Top-level progress-tracker category, owned by a single instructor.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| instructor_id | bigint FK | No | References instructors.id. Cascade on delete. |
| name | varchar(100) | No | Category name (e.g. "Junctions") |
| sort_order | int unsigned | No | Display order within the instructor's framework (default 0) |
| created_at / updated_at | timestamp | Yes | |
| deleted_at | timestamp | Yes | Soft delete — hides from framework but keeps history |

**Indexes:** (instructor_id, sort_order) composite
**Relationships:** BelongsTo → Instructor, HasMany → ProgressSubcategory

### progress_subcategories

Second-level item inside a category. Scoring happens at this level.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| progress_category_id | bigint FK | No | References progress_categories.id. Cascade on delete. |
| name | varchar(100) | No | Subcategory name (e.g. "Left Turn") |
| sort_order | int unsigned | No | Display order within the category (default 0) |
| created_at / updated_at | timestamp | Yes | |
| deleted_at | timestamp | Yes | Soft delete — hides from framework but keeps history |

**Indexes:** (progress_category_id, sort_order) composite
**Relationships:** BelongsTo → ProgressCategory, HasMany → StudentProgress

### student_progress

One row per (student, subcategory) pair. Upserted on save — no history is kept.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| student_id | bigint FK | No | References students.id. Cascade on delete. |
| progress_subcategory_id | bigint FK | No | References progress_subcategories.id. Cascade on delete. |
| score | tinyint unsigned | No | 1 = Introduced, 2 = Instructed, 3 = Prompted, 4 = Seldom prompted, 5 = Independent |
| created_at / updated_at | timestamp | Yes | |

**Indexes:** UNIQUE (student_id, progress_subcategory_id) — also the upsert target
**Relationships:** BelongsTo → Student, BelongsTo → ProgressSubcategory
**Business rule:** `SaveStudentProgressAction` silently ignores attempts to score against a soft-deleted subcategory or one that does not belong to the student's current instructor.

---

## HMRC MTD Integration Tables (Phase 1: OAuth foundation)

These tables back the OAuth + Hello World round-trip against HMRC's sandbox. All HMRC token material is encrypted at rest via Laravel's `encrypted` cast on top of the application key.

### hmrc_oauth_states

Short-lived per-user CSRF + PKCE state for the in-flight OAuth handshake. One row is created at the start of an authorization request and deleted on successful exchange (or swept after expiry).

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| state | string | No | Opaque CSRF token returned by HMRC on callback. **Unique.** |
| code_verifier | text | No | PKCE verifier paired with the stored `state`. |
| scopes | json | No | Array of scopes requested for this authorization. |
| redirect_uri | string | No | Snapshot of the redirect URI used (must match the token exchange). |
| expires_at | timestamp | No | Typically ~10 minutes from creation. Indexed for sweep. |
| created_at | timestamp | Yes | Created timestamp. |

**Indexes:** UNIQUE (state); INDEX (expires_at)
**Relationships:** BelongsTo → User

### hmrc_tokens

The instructor's persistent HMRC OAuth token pair. Single row per user (UNIQUE on `user_id`). Access and refresh tokens are encrypted at rest.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. **Unique.** Cascade on delete. |
| access_token | text (encrypted) | No | Bearer token for HMRC API calls. ~4-hour lifetime. |
| refresh_token | text (encrypted) | No | Refresh token. ~18-month lifetime, rotated on every refresh. |
| token_type | string | No | Defaults to `bearer`. |
| scopes | json | No | Array of scopes currently granted by HMRC. |
| expires_at | timestamp | No | Access-token expiry. |
| refresh_expires_at | timestamp | No | Refresh-token expiry — instructor must re-auth after this. |
| last_refreshed_at | timestamp | Yes | Set every time the access token is refreshed. |
| last_expiry_warning_at | timestamp | Yes | Used by `MonitorHmrcTokenExpiry` to dedupe T-30/T-7 warnings. |
| connected_at | timestamp | No | First successful connection time (preserved across refreshes). |
| created_at / updated_at | timestamp | Yes | |

**Indexes:** UNIQUE (user_id)
**Relationships:** BelongsTo → User; HasMany → HmrcTokenRefreshLog

### hmrc_device_identifiers

Stable per-user device identifier used for HMRC's `Gov-Client-Device-ID` fraud-prevention header. **Persists across token churn** — kept in its own table so disconnect/reconnect does not mint a new device ID. Mirrored to a long-lived secure cookie (`hmrc_device_id`).

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. **Unique.** Cascade on delete. |
| device_id | uuid | No | UUID generated client-side on first OAuth visit, mirrored server-side on first sight. |
| first_seen_at | timestamp | No | When the row was created. |
| last_seen_at | timestamp | No | Updated on every interactive HMRC action. |
| created_at / updated_at | timestamp | Yes | |

**Indexes:** UNIQUE (user_id)
**Relationships:** BelongsTo → User

### hmrc_token_refresh_logs

Append-only log of every refresh attempt for ops monitoring. Powers the failure-rate dashboard and per-user diagnostics.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| outcome | enum | No | `success`, `failure_invalid_grant`, `failure_network`, `failure_other`. |
| error_code | string | Yes | HMRC error code if HMRC returned one. |
| attempted_at | timestamp | No | Time of the refresh attempt. |
| created_at / updated_at | timestamp | Yes | |

**Indexes:** INDEX (outcome, attempted_at)
**Relationships:** BelongsTo → User

### hmrc_client_fingerprints

Browser-side device fingerprint captured immediately before any interactive HMRC call requiring fraud-prevention headers. One row per `hmrc_token_id`; refreshed on each interactive submit-flow entry so the sent values reflect the device the instructor is actually using right now.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| hmrc_token_id | bigint FK | No | Unique. References hmrc_tokens.id. Cascade on delete. |
| screens | json | No | Array of `{width, height, scaling-factor, colour-depth}` — one entry per monitor. |
| window_size | json | No | `{width, height}` of the browser window when captured. |
| timezone | json | No | `{iana, offset_minutes}` — server formats `UTC±hh:mm` for `Gov-Client-Timezone`. |
| browser_user_agent | text | No | `navigator.userAgent` from the originating browser, sent as `Gov-Client-Browser-JS-User-Agent`. |
| captured_at | timestamp | No | When the fingerprint was collected. Used to enforce freshness on submit flows. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (hmrc_token_id)
**Relationships:** BelongsTo → HmrcToken (and via HmrcToken to User)
**Notes:** WEB_APP_VIA_SERVER does not require `Local-IPs`, `Browser-Plugins`, or `Browser-Do-Not-Track`, so we don't capture them. See `.claude/hmrc-fraud-headers.md`.

### hmrc_itsa_businesses

Cache of self-employment / property businesses returned by HMRC's Business Details API. Refreshed by `SyncHmrcItsaObligations` daily and on demand when the user opens the ITSA page.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| instructor_id | bigint FK | Yes | References instructors.id. Null on delete (we keep the audit row even if the instructor profile is detached). |
| business_id | string(64) | No | HMRC's businessId. Indexed. |
| type_of_business | string(32) | No | `self-employment` / `uk-property` / `foreign-property`. |
| trading_name | string(160) | Yes | Business trading name. |
| accounting_type | string(16) | Yes | `CASH` / `ACCRUALS`. |
| commencement_date | date | Yes | When the business started. |
| cessation_date | date | Yes | When the business ended (if ceased). |
| latency_details | json | Yes | HMRC's `latencyDetails` block — opaque structure used by HMRC for back-period eligibility. |
| last_synced_at | timestamp | No | When this row was last refreshed from HMRC. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, business_id)
**Relationships:** BelongsTo → User, BelongsTo → Instructor

### hmrc_itsa_obligations

Cached open and recently-fulfilled quarterly obligations. Drives the deadline countdown banner on the dashboard, the Phase 3f reminder cron, and the obligations list on the ITSA page.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| business_id | string(64) | No | HMRC businessId. Indexed. |
| period_key | string(64) | No | HMRC's identifier for the obligation period. Indexed. |
| period_start_date | date | No | First day of the period. |
| period_end_date | date | No | Last day of the period. |
| due_date | date | No | When the quarterly update must be filed. |
| received_date | date | Yes | When HMRC marked the obligation fulfilled. |
| status | string(16) | No | `Open` / `Fulfilled`. |
| obligation_type | string(64) | No | Defaults to `Quarterly Update`. |
| last_reminder_sent_at | timestamp | Yes | Used by the reminder cron to dedupe sends within a deadline window. |
| last_synced_at | timestamp | No | When this row was last refreshed from HMRC. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, business_id, period_key, obligation_type)
**Indexes:** INDEX (status, due_date)
**Relationships:** BelongsTo → User; soft-relates to HmrcItsaBusiness via (user_id, business_id).

### hmrc_itsa_quarterly_updates

Permanent audit record per quarterly submission. The row's current values represent the *latest* state of that period (post-amendments); the immutable history is in `hmrc_itsa_quarterly_update_revisions`.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| instructor_id | bigint FK | Yes | References instructors.id. Null on delete. |
| business_id | string(64) | No | HMRC businessId. Indexed. |
| period_key | string(64) | No | HMRC's period identifier. Indexed. |
| period_start_date | date | No | First day of the period. |
| period_end_date | date | No | Last day of the period. |
| turnover_pence | bigInteger | No | Income — turnover in pence. Default 0. |
| other_income_pence | bigInteger | No | Income — other in pence. Default 0. |
| consolidated_expenses_pence | bigInteger | Yes | Mutually exclusive with itemised expense fields below. |
| cost_of_goods_pence | bigInteger | Yes | Itemised. |
| payments_to_subcontractors_pence | bigInteger | Yes | Itemised. |
| wages_and_staff_costs_pence | bigInteger | Yes | Itemised. |
| car_van_travel_expenses_pence | bigInteger | Yes | Itemised. |
| premises_running_costs_pence | bigInteger | Yes | Itemised. |
| maintenance_costs_pence | bigInteger | Yes | Itemised. |
| admin_costs_pence | bigInteger | Yes | Itemised. |
| business_entertainment_costs_pence | bigInteger | Yes | Itemised. |
| advertising_costs_pence | bigInteger | Yes | Itemised. |
| interest_on_bank_other_loans_pence | bigInteger | Yes | Itemised. |
| finance_charges_pence | bigInteger | Yes | Itemised. |
| irrecoverable_debts_pence | bigInteger | Yes | Itemised. |
| professional_fees_pence | bigInteger | Yes | Itemised. |
| depreciation_pence | bigInteger | Yes | Itemised. |
| other_expenses_pence | bigInteger | Yes | Itemised. |
| submission_id | string(128) | Yes | HMRC's submission identifier from the most recent successful submit/amend. |
| correlation_id | string(128) | Yes | HMRC's `X-CorrelationId` for support traceability. |
| submitted_at | timestamp | Yes | When the latest successful submit/amend completed. |
| request_payload | json | Yes | Last request JSON sent to HMRC. |
| response_payload | json | Yes | Last response JSON received from HMRC. |
| digital_records_attested_at | timestamp | Yes | Last time the user ticked the digital-records attestation. |
| digital_records_attested_by_user_id | bigint FK | Yes | Whoever clicked submit (supports staff-assisted submissions). Null on delete. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, business_id, period_key)
**Relationships:** BelongsTo → User; BelongsTo → Instructor; HasMany → HmrcItsaQuarterlyUpdateRevision.

### hmrc_itsa_quarterly_update_revisions

Append-only audit trail for the 6-year retention requirement. Every successful submission writes revision 1; every amendment writes revision N+1; failed submissions/amendments are recorded too with `kind` = `failed_*`. **Never updated, never deleted.**

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| quarterly_update_id | bigint FK | No | References hmrc_itsa_quarterly_updates.id. Cascade on delete. |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| revision_number | unsigned int | No | 1, 2, 3, ... per parent row. |
| kind | string(32) | No | `submission`, `amendment`, `failed_submission`, `failed_amendment`. |
| request_payload | json | No | Exact payload sent to HMRC. |
| response_payload | json | Yes | Exact response received (null only if a network error prevented even getting one). |
| submission_id | string(128) | Yes | HMRC's submission identifier when present. |
| correlation_id | string(128) | Yes | HMRC's `X-CorrelationId`. |
| submitted_at | timestamp | No | When this revision was attempted. |
| submitted_by_user_id | bigint FK | No | Whoever clicked submit/amend. Cascade on user delete. |
| digital_records_attested_at | timestamp | Yes | Attestation timestamp captured per revision. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (quarterly_update_id, revision_number)
**Indexes:** INDEX (user_id, submitted_at) — used by the audit-log export.
**Relationships:** BelongsTo → HmrcItsaQuarterlyUpdate, BelongsTo → User (twice — owner and submitter).

### hmrc_itsa_calculations

Triggered tax calculations (Phase 3.5). One row per `(user, calculation_id)`. HMRC issues a fresh calculationId for every trigger; we keep the history for audit and to let the user re-review prior calculations before submitting the final declaration. Phase 3.5 only triggers the `finalDeclaration` flavour, but the schema holds the other variants for future preview/in-year features.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| nino | string(32) | No | Frozen at trigger time so a profile change doesn't break re-fetch. |
| tax_year | string(16) | No | HMRC's dash form, e.g. `2025-26`. |
| calculation_id | string(128) | No | HMRC's identifier returned by the trigger endpoint. |
| calculation_type | string(32) | No | Cast to `ItsaCalculationType` (`inYear`, `intentToCrystallise`, `crystallisation`, `finalDeclaration`). |
| status | string(16) | No | Cast to `ItsaCalculationStatus` (`pending`, `processed`, `errored`). |
| triggered_at | timestamp | No | When the trigger POST returned 202. |
| processed_at | timestamp | Yes | First time we observed `IS_PROCESSED`. |
| summary_payload | json | Yes | The `liabilityAndCalculation` block (or equivalent) from HMRC. |
| detail_payload | json | Yes | The full retrieve response, kept as the source for future drill-downs. |
| error_payload | json | Yes | HMRC `errors[]` when status is `errored`. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, calculation_id)
**Indexes:** INDEX (calculation_id), INDEX (user_id, tax_year)
**Relationships:** BelongsTo → User; HasOne → HmrcItsaFinalDeclaration via `calculation_id`.

### hmrc_itsa_supplementary_data

Latest figures for each supplementary submission type per (user, tax year). HMRC accepts repeat PUTs that overwrite the previous figures — we mirror this by upserting on the unique key. The full v1 form payload lives in `payload` so adding new fields later doesn't require a migration.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| tax_year | string(16) | No | HMRC's dash form, e.g. `2025-26`. |
| type | string(32) | No | Cast to `ItsaSupplementaryType` (`reliefs`, `disclosures`, `savings`, `dividends`, `individual_details`). |
| payload | json | No | Exact JSON we PUT to HMRC for this type. |
| submission_id | string(128) | Yes | When HMRC echoes one. |
| correlation_id | string(128) | Yes | HMRC's `X-CorrelationId`. |
| submitted_at | timestamp | Yes | Last successful PUT for this row. |
| response_payload | json | Yes | Last response body for the row. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, tax_year, type)
**Relationships:** BelongsTo → User.

### hmrc_itsa_final_declarations

Permanent record of a Final Declaration submission. Created only on a successful `POST /final-declaration`. Failed attempts are reflected on the linked `hmrc_itsa_calculations` row's `error_payload` rather than as a row here. 6-year retention.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| nino | string(32) | No | Frozen at submission time. |
| tax_year | string(16) | No | HMRC's dash form. |
| calculation_id | bigint FK | Yes | References hmrc_itsa_calculations.id. NullOnDelete. |
| submitted_at | timestamp | No | When HMRC accepted the declaration. |
| correlation_id | string(128) | Yes | HMRC's `X-CorrelationId` for audit. |
| request_payload | json | Yes | We POST an empty body — the calculationId we asserted is captured here for the audit trail. |
| response_payload | json | Yes | Full response body (HMRC returns 204 with headers; we capture what we have). |
| digital_records_attested_at | timestamp | Yes | Attestation timestamp captured at submit. |
| digital_records_attested_by_user_id | bigint FK | Yes | Whoever clicked submit. NullOnDelete. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, tax_year)
**Relationships:** BelongsTo → User, BelongsTo → HmrcItsaCalculation (`calculation_id`), BelongsTo → User (`digital_records_attested_by_user_id`).

### hmrc_vat_obligations

Cached VAT obligations refreshed by the daily `SyncHmrcItsaObligations` cron (which despite its name covers both ITSA and VAT for connected, scope-granted instructors). Drives the open-obligations list and the deadline-reminder notifications.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| vrn | string(9) | No | Indexed. The instructor's VAT number. |
| period_key | string(16) | No | HMRC's identifier for the period — used in submit/retrieve. |
| period_start_date | date | No | |
| period_end_date | date | No | |
| due_date | date | No | |
| received_date | date | Yes | Populated once HMRC marks the obligation `Fulfilled`. |
| status | string(16) | No | `Open` or `Fulfilled`. |
| last_reminder_sent_at | timestamp | Yes | Idempotency for the 30/14/7/1-day reminder cron. |
| last_synced_at | timestamp | No | Last sync from HMRC. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, vrn, period_key)
**Indexes:** INDEX (status, due_date) for the dashboard banner / reminder queries.
**Relationships:** BelongsTo → User.

### hmrc_vat_returns

Permanent audit record of a 9-box VAT return submission. **Immutable on HMRC's side** — there is no amendment endpoint, so this row is the single source of truth and corrections happen via a future-period adjustment. 6-year retention.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | bigint PK | No | Auto-increment |
| user_id | bigint FK | No | References users.id. Cascade on delete. |
| instructor_id | bigint FK | Yes | References instructors.id. NullOnDelete. |
| vrn | string(9) | No | Indexed. |
| period_key | string(16) | No | Indexed. |
| vat_due_sales_pence | bigint | No | Box 1 — VAT due on sales. |
| vat_due_acquisitions_pence | bigint | No | Box 2 — VAT due on EU acquisitions (typically 0 for sole-trader DI). |
| total_vat_due_pence | bigint | No | Box 3 = Box 1 + Box 2. |
| vat_reclaimed_curr_period_pence | bigint | No | Box 4 — VAT reclaimed on purchases. |
| net_vat_due_pence | bigint | No | Box 5 = abs(Box 3 − Box 4). Non-negative. |
| total_value_sales_ex_vat_pence | bigint | No | Box 6 (whole pounds at HMRC; stored in pence here). |
| total_value_purchases_ex_vat_pence | bigint | No | Box 7 (whole pounds). |
| total_value_goods_supplied_ex_vat_pence | bigint | No | Box 8 (whole pounds). |
| total_acquisitions_ex_vat_pence | bigint | No | Box 9 (whole pounds). |
| finalised | boolean | No | Always `true` in v1 — HMRC's binding declaration that the return is final. |
| submitted_at | timestamp | Yes | When HMRC accepted the return. |
| processing_date | timestamp | Yes | HMRC's `processingDate` from the response. |
| form_bundle_number | string(32) | Yes | HMRC's `formBundleNumber` for charge tracking. |
| charge_ref_number | string(32) | Yes | When HMRC echoes one (often present when net VAT > 0). |
| payment_indicator | string(8) | Yes | HMRC's `paymentIndicator` (`DD` for direct debit, etc.). |
| correlation_id | string(128) | Yes | HMRC's `X-CorrelationId`. |
| request_payload | json | Yes | Exact payload sent to HMRC (audit). |
| response_payload | json | Yes | Exact response body received. |
| digital_records_attested_at | timestamp | Yes | Attestation captured at submit. |
| digital_records_attested_by_user_id | bigint FK | Yes | Whoever clicked submit. NullOnDelete. |
| created_at / updated_at | timestamp | Yes | |

**Constraints:** UNIQUE (user_id, vrn, period_key) — represents the single authoritative submission per period.
**Relationships:** BelongsTo → User, BelongsTo → Instructor, BelongsTo → User (`digital_records_attested_by_user_id`).
