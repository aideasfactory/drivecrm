# Task: Reuse Existing Users During Onboarding

## Overview
Fix the onboarding flow so existing users are reused instead of duplicated when the submitted email already exists.

## Phases

### Phase 1: Planning
**Status:** Complete

### Phase 2: Implementation
**Status:** Complete

- [x] Update CreateUserAndStudentFromEnquiryAction to update existing student data
- [x] Update StepSixController to cancel previous pending orders from the same enquiry
- [x] Create CancelPendingOrderAction to cleanly cancel an order and release calendar items
- [x] Write Pest tests for user reuse and duplicate order prevention

### Phase 3: Reflection
**Status:** Complete

**Last Updated:** 2026-03-24
