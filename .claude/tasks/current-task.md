# Task: Fix Onboarding UI Issues (Colors, Spacing, Continue Button)

**Created:** 2026-03-05
**Last Updated:** 2026-03-05T19:15:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Fix several UI issues across the onboarding forms:
1. Change 'DVSA Approved' and 'Secure Checkout' badge colors from hardcoded `bg-red-600` to `bg-primary`
2. Update stepper completed step badges from `bg-red-600` to `bg-primary`
3. Increase spacing between labels and inputs in Step 5 "Learner Details" form
4. Fix Continue button on Step 5 when "I'm booking for someone else" is checked

### Success Criteria
- [x] Badge labels use `bg-primary text-primary-foreground` instead of `bg-red-600 text-white`
- [x] Stepper completed steps use `bg-primary text-primary-foreground` instead of `bg-red-600 text-white`
- [x] Learner Details form has 2-3px more spacing between labels and inputs
- [x] Continue button on Step 5 progresses to Step 6 when "booking for someone else" is checked

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Analysis

#### Issue 1: Badge Colors (DVSA Approved & Secure Checkout)
- **Files:** `Step1.vue` (lines 30, 34), `OnboardingLeftSidebar.vue` (lines 23, 27)
- **Current:** `bg-red-600 text-white hover:bg-red-700`
- **Fix:** Replace with `bg-primary text-primary-foreground hover:bg-primary/90`

#### Issue 2: Stepper Background
- **File:** `OnboardingHeader.vue` (lines 32, 51)
- **Current:** `bg-red-600 text-white hover:bg-red-700` on completed steps
- **Fix:** Replace with `bg-primary text-primary-foreground hover:bg-primary/90`

#### Issue 3: Learner Details Form Spacing
- **File:** `Step5.vue` — learner details form inputs
- **Fix:** Add `mt-1.5` to all 5 Input elements in the learner details section

#### Issue 4: Continue Button Bug
- **Root cause:** `autoSave()` posts to same endpoint as `submit()` without an `auto_save` field. Backend checks `!$request->has('auto_save')` to decide redirect vs save-only. Auto-save was being treated as a real submit, causing race conditions and premature redirect attempts.
- **Fix:** (1) Add `auto_save: true` via `form.transform()`, (2) Cancel pending auto-save before submit, (3) Skip required learner field validation during auto-save in FormRequest

### Reflection
Clear, well-scoped fixes. The button bug was a subtle interaction between auto-save and the backend's redirect logic.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Fix badge colors in `OnboardingLeftSidebar.vue` (2 badges)
- [x] Fix badge colors in `Step1.vue` (2 badges)
- [x] Fix stepper colors in `OnboardingHeader.vue` (2 occurrences)
- [x] Add `mt-1.5` spacing to all 5 inputs in Step 5 learner details form
- [x] Fix auto-save to send `auto_save: true` via `form.transform()`
- [x] Cancel pending auto-save timeout before explicit submit
- [x] Remove duplicate watcher on `isBookingForSomeoneElse`
- [x] Remove stale `console.log(props.package)` from Step5.vue
- [x] Update `StepFiveRequest.php` to accept `auto_save` field and skip learner required validation during auto-save
- [x] Fixed Label `for` attribute typo (`learner-first-name mb-4` → `learner-first-name`)

### Reflection
All fixes applied cleanly. The badge and stepper color changes were simple class replacements. The button bug fix required changes in both frontend (auto-save transform + cancel) and backend (FormRequest conditional validation). Also cleaned up dead code (duplicate watcher, console.log, typo in Label for attribute).

---

## FINAL REFLECTION
**Status:** ✅ Complete

### Summary
Fixed 4 UI issues in the onboarding flow: badge colors, stepper colors, form spacing, and continue button bug.

### Files Changed
1. `resources/js/components/Onboarding/OnboardingLeftSidebar.vue` — Badge colors: `bg-red-600` → `bg-primary`
2. `resources/js/pages/Onboarding/Step1.vue` — Badge colors: `bg-red-600` → `bg-primary`
3. `resources/js/components/Onboarding/OnboardingHeader.vue` — Stepper colors: `bg-red-600` → `bg-primary`
4. `resources/js/pages/Onboarding/Step5.vue` — Label spacing, auto-save fix, removed dead code
5. `app/Http/Requests/Onboarding/StepFiveRequest.php` — Added `auto_save` rule, conditional learner validation
