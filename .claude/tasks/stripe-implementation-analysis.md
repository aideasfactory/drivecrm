# Stripe Implementation Analysis - v1 System

**Document Created:** 2026-02-10
**Purpose:** Comprehensive analysis of v1 Stripe implementation for reference when building v2

---

## 📋 Executive Summary

The v1 system implements a **Stripe Connect Express** platform where:
- **Platform** acts as the payment facilitator (holds funds)
- **Instructors** have Stripe Connect Express accounts
- **Students** pay upfront or weekly for lesson packages
- **Lessons** are completed by instructors who then receive payouts via Stripe Transfers
- **Webhooks** handle asynchronous payment confirmations and account updates

---

## 🏗️ System Architecture

### Payment Flow Overview

```
┌─────────────────────────────────────────────────────────────┐
│                      STRIPE INTEGRATION                      │
└─────────────────────────────────────────────────────────────┘

1. INSTRUCTOR ONBOARDING
   └─> Create Stripe Connect Account (Express)
   └─> Generate Account Link for onboarding
   └─> Instructor completes Stripe verification
   └─> Webhook updates instructor status
   └─> stripe_account_id stored in DB

2. PACKAGE CREATION
   └─> Instructor creates package (name, price, lesson count)
   └─> Create Stripe Product
   └─> Create Stripe Price (for upfront payment)
   └─> stripe_product_id & stripe_price_id stored in DB

3. STUDENT CHECKOUT (Upfront)
   └─> Student selects package
   └─> Create/Retrieve Stripe Customer
   └─> Create Checkout Session
   └─> Student pays via Stripe Checkout
   └─> Webhook creates lessons in DB
   └─> Order status = ACTIVE

4. LESSON COMPLETION & PAYOUT
   └─> Instructor marks lesson complete
   └─> Create Stripe Transfer to instructor's connected account
   └─> Payout record created with transfer_id
   └─> Lesson status = COMPLETED
   └─> If all lessons complete → Order status = COMPLETED
```

---

## 🔐 Stripe Components Used

### 1. Stripe Connect (Express Accounts)
**Purpose:** Enable instructors to receive payouts
**Type:** Express accounts (Stripe handles onboarding UI)
**Capabilities:** Transfers (receive funds from platform)

### 2. Stripe Products & Prices
**Purpose:** Represent lesson packages in Stripe
**Structure:**
- **Product:** Package metadata (name, description)
- **Price:** Fixed price for upfront payment mode

### 3. Stripe Customers
**Purpose:** Represent students in Stripe
**Storage:** `stripe_customer_id` on User model

### 4. Stripe Checkout Sessions
**Purpose:** Handle student payments
**Mode:** `payment` (one-time upfront payment)
**Metadata:** Includes `order_id`, `package_id`, `student_id`

### 5. Stripe Transfers
**Purpose:** Pay instructors per completed lesson
**Destination:** Instructor's connected account
**Metadata:** Includes `lesson_id`, `order_id`, `instructor_id`

### 6. Stripe Webhooks
**Events Handled:**
- `checkout.session.completed` - Payment successful
- `payment_intent.succeeded` - Payment confirmed
- `payment_intent.payment_failed` - Payment failed
- `account.updated` - Instructor account status changed
- `invoice.paid` - Weekly payment received (future mode)
- `invoice.payment_failed` - Weekly payment failed

---

## 📊 Database Schema Structure

### Key Tables & Stripe Fields

#### **instructors**
```php
- id
- user_id
- stripe_account_id              // Stripe Connect Account ID
- onboarding_complete (boolean)  // Has completed Stripe onboarding
- charges_enabled (boolean)      // Can accept payments
- payouts_enabled (boolean)      // Can receive payouts
- bio, rating, transmission_type, status, etc.
- created_at, updated_at
```

#### **packages**
```php
- id
- instructor_id (nullable)       // NULL = platform package, ID = instructor bespoke
- name
- description
- total_price_pence              // Total package price in pence
- lessons_count                  // Number of lessons in package
- lesson_price_pence             // Price per lesson (auto-calculated)
- stripe_product_id              // Stripe Product ID
- stripe_price_id                // Stripe Price ID (for upfront payment)
- active (boolean)
- created_at, updated_at
```

#### **orders**
```php
- id
- student_id                     // User ID (student)
- instructor_id                  // Assigned instructor
- package_id                     // Package purchased
- payment_mode                   // 'upfront' or 'weekly'
- status                         // 'pending', 'active', 'completed'
- stripe_payment_intent_id       // Payment Intent from Checkout
- stripe_checkout_session_id     // Checkout Session ID
- stripe_subscription_id         // For weekly mode (future)
- created_at, updated_at
```

#### **lessons**
```php
- id
- order_id
- instructor_id
- amount_pence                   // Payment amount for this lesson
- date
- start_time
- end_time
- calendar_item_id               // Link to instructor's calendar
- completed_at
- status                         // 'pending', 'completed', 'cancelled'
- created_at, updated_at
```

#### **payouts**
```php
- id
- lesson_id
- instructor_id
- amount_pence                   // Amount transferred
- status                         // 'pending', 'paid', 'failed'
- stripe_transfer_id             // Stripe Transfer ID
- paid_at                        // When transfer completed
- created_at, updated_at
```

#### **lesson_payments** (for weekly mode)
```php
- id
- lesson_id
- amount_pence
- status                         // 'due', 'paid', 'failed', 'refunded'
- due_date
- paid_at
- stripe_invoice_id              // Stripe Invoice ID (weekly mode)
- created_at, updated_at
```

