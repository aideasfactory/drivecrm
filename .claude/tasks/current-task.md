# Task: Instructor creation: add Both transmission option

**Created:** 2026-03-13
**Last Updated:** 2026-03-13T15:30:00Z
**Status:** ✅ Complete

---

## Overview

### Goal
Add "Both" as a selectable transmission option when adding or editing an instructor.

### Context
- Tile ID: 019ce7ac-e171-70e4-86ad-63eff1658b8e
- Repository: drivecrm
- Branch: feature/019ce7ac-e171-70e4-86ad-63eff1658b8e-instructor-creation-add-both-transmission-option
- Priority: MEDIUM

---

## PHASE 1: PLANNING
**Status:** ✅ Complete

### Analysis
- `transmission_type` is stored as a string column on `instructors` table
- Validation rules only allowed 'manual' or 'automatic'
- StepFourController already handled 'both' case for onboarding tags
- TypeScript types restricted to `'manual' | 'automatic'`
- AddInstructorSheet select only had Manual and Automatic options

### Reflection
Straightforward addition with no database migration needed.

---

## PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks:
- [x] Update StoreInstructorRequest validation to allow 'both'
- [x] Update UpdateInstructorRequest validation to allow 'both'
- [x] Update BulkImportInstructorsAction validation to allow 'both'
- [x] Update TypeScript types for 'both'
- [x] Add 'Both' option to AddInstructorSheet select
- [x] Update FindInstructorsByPostcodeSectorAction for 'both' transmission handling
- [x] Write tests

### Reflection
All changes were minimal and focused. No database migration required.

---

## PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Summary
Added 'both' as a valid transmission type across the entire instructor flow. No anti-patterns. Backward-compatible.

### Score: 9/10
