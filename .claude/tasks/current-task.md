# Task: Expose Weekly Lesson-Payment Onboarding Workflow Through API

## Overview
Built API support for the existing weekly lesson-payment onboarding workflow. The core order creation logic already existed (`CreateOrderFromApiAction`). This task filled the remaining gaps: confirmation emails, order/lesson-payment retrieval endpoints, and enhanced API resources.

## Phases

### Phase 1: Planning ✅
**Status:** ✅ Complete

### Phase 2: Implementation ✅
**Status:** ✅ Complete

**Tasks:**
- [x] 1. Send order confirmation email for weekly API orders (reuse `SendOrderConfirmationEmailAction`)
- [x] 2. Create `LessonPaymentResource` in `app/Http/Resources/V1/`
- [x] 3. Create `OrderLessonResource` for lesson data in order context
- [x] 4. Enhance `OrderResource` to include lessons and lesson_payments when loaded
- [x] 5. Add `getStudentOrders()` and `getOrderDetail()` methods to `OrderService`
- [x] 6. Add `GET /api/v1/students/{student}/orders` endpoint (list orders)
- [x] 7. Add `GET /api/v1/students/{student}/orders/{order}` endpoint (order detail)
- [x] 8. Add routes to `routes/api.php`
- [x] 9. Write tests for new endpoints

**Reflection:** Reused existing Actions and Services per coding standards. No new Actions or Services created — only added methods to `OrderService`. The `SendOrderConfirmationEmailAction` from the onboarding domain was cleanly reused.

### Phase 3: API Documentation ✅
**Status:** ✅ Complete

**Tasks:**
- [x] 1. Document `GET /api/v1/students/{student}/orders` in api.md
- [x] 2. Document `GET /api/v1/students/{student}/orders/{order}` in api.md
- [x] 3. Update existing `POST /api/v1/students/{student}/orders` docs with enhanced response
- [x] 4. Update changelog

**Reflection:** Full documentation including request/response examples, field descriptions, and weekly payment flow notes.

## Last Updated
2026-03-30
