# Task: Create instructor Reports page with payment tracking

**Created:** 2026-03-04
**Last Updated:** 2026-03-04T17:30:00Z
**Status:** Complete

---

## 📋 Overview

### Goal
Implement the Reports page for individual instructors in Drive CRM. Display tabulated data showing all payments received and pending payments with filtering capability.

### Success Criteria
- [x] Update page at instructors/{id}?tab=reports
- [x] Display tabulated data for payments received and pending
- [x] Implement filtering to toggle between paid/pending views
- [x] Update summary cards to reflect totals based on active filter
- [x] Clean, simple tabulated data for version one

### Context
- Tile ID: 019cb9b4-7154-7012-bebd-86a8b24a59d0
- Branch: feature/019cb9b4-7154-7012-bebd-86a8b24a59d0-create-instructor-reports-page-with-payment-tracking

---

## 🎯 PHASE 1: PLANNING
**Status:** ✅ Complete

### Architecture
- Backend: GetInstructorPayoutsAction → InstructorService → InstructorController
- Frontend: Self-managed ReportsTab with local filtering, summary cards, ShadCN table
- Route: GET /instructors/{instructor}/payouts

### Reflection
Planning complete. Using existing patterns with Payout model as primary data source.

---

## 🔨 PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks
- [x] Create GetInstructorPayoutsAction
- [x] Add getPayouts to InstructorService
- [x] Add payouts endpoint to InstructorController
- [x] Add route for payouts
- [x] Add TypeScript Payout interface
- [x] Implement ReportsTab.vue
- [x] Write Pest test
- [x] Create factories (Student, Order, Lesson, Payout)

### Reflection
All backend and frontend implementation complete. Followed existing patterns exactly.

---

## 💭 PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Summary
Implemented the instructor Reports tab with payment tracking. The tab replaces the placeholder and now shows a self-loading component that fetches payout data via axios, displays 4 summary cards (Total Payouts, Total Amount, Paid, Pending), and renders a ShadCN Table with student name, lesson date/time, package, amount, status badge, and paid-at columns. Filter buttons (All/Paid/Pending) allow toggling between views, with summary cards updating reactively.

### Files Changed
- `app/Actions/Instructor/GetInstructorPayoutsAction.php` (created)
- `app/Services/InstructorService.php` (added getPayouts method)
- `app/Http/Controllers/InstructorController.php` (added payouts endpoint)
- `routes/web.php` (added GET /instructors/{instructor}/payouts)
- `resources/js/types/instructor.ts` (added InstructorPayout interface)
- `resources/js/components/Instructors/Tabs/ReportsTab.vue` (full implementation)
- `database/factories/StudentFactory.php` (created)
- `database/factories/OrderFactory.php` (created)
- `database/factories/LessonFactory.php` (created)
- `database/factories/PayoutFactory.php` (created)
- `tests/Feature/Instructors/InstructorPayoutsTest.php` (created, 7 tests)

### Potential Overhead / Anti-patterns
None. The implementation follows all established patterns exactly:
- Action → Service → Controller architecture
- Self-managed tab component with axios
- ShadCN components throughout
- Client-side filtering for responsiveness

### Score: 8/10
Clean V1 implementation following all conventions. Deducted points for: wireframe unavailable (Google Drive auth required) so layout was inferred from requirements and existing patterns; no pagination yet (acceptable for V1 but may be needed as data grows).
