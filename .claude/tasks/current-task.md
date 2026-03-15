# Task: Update Package Pricing UI to Use Pounds Instead of Pence

**Created:** 2026-03-13
**Last Updated:** 2026-03-13T19:00:00Z
**Status:** 🔄 In Progress

---

## Overview

### Goal
Update the Create/Edit Package UI so users enter prices in pounds (e.g., 500.00) instead of pence (e.g., 50000). Database storage remains in pence — conversions happen at the form boundary.

### Context
- Currently `PackageForm.vue` accepts `total_price_pence` directly as a pence integer
- Helper text says "Enter price in pence (e.g., 50000 = £500.00)"
- The model already formats display values correctly (formatted_total_price, formatted_lesson_price)
- Stripe integration uses `total_price_pence` directly as `unit_amount` — no change needed there
- Database stores all prices as integer pence — this must NOT change

### Key Files
- `resources/js/components/Instructors/PackageForm.vue` — Main form component
- `resources/js/components/Packages/CreatePackageSheet.vue` — Create sheet wrapper
- `resources/js/pages/Packages/Index.vue` — Package listing page
- `app/Http/Requests/StorePackageRequest.php` — Store validation
- `app/Http/Requests/UpdatePackageRequest.php` — Update validation
- `tests/Feature/Packages/PackageManagementTest.php` — Existing tests

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Tasks
- [x] Review PackageForm.vue input handling and computed properties
- [x] Review CreatePackageSheet.vue submission logic
- [x] Review StorePackageRequest / UpdatePackageRequest validation
- [x] Review Package model formatting attributes
- [x] Review existing tests
- [x] Identify conversion points (frontend → backend, backend → frontend)

### Conversion Strategy
**Frontend (form input in pounds → submit in pence):**
1. Change form input to accept pounds (decimal, e.g., 500.00)
2. On form submit, convert pounds to pence: `Math.round(pounds * 100)`
3. Update helper text and labels
4. Update computed display properties

**Backend (validation):**
1. Validation rules stay as integer/pence — the frontend sends pence after conversion
2. No backend changes needed for storage or Stripe

**Edit form (load existing data):**
1. When editing, convert `total_price_pence` from model → pounds for display: `pence / 100`

### Reflection
Clean separation — all conversion happens in the Vue form component. Backend stays unchanged because the form still submits pence. This minimises risk and keeps Stripe/database logic untouched.

---

## PHASE 2: IMPLEMENTATION
**Status:** ⏸️ Not Started

### Tasks
- [ ] Update `PackageForm.vue`: change input to accept pounds, add pence↔pounds conversion
- [ ] Update `PackageForm.vue`: update labels, helper text, and computed properties
- [ ] Update `CreatePackageSheet.vue`: ensure submit converts pounds→pence before POST
- [ ] Check if edit flow exists and handle pence→pounds conversion for pre-filling
- [ ] Update tests to verify the flow still works correctly
- [ ] Verify Index.vue display columns still work (they use model-formatted values)

### Reflection
_To be completed after implementation_

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ⏸️ Not Started

### Files Changed
_To be completed_

### Summary
_To be completed_
