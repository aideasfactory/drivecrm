# Database Schema Documentation

This document provides a comprehensive overview of the database structure for the Stripe Payment Learning Management System.

## 🔍 Quick Reference

**Core Models:**
- `User` → `Instructor` or `Student` (polymorphic via `role`)
- `Instructor` → Creates `Packages`, Teaches `Lessons`, Receives `Payouts`
- `Student` → Purchases `Orders` → Contains `Lessons`
- `Order` = Student + Instructor + Package

**Key Relationships:**
```
User (instructor) → Instructor → Packages
                              → Orders (assigned)
                              → Lessons (teaches)
                              → Payouts (receives)
x
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
Users (1) ──┬── (1) Instructors ──┬── (Many) Packages
            │                     │
            │                     ├── (Many) Orders
            │                     │
            │                     ├── (Many) Lessons ────┐
            │                     │                      │
            │                     ├── (Many) Payouts     │
            │                     │                      │
            │                     └── (Many) Calendars ──┼── (Many) CalendarItems
            │                                            │
            └── (1) Students ──── (Many) Orders ──── (Many) Lessons ──┬── (1) LessonPayments
                      │                  │              │              │
                      │                  │              └──────────────┤
                      │                  │                             └── (1) Payouts
                      │                  │
                      └─────────────┐    └── (1) Packages
                                    │
                          Instructors (Many)
```

---

## Key Tables & Relationships

### Core Entities

1. **users** - Central user table
   - Polymorphic based on `role` enum: `owner`, `instructor`, `student`
   - Has one-to-one relationship with either `instructors` or `students` table

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

7. **lesson_payments** - Payment tracking (weekly mode)
   - One-to-one with `lessons` (payment for specific lesson)

8. **payouts** - Instructor payments
   - One-to-one with `lessons` (payout for completed lesson)
   - Many-to-one with `instructors` (recipient)

### Support Tables

9. **webhook_events** - Stripe webhook logging
10. **password_reset_tokens** - Laravel password resets
11. **sessions** - Laravel session storage
12. **cache** - Laravel cache
13. **jobs** - Laravel queue jobs

### Relationship Summary

```
User (role=instructor) → Instructor → Creates Packages
                                   → Assigned to Orders
                                   → Conducts Lessons
                                   → Receives Payouts

User (role=student) → Student → Purchases Orders → Contains Lessons → Has LessonPayments
                             → Assigned to Instructor                → Has Payouts

Package → Used in Orders

Order = Student + Instructor + Package → Creates Lessons
```

---

## Tables

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
| `remember_token` | varchar(100) | NULLABLE | Remember me token |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Relationships:**
- Has one `Instructor` profile (if role is instructor)
- Has one `Student` profile (if role is student)
- Has many `Orders` (through Student)

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
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Indexes:**
- `instructor_id`

**Relationships:**
- Belongs to one `User`
- Belongs to one `Instructor` (optional - assigned instructor)
- Has many `Orders`

**Business Logic:**
- Students can be assigned to a specific instructor
- Students inherit instructor assignments from their orders

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
| `package_id` | bigint unsigned | FOREIGN KEY (packages.id), ON DELETE CASCADE | Purchased package |
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
- Belongs to one `Package`
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
| `status` | enum('pending', 'completed', 'cancelled') | DEFAULT 'pending' | Lesson status |
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
- Status: `pending`, `completed`, `cancelled`

**Business Logic:**
- Created automatically when order is activated
- Number of lessons matches the package's `lessons_count`
- Scheduling information (date, start_time, end_time) can be set when booking lesson
- Links to calendar_item for slot availability tracking
- Instructor gets paid after lesson is completed

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

### 9. **webhook_events**

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

### 10. **password_reset_tokens**

Laravel's password reset token storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `email` | varchar(255) | PRIMARY KEY | User's email |
| `token` | varchar(255) | NOT NULL | Reset token |
| `created_at` | timestamp | NULLABLE | Token creation time |

---

### 11. **enquiries** (New - Phase 1 Onboarding)

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

### 12. **locations** (New - Phase 1 Onboarding)

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

### 13. **calendars** (New - Phase 1 Onboarding)

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

### 14. **calendar_items** (New - Phase 1 Onboarding)

Defines time slots within a calendar date.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PRIMARY KEY, AUTO_INCREMENT | Unique calendar item identifier |
| `calendar_id` | bigint unsigned | FOREIGN KEY (calendars.id), ON DELETE CASCADE | Reference to calendar |
| `start_time` | time | NOT NULL | Slot start time |
| `end_time` | time | NOT NULL | Slot end time |
| `is_available` | boolean | DEFAULT true | Availability flag |
| `created_at` | timestamp | - | Record creation timestamp |
| `updated_at` | timestamp | - | Record update timestamp |

**Relationships:**
- Belongs to one `Calendar`

**Business Logic:**
- Multiple time slots per calendar date
- `is_available` allows blocking slots without deletion

---

---

### 15. **sessions**

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

### 16. **cache**

Laravel's cache storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `key` | varchar(255) | PRIMARY KEY | Cache key |
| `value` | mediumtext | NOT NULL | Cached value |
| `expiration` | integer | NOT NULL | Expiration timestamp |

---

### 17. **cache_locks**

Laravel's cache locking mechanism.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `key` | varchar(255) | PRIMARY KEY | Lock key |
| `owner` | varchar(255) | NOT NULL | Lock owner identifier |
| `expiration` | integer | NOT NULL | Lock expiration timestamp |

---

### 18. **jobs**

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

### 19. **job_batches**

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

### 20. **failed_jobs**

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
- **webhook_events:** `type`
- **enquiries:** None (uses UUID primary key)
- **locations:** `postcode_sector`
- **calendars:** Unique constraint on `(instructor_id, date)`
- **calendar_items:** None
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