#### **webhook_events** (idempotency)
```php
- id
- stripe_event_id                // Stripe Event ID (prevents duplicate processing)
- type                           // Event type (e.g., 'checkout.session.completed')
- payload (JSON)                 // Full webhook payload
- created_at, updated_at
```

---

## 🔄 Flow #1: Instructor Onboarding

### User Flow
1. Instructor logs in
2. Navigates to `/instructor/onboarding`
3. Clicks "Start Onboarding" button
4. System creates Stripe Connect Account
5. Redirected to Stripe's hosted onboarding
6. Completes bank details, identity verification
7. Redirected back to platform
8. System checks account status
9. Instructor dashboard shows "Onboarding Complete"

### Technical Implementation

#### Controller: `InstructorOnboardingController.php`

**Route:** `POST /instructor/onboarding/start`
```php
public function start(Request $request): RedirectResponse
{
    $instructor = auth()->user()->instructor;

    // Create Stripe Connect Account
    $accountResult = $this->stripeService->createConnectAccount($instructor);
    $instructor->stripe_account_id = $accountResult['account_id'];
    $instructor->save();

    // Create Account Link
    $linkResult = $this->stripeService->createAccountLink(
        $instructor,
        route('instructor.onboarding.return'),  // Success URL
        route('instructor.onboarding.refresh')  // Refresh URL (if link expires)
    );

    return redirect($linkResult['url']);  // Redirect to Stripe
}
```

**Route:** `GET /instructor/onboarding/return`
```php
public function return(Request $request): RedirectResponse
{
    $instructor = auth()->user()->instructor;

    // Retrieve Stripe account to check status
    $account = \Stripe\Account::retrieve($instructor->stripe_account_id);

    // Update instructor status
    $instructor->onboarding_complete = $account->details_submitted;
    $instructor->charges_enabled = $account->charges_enabled;
    $instructor->payouts_enabled = $account->payouts_enabled;
    $instructor->save();

    return redirect()->route('instructor.dashboard')
        ->with('success', 'Onboarding completed successfully!');
}
```

#### Service Method: `StripeService::createConnectAccount()`
```php
public function createConnectAccount(Instructor $instructor): array
{
    $account = $this->stripe->accounts->create([
        'type' => 'express',              // Express account type
        'country' => 'GB',                // UK-based
        'email' => $instructor->user->email,
        'capabilities' => [
            'transfers' => ['requested' => true],  // Enable receiving transfers
        ],
    ]);

    return [
        'success' => true,
        'account_id' => $account->id,
        'account' => $account,
    ];
}
```

#### Service Method: `StripeService::createAccountLink()`
```php
public function createAccountLink(Instructor $instructor, string $returnUrl, string $refreshUrl): array
{
    $accountLink = $this->stripe->accountLinks->create([
        'account' => $instructor->stripe_account_id,
        'refresh_url' => $refreshUrl,     // If link expires
        'return_url' => $returnUrl,       // After completion
        'type' => 'account_onboarding',   // Onboarding flow
    ]);

    return [
        'success' => true,
        'url' => $accountLink->url,  // Stripe-hosted onboarding URL
    ];
}
```

### Webhook: `account.updated`
```php
protected function handleAccountUpdated(object $event): void
{
    $account = $event->data->object;

    $instructor = Instructor::where('stripe_account_id', $account->id)->first();

    // Update instructor status from Stripe
    $instructor->onboarding_complete = $account->details_submitted;
    $instructor->charges_enabled = $account->charges_enabled;
    $instructor->payouts_enabled = $account->payouts_enabled;
    $instructor->save();
}
```

### Key Points
- ✅ **Stripe-hosted onboarding** (no custom forms needed)
- ✅ **Express account** (fast setup, Stripe handles compliance)
- ✅ **Transfer capability** (instructor can receive funds)
- ✅ **Async status updates** via webhooks
- ✅ **Refresh link** if onboarding expires before completion

---

## 🔄 Flow #2: Package Creation in Stripe

### User Flow
1. Instructor navigates to `/instructor/packages/create`
2. Fills form: Package name, total price, lesson count
3. Submits form
4. System creates Stripe Product & Price
5. Package stored in DB with Stripe IDs
6. Redirect to packages list

### Technical Implementation

#### Controller: `InstructorPackageController::store()`
```php
public function store(Request $request): RedirectResponse
{
    $instructor = auth()->user()->instructor;

    // Check onboarding status
    if (!$instructor->onboarding_complete || !$instructor->charges_enabled) {
        return redirect()->route('instructor.onboarding.index')
            ->with('error', 'Please complete Stripe onboarding first');
    }

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'total_price_pence' => 'required|integer|min:100',
        'lessons_count' => 'required|integer|min:1|max:100',
    ]);

    DB::beginTransaction();

    // Create package record
    $package = Package::create([
        'instructor_id' => $instructor->id,
        'name' => $validated['name'],
        'total_price_pence' => $validated['total_price_pence'],
        'lessons_count' => $validated['lessons_count'],
        'active' => true,
    ]);
    // lesson_price_pence auto-calculated in model boot()

    // Create Stripe Product
    $productResult = $this->stripeService->createProduct($package);
    $package->stripe_product_id = $productResult['product_id'];
    $package->save();

    // Create Stripe Price (for upfront payment)
    $priceResult = $this->stripeService->createPrice($package);
    $package->stripe_price_id = $priceResult['price_id'];
    $package->save();

    DB::commit();

    return redirect()->route('instructor.packages.index')
        ->with('success', 'Package created successfully!');
}
```

