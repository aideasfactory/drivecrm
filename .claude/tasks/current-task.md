# Task: Show each student's next booked lesson on the instructor pupils screen

## Overview
The next lesson field on the instructor pupils screen is always blank because GetInstructorPupilsAction compares the LessonStatus enum with plain strings, which always returns false in PHP 8.1+.

## Phase 1: Planning - Complete
- [x] Identify root cause
- [x] Frontend already handles display correctly

## Phase 2: Implementation - Complete
- [x] Fix enum comparison for next lesson lookup
- [x] Fix enum comparison for completed count
- [x] Add date filter for today+ only
- [x] Fix start_time serialization

## Phase 3: Final Review - Complete
- [x] Verify changes follow backend coding standards
- [x] Write .phase_done sentinel

Last Updated: 2026-03-30
Status: Complete
