# Task: Mobile onboarding padding

**Created:** 2026-09-28
**Last Updated:** 2026-09-28
**Status:** Complete

---

## 📋 Overview

### Goal
Keep the public onboarding review (step 5) and payment (step 6) screens inside the mobile viewport. The instructor rating was cramped, and the confirm / proceed buttons overflowed the card.

### Success Criteria
- [x] Instructor rating wraps as a whole chip and no longer renders an empty "( reviews)" label
- [x] A single lesson reads "1 lesson"
- [x] Confirm and proceed buttons stay inside the card on narrow screens
- [x] Desktop layout keeps Back and the primary action on one row

---

## 🎯 PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Trace the review and payment markup in `Step5.vue` and `Step6.vue`
- [x] Identify nested card padding, non-wrapping meta row, and nowrap action buttons as the overflow

### Decisions Made
- Reduce horizontal card padding on small screens only (`px-4 sm:px-6`)
- Stack the Back and primary buttons full-width below `sm`, keep the row from `sm` up
- Append instructor `reviews` for the review step and pluralize lesson / review counts in the template

### Reflection
The overflow is layout, not a missing page margin. Side-by-side nowrap buttons cannot fit a 320–390px content box.

---

## 🎯 PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Tasks
- [x] Review-step rating row, lesson pluralization, and confirm button
- [x] Payment-step proceed button and matching lesson / invoice pluralization
- [x] Pass review counts through `StepFiveController`

### Reflection
No new API routes and no schema changes. The Laravel app could not be booted in this environment (no PHP), so the layout was checked with a narrow-width render of the same stacking rules.

---

## 🎯 PHASE 3: REFLECTION

**Status:** ✅ Complete

- Nested `px-6` cards on an already padded page are what made the rating row too narrow.
- Button text still wraps inside the full-width control on a 320px screen if the label is longer than one line.