#### Service Method: `StripeService::createProduct()`
```php
public function createProduct(Package $package): array
{
    $product = $this->stripe->products->create([
        'name' => $package->name,
        'description' => "{$package->lessons_count} driving lessons",
    ]);

    return [
        'success' => true,
        'product_id' => $product->id,  // prod_xxxxx
        'product' => $product,
    ];
}
```

#### Service Method: `StripeService::createPrice()`
```php
public function createPrice(Package $package): array
{
    $price = $this->stripe->prices->create([
        'product' => $package->stripe_product_id,
        'unit_amount' => $package->total_price_pence,  // Price in pence
        'currency' => 'gbp',
    ]);

    return [
        'success' => true,
        'price_id' => $price->id,  // price_xxxxx
        'price' => $price,
    ];
}
```

#### Model: `Package.php` Auto-Calculation
```php
protected static function boot()
{
    parent::boot();

    static::saving(function ($package) {
        if ($package->isDirty(['total_price_pence', 'lessons_count'])) {
            // Auto-calculate lesson price
            $package->lesson_price_pence = floor($package->total_price_pence / $package->lessons_count);
        }
    });
}
```

### Key Points
- ✅ **Stripe Product** represents the package
- ✅ **Stripe Price** enables Checkout Session
- ✅ **Lesson price auto-calculated** (for future per-lesson payouts)
- ✅ **Transaction safety** (DB::beginTransaction)
- ✅ **Onboarding check** (must complete before creating packages)

---

## 🔄 Flow #3: Student Checkout & Order Creation

### User Flow (Upfront Payment)
1. Student browses packages
2. Selects package → `/student/packages/{id}`
3. Clicks "Buy Now" → `POST /student/checkout/{package}`
4. System creates Order (status: PENDING)
5. System creates/retrieves Stripe Customer
6. System creates Stripe Checkout Session
7. Student redirected to Stripe Checkout
8. Student completes payment
9. Stripe webhook fires: `checkout.session.completed`
10. System creates lessons, updates order status to ACTIVE
11. Student redirected to success page

### Technical Implementation

#### Controller: `StudentCheckoutController::create()`
```php
public function create(Request $request, Package $package): RedirectResponse
{
    $student = auth()->user();
    $instructor = $package->instructor;  // Could be null for platform packages

    // Create Order record (PENDING)
    $order = Order::create([
        'student_id' => $student->id,
        'instructor_id' => $instructor?->id,
        'package_id' => $package->id,
        'payment_mode' => PaymentMode::UPFRONT,
        'status' => OrderStatus::PENDING,
    ]);

    // Create or retrieve Stripe Customer
    $customerResult = $this->stripeService->createOrGetCustomer($student);

    if (!$customer->stripe_customer_id) {
        $student->stripe_customer_id = $customerResult['customer_id'];
        $student->save();
    }

    // Create Checkout Session
    $sessionResult = $this->stripeService->createCheckoutSession(
        $order,
        $package,
        $student,
        $instructor,
        route('student.checkout.success', $order),   // Success URL
        route('student.checkout.cancel', $order)     // Cancel URL
    );

    // Store session ID for webhook lookup
    $order->stripe_checkout_session_id = $sessionResult['session_id'];
    $order->save();

    return redirect($sessionResult['url']);  // Redirect to Stripe Checkout
}
```

#### Service Method: `StripeService::createOrGetCustomer()`
```php
public function createOrGetCustomer(User $student): array
{
    // If customer already exists, retrieve it
    if ($student->stripe_customer_id) {
        $customer = $this->stripe->customers->retrieve($student->stripe_customer_id);

        return [
            'success' => true,
            'customer_id' => $customer->id,
            'customer' => $customer,
        ];
    }

    // Create new customer
    $customer = $this->stripe->customers->create([
        'email' => $student->email,
        'name' => $student->name,
        'metadata' => [
            'user_id' => $student->id,
        ],
    ]);

    return [
        'success' => true,
        'customer_id' => $customer->id,
        'customer' => $customer,
    ];
}
```

#### Service Method: `StripeService::createCheckoutSession()`
```php
public function createCheckoutSession(
    Order $order,
    Package $package,
    User $student,
    ?Instructor $instructor,
    string $successUrl,
    string $cancelUrl
): array {
    $sessionData = [
        'mode' => 'payment',                      // One-time payment
        'customer' => $student->stripe_customer_id,
        'line_items' => [[
            'price' => $package->stripe_price_id, // Stripe Price ID
            'quantity' => 1,
        ]],
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => [
            'order_id' => $order->id,
            'package_id' => $package->id,
            'student_id' => $student->id,
        ],
    ];

    $session = $this->stripe->checkout->sessions->create($sessionData);

    return [
        'success' => true,
        'session_id' => $session->id,
        'url' => $session->url,  // Checkout URL
    ];
}
```

