# Task: Calendar Care Window — Visual Status System

**Created:** 2026-03-23
**Last Updated:** 2026-03-23
**Status:** All Phases Complete

---

## Overview

### Goal
Build a complete visual status system for the calendar care window (ScheduleTab / CalendarEventBlock) with defined colours, flags, and payment icons for every booking state.

### Context
- Repository: drivecrm
- Priority: HIGH (UX consistency)

### Required Status Combinations
1. **Available** — Blue, no flag, no payment icon
2. **Booked & not paid** — Gray, check flag, ⊘ icon
3. **Booked & paid** — Amber, check flag, £ icon
4. **Completed & paid** — Green, checkered flag, £ icon
5. **Completed & not paid** — Green, checkered flag, ⊘ icon
6. **Unavailable** — Red, cross flag, no payment icon
7. **Draft** — Gray lighter (existing), no flag, no payment icon
8. **Reserved** — Orange (existing), no flag, no payment icon
9. **Travel** — Purple dashed (existing)
10. **Practical Test** — Teal (existing)

---

## PHASE 1: PLANNING — Map All States & Data Flow
**Status:** ✅ Complete

### Tasks
- [x] 1.1 Map all required visual states to existing CalendarItemStatus enum values
- [x] 1.2 Determine if CalendarItemStatus enum needs new values or if payment status is a separate axis
- [x] 1.3 Plan backend changes — what data needs to be added to GetInstructorCalendarAction response
- [x] 1.4 Plan TypeScript type changes — CalendarItemResponse and CalendarEvent interfaces
- [x] 1.5 Plan CalendarEventBlock.vue changes — colour system, flag icons, payment icons
- [x] 1.6 Document edge cases: completed-but-unpaid-but-signed-off, temporary/unbooked slots, mixed states

### Reflection
The existing architecture cleanly separates booking status (CalendarItemStatus) from payment status (LessonPayment). No enum changes needed — we just need to expose the payment data to the frontend and build the visual layer on top of the combined state.

---

## PHASE 2: BACKEND — Expose Payment Status to Calendar Data
**Status:** ✅ Complete

### Tasks
- [x] 2.1 Update `GetInstructorCalendarAction` to eager-load `items.lessons.lessonPayment`
- [x] 2.2 Add `is_paid` field to the calendar item response array
- [x] 2.3 Update TypeScript types (`CalendarItemResponse`, `CalendarEvent` interfaces) with `isPaid` field

### Reflection
Minimal backend change — added `lessonPayment` to the eager-load chain and computed `is_paid` from the first lesson's payment status. Only applies to booked/completed items (draft/reserved/available have no lesson payment). Clean separation maintained.

---

## PHASE 3: FRONTEND — Implement Visual Status System
**Status:** ✅ Complete

### Tasks
- [x] 3.1 Update `ScheduleTab.vue` data loading to pass `is_paid` field through to events
- [x] 3.2 Rewrite `CalendarEventBlock.vue` colour system with new mappings
- [x] 3.3 Add flag icons (Flag for completed, Check for booked, X for unavailable) using Lucide
- [x] 3.4 Add payment icons (PoundSterling for paid, CircleOff for unpaid) using Lucide
- [x] 3.5 Handle all edge case combinations (completed+unpaid shown with green + flag + ⊘)
- [x] 3.6 Update `statusLabel` computed to reflect new status names (e.g. "Booked & Paid", "Booked (Unpaid)")
- [x] 3.7 Ensure dark mode variants are correct for all new colours (amber dark mode added)

### Reflection
Complete rewrite of the colour system. Key design decisions:
- Draft uses a lighter gray (`gray-200/gray-50`) to visually distinguish from "Booked & not paid" which uses standard gray
- Available changed from yellow to blue as requested
- Booked splits into two states based on `isPaid` — gray (unpaid) vs amber (paid)
- Flag/payment icons only show for relevant states, keeping the UI clean for available/draft/reserved slots
