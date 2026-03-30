# Task: Populate Instructor Student Payments Tab

## Overview
Build out the payments tab on the instructor-side student screen to show the student's actual payment records (LessonPayment records linked through Orders -> Lessons).

## Phase 1: Planning ✅
- [x] Analyze existing data structures (LessonPayment, Order, Lesson models)
- [x] Review existing tab patterns (LessonsSubTab as reference)
- [x] Plan backend endpoint and frontend component

### Reflection
Data flows: Student -> Orders -> Lessons -> LessonPayment. Each LessonPayment has amount_pence, status (due/paid/refunded), due_date, paid_at. Orders have payment_mode (upfront/weekly). Will create a new Action + controller method + route, then build out the Vue component following LessonsSubTab pattern.

## Phase 2: Implementation 🔄
- [ ] Create GetStudentPaymentsAction in app/Actions/Student/Payment/
- [ ] Add payments() method to PupilController
- [ ] Add route in web.php
- [ ] Implement PaymentsSubTab.vue with table, summary cards, loading/empty states

## Phase 3: Polish & Completion ⏸️
- [ ] Final review of data flow
- [ ] Update current-task.md with reflection
- [ ] Write .phase_done sentinel

## Status: Phase 2 - In Progress
Last Updated: 2026-03-30