### Webhook: `checkout.session.completed`
```php
protected function handleCheckoutSessionCompleted(object $event): void
{
    $session = $event->data->object;

    // Find order by checkout session ID
    $order = Order::where('stripe_checkout_session_id', $session->id)->first();

    if ($session->payment_status === 'paid') {
        DB::beginTransaction();

        // Update order
        $order->stripe_payment_intent_id = $session->payment_intent;
        $order->status = OrderStatus::ACTIVE;
        $order->save();

        // Create lessons (if not already created)
        if ($order->lessons()->count() === 0) {
            $this->createLessonsForOrder($order);
        }

        // Send confirmation email
        $this->sendOrderConfirmationEmail($order);

        // Mark calendar item unavailable (if applicable)
        $this->markCalendarItemUnavailable($order);

        DB::commit();
    }
}

protected function createLessonsForOrder(Order $order): void
{
    $package = $order->package;

    for ($i = 0; $i < $package->lessons_count; $i++) {
        Lesson::create([
            'order_id' => $order->id,
            'instructor_id' => $package->instructor_id,
            'amount_pence' => $package->lesson_price_pence,
            'status' => LessonStatus::PENDING,
        ]);
    }
}
```

### Key Points
- ✅ **Platform holds funds** (not immediate transfer to instructor)
- ✅ **Checkout Session metadata** (order_id for webhook lookup)
- ✅ **Lessons created after payment** (via webhook)
- ✅ **Order status flow:** PENDING → ACTIVE
- ✅ **Idempotency:** Check if lessons already exist before creating

---

## 🔄 Flow #4: Lesson Sign-Off & Instructor Payout

### User Flow
1. Instructor views lessons in admin area
2. Lesson shows: Student name, date, amount, status
3. Instructor clicks "Sign Off" / "Mark Complete"
4. System validates:
   - Lesson not already completed
   - Instructor onboarding complete
   - If weekly mode: payment received
5. System creates Stripe Transfer to instructor
6. Payout record created (with transfer_id)
7. Lesson status = COMPLETED
8. If all lessons complete → Order status = COMPLETED
9. Success message: "Payout processed successfully!"

### Technical Implementation

#### Controller: `InstructorLessonController::complete()`
```php
public function complete(Request $request, Lesson $lesson): RedirectResponse
{
    $instructor = auth()->user()->instructor;

    // Validation checks
    if ($lesson->status === LessonStatus::COMPLETED) {
        return redirect()->back()
            ->with('warning', 'Lesson already completed');
    }

    if (!$instructor->onboarding_complete || !$instructor->payouts_enabled) {
        return redirect()->route('instructor.onboarding.index')
            ->with('error', 'Complete Stripe onboarding to receive payouts');
    }

    // For weekly mode: check payment received
    if ($lesson->order->isWeekly()) {
        if (!$lesson->lessonPayment || !$lesson->lessonPayment->isPaid()) {
            return redirect()->back()
                ->with('error', 'Payment not yet received');
        }
    }

    DB::beginTransaction();

    // Mark lesson complete
    $lesson->status = LessonStatus::COMPLETED;
    $lesson->completed_at = now();
    $lesson->save();

    // Create payout record
    $payout = Payout::create([
        'lesson_id' => $lesson->id,
        'instructor_id' => $instructor->id,
        'amount_pence' => $lesson->amount_pence,
        'status' => PayoutStatus::PENDING,
    ]);

    // Create Stripe Transfer
    $transferResult = $this->stripeService->createTransfer(
        $lesson,
        $instructor,
        $lesson->amount_pence
    );

    if (!$transferResult['success']) {
        throw new \Exception('Transfer failed: ' . $transferResult['error']);
    }

    // Update payout with transfer ID
    $payout->stripe_transfer_id = $transferResult['transfer_id'];
    $payout->status = PayoutStatus::PAID;
    $payout->paid_at = now();
    $payout->save();

    // Check if all lessons completed
    $order = $lesson->order;
    $allLessonsCompleted = $order->lessons()
        ->where('status', '!=', LessonStatus::COMPLETED)
        ->count() === 0;

    if ($allLessonsCompleted) {
        $order->status = OrderStatus::COMPLETED;
        $order->save();
    }

    DB::commit();

    return redirect()->back()
        ->with('success', 'Payout processed successfully!');
}
```

#### Service Method: `StripeService::createTransfer()`
```php
public function createTransfer(Lesson $lesson, Instructor $instructor, int $amountPence): array
{
    $transfer = $this->stripe->transfers->create([
        'amount' => $amountPence,             // Amount in pence
        'currency' => 'gbp',
        'destination' => $instructor->stripe_account_id,  // Connected account
        'metadata' => [
            'lesson_id' => $lesson->id,
            'order_id' => $lesson->order_id,
            'instructor_id' => $instructor->id,
        ],
    ]);

    return [
        'success' => true,
        'transfer_id' => $transfer->id,  // tr_xxxxx
        'transfer' => $transfer,
    ];
}
```

### Payout Flow Diagram
```
┌──────────────────────────────────────────────────────────────┐
│              LESSON COMPLETION & PAYOUT FLOW                 │
└──────────────────────────────────────────────────────────────┘

INSTRUCTOR ACTION
    └─> Clicks "Sign Off Lesson"
         │
         ├─> Validate: Lesson not already complete
         ├─> Validate: Instructor onboarding complete
         ├─> Validate: If weekly, payment received
         │
         ▼
    Mark lesson as COMPLETED (completed_at = now())
         │
         ▼
    Create Payout record (status = PENDING)
         │
         ▼
    Call Stripe API: Create Transfer
         ├─> Amount: lesson.amount_pence
         ├─> Destination: instructor.stripe_account_id
         ├─> Metadata: lesson_id, order_id, instructor_id
         │
         ▼
    Transfer successful ✅
         │
         ├─> Update Payout:
         │   - stripe_transfer_id = tr_xxxxx
         │   - status = PAID
         │   - paid_at = now()
         │
         ▼
    Check if all lessons in order completed
         │
         ├─> YES → Update Order status = COMPLETED
         └─> NO  → Order remains ACTIVE
         │
         ▼
    Success message: "Payout processed!"
```

