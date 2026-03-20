# Task: Add onboarding discount support using UUID-based discount tiers

**Created:** 2026-03-20
**Last Updated:** 2026-03-20T16:30:00Z
**Status:** Complete

---

## Overview

### Goal
Build a UUID-driven discount system into the onboarding flow. Discount codes map to percentage tiers (5%, 10%, 15%, 20%), are manageable from admin, and carry through the entire onboarding, package display, and purchase flow.

### Context
- Tile ID: 019d0adf-3ffd-7181-9923-606d3b1a519c
- Branch: feature/019d0adf-3ffd-7181-9923-606d3b1a519c-add-onboarding-discount-support-using-uuid-based-discount-ti

---

## PHASE 1: PLANNING
**Status:** Complete

## PHASE 2: IMPLEMENTATION
**Status:** Complete

### Completed Tasks
- [x] Created migration for discount_codes table (UUID PK, label, percentage, active)
- [x] Created migration to add discount_code_id and discount_percentage to orders
- [x] Created DiscountCode model with factory
- [x] Created Actions: GetAllDiscountCodesAction, CreateDiscountCodeAction, DeleteDiscountCodeAction
- [x] Created DiscountService extends BaseService
- [x] Created DiscountCodeController (admin CRUD: index, store, destroy)
- [x] Created StoreDiscountCodeRequest FormRequest
- [x] Added admin routes for discount codes
- [x] Created admin Vue page DiscountCodes/Index.vue with table, search, delete, copy URL
- [x] Created CreateDiscountCodeSheet.vue component
- [x] Added Discount Codes nav item to AppSidebar
- [x] Modified OnboardingController::start() to accept ?discount=uuid param
- [x] Added getDiscountData() helper to Enquiry model
- [x] Modified StepThreeController::show() to pass discount data
- [x] Modified Step3.vue to display discounted prices with strikethrough
- [x] Modified StepFiveController::show() to include discount in pricing
- [x] Modified Step5.vue to show discount line in pricing summary
- [x] Modified StepSixController::show() to include discount data
- [x] Modified Step6.vue to show discount badge
- [x] Modified StepSixController::store() to pass discount to order action
- [x] Modified CreateOrderFromEnquiryAction to apply discounted prices to order
- [x] Modified StripeService::createCheckoutSession() to use price_data for discounted amounts
- [x] Updated Order model with discount_code_id and discount_percentage fields
- [x] Updated database-schema.md
- [x] Wrote Pest tests for discount code CRUD
- [x] Wrote Pest tests for onboarding discount flow

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** Complete

### Reflection
The implementation follows all established patterns: Controller -> Service -> Action architecture, BaseService extension, FormRequest validation, ShadCN components, and Inertia/Vue patterns. The discount state is held server-side in the enquiry JSON for security. Discounted prices are snapshotted into the order to preserve the price at time of purchase. The Stripe checkout uses price_data with the discounted amount when a discount is active, falling back to the pre-created price_id when no discount applies.

### Score: 8/10
Solid implementation covering all requirements. Minor areas for improvement:
- Could add a toggle to deactivate/reactivate discount codes (currently only create/delete)
- Could add usage analytics (tracked via orders_count but no detailed reporting)
- The promo code system in Step 5 still exists in parallel with the UUID discount system - they could potentially be unified in the future