### Key Points
- ✅ **Per-lesson payouts** (not per order)
- ✅ **Platform to instructor** (Stripe Transfer)
- ✅ **Transfer metadata** (for reconciliation)
- ✅ **Payout record tracking** (with stripe_transfer_id)
- ✅ **Order completion check** (all lessons must be complete)
- ✅ **Transaction safety** (DB::beginTransaction)
- ✅ **Weekly mode validation** (payment must be received first)

---

## 🔄 Flow #5: Weekly Payment Mode (Future Enhancement)

### How Weekly Mode Works
The v1 system was designed to support both **upfront** and **weekly** payment modes, though the full weekly implementation may not be complete.

#### Weekly Payment Concept
1. Student enrolls in package with weekly payment mode
2. No upfront payment required (or minimal deposit)
3. For each lesson:
   - System creates `LessonPayment` record (status: DUE, due_date: 1 day after lesson)
   - Stripe Invoice sent to student
   - Student pays invoice
   - Webhook: `invoice.paid` → LessonPayment status = PAID
4. Instructor can only sign off lesson if payment received
5. Transfer created only after lesson paid & signed off

#### Invoice Creation
```php
// Service Method: StripeService::createInvoice()
public function createInvoice(Lesson $lesson, User $student): array
{
    $package = $lesson->order->package;

    // Create invoice item
    $invoiceItem = $this->stripe->invoiceItems->create([
        'customer' => $student->stripe_customer_id,
        'amount' => $lesson->amount_pence,
        'currency' => 'gbp',
        'description' => "Lesson payment for {$package->name}",
        'metadata' => [
            'lesson_id' => $lesson->id,
            'order_id' => $lesson->order_id,
        ],
    ]);

    // Create and finalize invoice
    $invoice = $this->stripe->invoices->create([
        'customer' => $student->stripe_customer_id,
        'auto_advance' => true,
        'collection_method' => 'send_invoice',
        'days_until_due' => 1,  // Due 1 day after lesson
        'metadata' => [
            'lesson_id' => $lesson->id,
            'payment_mode' => 'weekly',
        ],
    ]);

    // Finalize to send
    $invoice = $this->stripe->invoices->finalizeInvoice($invoice->id);

    return [
        'success' => true,
        'invoice_id' => $invoice->id,
        'hosted_invoice_url' => $invoice->hosted_invoice_url,
    ];
}
```

#### Webhook: `invoice.paid`
```php
protected function handleInvoicePaid(object $event): void
{
    $invoice = $event->data->object;
    $lessonId = $invoice->metadata->lesson_id;

    $lessonPayment = LessonPayment::whereHas('lesson', function($query) use ($lessonId) {
        $query->where('id', $lessonId);
    })->first();

    $lessonPayment->update([
        'status' => PaymentStatus::PAID,
        'stripe_invoice_id' => $invoice->id,
        'paid_at' => now(),
    ]);
}
```

### Key Points (Weekly Mode)
- ✅ **Per-lesson invoicing** (not upfront)
- ✅ **Stripe handles collection** (send_invoice mode)
- ✅ **Payment before sign-off** (instructor can't complete until paid)
- ✅ **Automatic reminders** (Stripe emails student)
- ⚠️ **May not be fully implemented in v1**

---

## 📁 Key Files & Their Responsibilities

### Backend Files

#### **Services**
- `app/Services/StripeService.php`
  - **Central Stripe integration service**
  - All Stripe API calls go through this service
  - Methods: createConnectAccount, createAccountLink, createProduct, createPrice, createOrGetCustomer, createCheckoutSession, createTransfer, createInvoice, verifyWebhookSignature

#### **Models**
- `app/Models/Instructor.php`
  - Fields: stripe_account_id, onboarding_complete, charges_enabled, payouts_enabled
  - Methods: hasCompletedOnboarding(), canReceivePayouts()

- `app/Models/Package.php`
  - Fields: stripe_product_id, stripe_price_id, instructor_id, total_price_pence, lessons_count, lesson_price_pence
  - Auto-calculates lesson_price_pence in boot() method
  - Methods: isPlatformPackage(), isBespokePackage()

- `app/Models/Order.php`
  - Fields: payment_mode, status, stripe_payment_intent_id, stripe_checkout_session_id, stripe_subscription_id
  - Methods: isUpfront(), isWeekly(), isActive(), isPending()

- `app/Models/Lesson.php`
  - Fields: amount_pence, status, completed_at, calendar_item_id
  - Relationships: order, instructor, lessonPayment, payout, calendarItem

- `app/Models/Payout.php`
  - Fields: lesson_id, instructor_id, amount_pence, status, stripe_transfer_id, paid_at
  - Methods: isCompleted(), isPending(), isFailed()

- `app/Models/LessonPayment.php`
  - Fields: lesson_id, amount_pence, status, due_date, paid_at, stripe_invoice_id
  - Methods: isPaid(), isDue(), isRefunded()

- `app/Models/WebhookEvent.php`
  - Fields: stripe_event_id, type, payload
  - Purpose: Idempotency (prevent duplicate webhook processing)

#### **Controllers**
- `app/Http/Controllers/Instructor/OnboardingController.php`
  - Routes: index, start, refresh, return
  - Handles: Stripe Connect onboarding flow

- `app/Http/Controllers/Instructor/PackageController.php`
  - Routes: index, create, store, show, destroy
  - Handles: Package creation with Stripe Product/Price

- `app/Http/Controllers/Instructor/LessonController.php`
  - Routes: complete
  - Handles: Lesson sign-off and payout creation

- `app/Http/Controllers/Student/CheckoutController.php`
  - Routes: create, success, cancel
  - Handles: Stripe Checkout Session creation

- `app/Http/Controllers/WebhookController.php`
  - Route: POST /webhook/stripe
  - Handles: All Stripe webhook events
  - Events: checkout.session.completed, payment_intent.succeeded, account.updated, invoice.paid, etc.

#### **Enums**
- `app/Enums/PaymentMode.php`
  - Values: UPFRONT, WEEKLY

- `app/Enums/LessonStatus.php`
  - Values: PENDING, COMPLETED, CANCELLED

- `app/Enums/OrderStatus.php`
  - Values: PENDING, ACTIVE, COMPLETED, CANCELLED

- `app/Enums/PaymentStatus.php`
  - Values: DUE, PAID, FAILED, REFUNDED

- `app/Enums/PayoutStatus.php`
  - Values: PENDING, PAID, FAILED

### Routes

#### Instructor Routes
```php
// Onboarding
POST /instructor/onboarding/start          → Create account & redirect to Stripe
GET  /instructor/onboarding/refresh        → Refresh expired onboarding link
GET  /instructor/onboarding/return         → Return after Stripe onboarding

// Packages
GET  /instructor/packages                  → List instructor's packages
GET  /instructor/packages/create           → Package creation form
POST /instructor/packages                  → Create package + Stripe Product/Price
GET  /instructor/packages/{id}             → View package details
DELETE /instructor/packages/{id}           → Delete package (if no orders)

// Lessons
POST /instructor/lessons/{id}/complete     → Sign off lesson + create payout
```

#### Student Routes
```php
// Packages
GET  /student/packages                     → Browse available packages
GET  /student/packages/{id}                → View package details

// Checkout
POST /student/checkout/{package}           → Create order + Checkout Session
GET  /student/checkout/success/{order}     → Success page after payment
GET  /student/checkout/cancel/{order}      → Cancel page if payment cancelled

// Orders
GET  /student/orders                       → List student's orders
GET  /student/orders/{id}                  → View order details with lessons
```

#### Webhook Routes
```php
POST /webhook/stripe                       → Stripe webhook endpoint (no auth)
```

---

## 🔐 Configuration & Environment

### Required Environment Variables
```env
# Stripe Keys
STRIPE_KEY=pk_live_xxxxx                    # Publishable key
STRIPE_SECRET=sk_live_xxxxx                 # Secret key
STRIPE_WEBHOOK_SECRET=whsec_xxxxx           # Webhook signing secret

# App URLs (for Stripe redirects)
APP_URL=https://yourdomain.com
```

### Stripe Dashboard Setup
1. **Connect Settings**
   - Platform name
   - Support email
   - Branding (logo, colors)

2. **Webhook Endpoint**
   - URL: `https://yourdomain.com/webhook/stripe`
   - Events to listen for:
     - checkout.session.completed
     - payment_intent.succeeded
     - payment_intent.payment_failed
     - account.updated
     - invoice.paid
     - invoice.payment_failed

3. **Connect Onboarding Settings**
   - Country: United Kingdom (GB)
   - Currency: GBP
   - Account type: Express

---

## 💰 Money Flow & Platform Fees

### Current Implementation
The v1 system shows that the **platform holds all funds** and then transfers to instructors per lesson.

### Potential Fee Structure
```
Student pays £500 for 10-lesson package
    ├─> Platform receives: £500 (held in platform Stripe account)
    └─> On each lesson completion:
        ├─> Instructor receives: £50 (via Stripe Transfer)
        └─> Platform retains: £0 (could implement application_fee)
```

### Adding Platform Fees (Future)
To take a platform fee, modify the transfer creation:
```php
$transfer = $this->stripe->transfers->create([
    'amount' => $lesson->amount_pence - $platformFeePence,  // Deduct fee
    'currency' => 'gbp',
    'destination' => $instructor->stripe_account_id,
    'metadata' => [
        'lesson_id' => $lesson->id,
        'platform_fee' => $platformFeePence,
    ],
]);
```

Or use `application_fee_amount` in Checkout Session (for immediate fee capture):
```php
$sessionData = [
    'mode' => 'payment',
    'payment_intent_data' => [
        'application_fee_amount' => $totalPlatformFeePence,  // e.g., 10% of total
        'transfer_data' => [
            'destination' => $instructor->stripe_account_id,
        ],
    ],
    // ... rest of session config
];
```

---

## ⚠️ Important Technical Notes

### 1. Idempotency Protection
- **webhook_events table** stores processed event IDs
- Prevents duplicate processing if Stripe retries
- Check `WebhookEvent::hasBeenProcessed()` before handling

### 2. Transaction Safety
- All multi-step operations wrapped in `DB::beginTransaction()`
- Rollback on any failure (e.g., Stripe API error)

### 3. Error Handling
- All Stripe calls in try/catch blocks
- Errors logged with context (user_id, order_id, etc.)
- User-friendly error messages returned

### 4. Webhook Security
- Signature verification: `StripeService::verifyWebhookSignature()`
- Uses `Stripe::constructEvent()` with webhook secret
- Returns 400 if signature invalid

### 5. Async Status Updates
- Instructor onboarding status updated via `account.updated` webhook
- Don't rely solely on return URL status check
- Webhooks are source of truth

### 6. Currency Handling
- All amounts stored in **pence** (smallest currency unit)
- GBP currency hardcoded
- Format for display: `£{amount / 100}`

### 7. Instructor Capability Check
- Before package creation: check `onboarding_complete` && `charges_enabled`
- Before payout: check `onboarding_complete` && `payouts_enabled`
- Redirect to onboarding if not ready

### 8. Weekly Mode Validation
- Instructor cannot sign off lesson until payment received
- Check `lesson->lessonPayment->isPaid()` before allowing completion

---

## 🎯 Key Business Logic Rules

### Package Rules
1. Platform packages: `instructor_id = NULL`
2. Instructor bespoke packages: `instructor_id = {instructor_id}`
3. `lesson_price_pence` auto-calculated: `total_price_pence / lessons_count`
4. Cannot delete package if orders exist

### Order Rules
1. Order starts as PENDING (awaiting payment)
2. After payment confirmed (webhook): ACTIVE
3. After all lessons completed: COMPLETED
4. Payment modes: UPFRONT or WEEKLY

### Lesson Rules
1. Lessons created after successful payment (webhook)
2. Number of lessons = `package.lessons_count`
3. Each lesson: `amount_pence = package.lesson_price_pence`
4. Instructor can only sign off if:
   - Lesson not already completed
   - Instructor onboarding complete
   - If weekly mode: payment received

### Payout Rules
1. One payout per lesson (not per order)
2. Payout amount = `lesson.amount_pence`
3. Created immediately after lesson sign-off
4. Transfer created to instructor's connected account
5. Payout record stores `stripe_transfer_id`

---

## 🚀 Implementation Recommendations for v2

### What Worked Well (Keep)
1. ✅ **StripeService centralization** - All Stripe calls in one service
2. ✅ **Webhook idempotency** - Prevents duplicate processing
3. ✅ **Express accounts** - Fast instructor onboarding
4. ✅ **Per-lesson payouts** - Fair for incomplete packages
5. ✅ **Metadata everywhere** - Easy reconciliation & debugging

### What Could Be Improved
1. ⚠️ **Add platform fees** - Currently no revenue model
2. ⚠️ **Refund handling** - No refund logic implemented
3. ⚠️ **Dispute handling** - No chargeback/dispute flow
4. ⚠️ **Payout schedule** - Currently immediate, could batch
5. ⚠️ **Weekly mode** - Incomplete implementation, needs testing
6. ⚠️ **Calendar integration** - Link lessons to time slots properly
7. ⚠️ **Error recovery** - Manual intervention needed for failed transfers

### Additional Features to Consider
1. 📊 **Instructor earnings dashboard** - Track payouts, pending, completed
2. 🔔 **Email notifications** - Payment confirmations, payout notifications
3. 📅 **Scheduled payouts** - Daily/weekly batch transfers (reduce fees)
4. 💳 **Multiple payment methods** - Apple Pay, Google Pay
5. 🌍 **Multi-currency** - Support EUR, USD
6. 📈 **Analytics** - Revenue tracking, conversion rates
7. 🎫 **Promo codes** - Discounts for students
8. 🔄 **Subscription packages** - Monthly lesson subscriptions
9. 🛡️ **Fraud prevention** - Stripe Radar integration
10. 📝 **Tax handling** - VAT/sales tax calculation

---

## 🧪 Testing Checklist

### Stripe Test Mode
Use Stripe test mode for development:
- Test publishable key: `pk_test_xxxxx`
- Test secret key: `sk_test_xxxxx`
- Test webhook secret: `whsec_test_xxxxx`

### Test Cards
```
Success: 4242 4242 4242 4242 (Visa)
Decline: 4000 0000 0000 0002 (Card declined)
3D Secure: 4000 0027 6000 3184 (Requires authentication)
```

### Manual Testing Flow
1. **Instructor Onboarding**
   - [ ] Create instructor account
   - [ ] Start onboarding → Redirect to Stripe
   - [ ] Complete Stripe onboarding (test mode)
   - [ ] Return to platform → Check status updated
   - [ ] Verify webhook received: `account.updated`

2. **Package Creation**
   - [ ] Attempt before onboarding → Error
   - [ ] Create package after onboarding → Success
   - [ ] Verify Stripe Product created (Stripe Dashboard)
   - [ ] Verify Stripe Price created (Stripe Dashboard)
   - [ ] Check `stripe_product_id` and `stripe_price_id` stored

3. **Student Checkout**
   - [ ] Select package → Create order (PENDING)
   - [ ] Redirect to Stripe Checkout
   - [ ] Complete payment with test card
   - [ ] Verify webhook received: `checkout.session.completed`
   - [ ] Check order status = ACTIVE
   - [ ] Check lessons created (count = package.lessons_count)
   - [ ] Check success email sent

4. **Lesson Sign-Off**
   - [ ] Instructor views lesson
   - [ ] Click "Sign Off" → Confirm
   - [ ] Verify lesson status = COMPLETED
   - [ ] Verify payout created
   - [ ] Verify Stripe Transfer created (Stripe Dashboard)
   - [ ] Check `stripe_transfer_id` stored
   - [ ] Check payout status = PAID
   - [ ] Sign off all lessons → Check order status = COMPLETED

5. **Webhook Handling**
   - [ ] Use Stripe CLI: `stripe listen --forward-to localhost/webhook/stripe`
   - [ ] Trigger test events
   - [ ] Verify idempotency (duplicate events handled)
   - [ ] Check `webhook_events` table records

6. **Error Scenarios**
   - [ ] Payment fails → Webhook `payment_intent.payment_failed`
   - [ ] Transfer fails → Error message, payout not created
   - [ ] Incomplete onboarding → Blocked from creating packages
   - [ ] Sign off lesson without payment (weekly) → Error
   - [ ] Duplicate webhook event → Ignored (idempotency)

---

## 📞 Support & Resources

### Stripe Documentation
- **Connect Overview:** https://stripe.com/docs/connect
- **Express Accounts:** https://stripe.com/docs/connect/express-accounts
- **Checkout Sessions:** https://stripe.com/docs/payments/checkout
- **Transfers:** https://stripe.com/docs/connect/separate-charges-and-transfers
- **Webhooks:** https://stripe.com/docs/webhooks
- **Testing:** https://stripe.com/docs/testing

### Stripe Dashboard
- **Live mode:** https://dashboard.stripe.com
- **Test mode:** https://dashboard.stripe.com/test
- **Webhooks:** https://dashboard.stripe.com/webhooks
- **Connect:** https://dashboard.stripe.com/connect/accounts/overview

### Stripe CLI
- **Install:** https://stripe.com/docs/stripe-cli
- **Listen to webhooks:** `stripe listen --forward-to localhost/webhook/stripe`
- **Trigger test events:** `stripe trigger payment_intent.succeeded`

---

## ✅ Confirmation Checklist

**I have fully understood the following aspects of the v1 Stripe implementation:**

### Architecture & Flow
- [✅] Stripe Connect Express account model
- [✅] Platform-holds-funds model (not direct charges)
- [✅] Per-lesson payout structure (Stripe Transfers)
- [✅] Webhook-driven async updates

### Instructor Onboarding
- [✅] Create Stripe Connect Account (Express)
- [✅] Generate Account Link for Stripe-hosted onboarding
- [✅] Return URL status check + webhook confirmation
- [✅] Fields: `stripe_account_id`, `onboarding_complete`, `charges_enabled`, `payouts_enabled`

### Package Creation
- [✅] Create Stripe Product (represents package)
- [✅] Create Stripe Price (for Checkout Session)
- [✅] Auto-calculate `lesson_price_pence`
- [✅] Fields: `stripe_product_id`, `stripe_price_id`

### Student Checkout
- [✅] Create/retrieve Stripe Customer
- [✅] Create Checkout Session (mode: payment)
- [✅] Metadata: order_id, package_id, student_id
- [✅] Webhook creates lessons after payment
- [✅] Order status: PENDING → ACTIVE

### Lesson Completion & Payout
- [✅] Instructor signs off lesson
- [✅] Validation: onboarding complete, payment received (if weekly)
- [✅] Create Stripe Transfer to instructor's connected account
- [✅] Create Payout record with `stripe_transfer_id`
- [✅] Lesson status: PENDING → COMPLETED
- [✅] Check if all lessons complete → Order status: COMPLETED

### Database Schema
- [✅] instructors table (Stripe fields)
- [✅] packages table (Stripe Product/Price IDs)
- [✅] orders table (payment_mode, Stripe session/intent IDs)
- [✅] lessons table (amount_pence, status, completed_at)
- [✅] payouts table (stripe_transfer_id, status, paid_at)
- [✅] lesson_payments table (weekly mode)
- [✅] webhook_events table (idempotency)

### Technical Details
- [✅] Currency: GBP, stored in pence
- [✅] Transaction safety (DB::beginTransaction)
- [✅] Error handling & logging
- [✅] Webhook signature verification
- [✅] Idempotency protection
- [✅] Async status updates via webhooks

### Key Files
- [✅] `app/Services/StripeService.php` - All Stripe API calls
- [✅] `app/Http/Controllers/WebhookController.php` - Webhook handling
- [✅] `app/Http/Controllers/Instructor/OnboardingController.php`
- [✅] `app/Http/Controllers/Instructor/PackageController.php`
- [✅] `app/Http/Controllers/Instructor/LessonController.php`
- [✅] `app/Http/Controllers/Student/CheckoutController.php`
- [✅] Models: Instructor, Package, Order, Lesson, Payout, LessonPayment, WebhookEvent

### Payment Modes
- [✅] UPFRONT: Full payment at checkout
- [✅] WEEKLY: Per-lesson invoicing (partial implementation)

---

## 🎉 Conclusion

This document provides a **complete technical analysis** of the v1 Stripe implementation. The system is well-architected with:

✅ Clear separation of concerns (Service layer)
✅ Robust webhook handling with idempotency
✅ Per-lesson payouts for fair compensation
✅ Express accounts for fast instructor onboarding
✅ Transaction safety and error handling

**Ready for v2 implementation with confidence!**

---

**Document Version:** 1.0
**Last Updated:** 2026-02-10
**Analyzed By:** Claude (Sonnet 4.5)
**Status:** ✅ Complete & Ready for Developer Review
