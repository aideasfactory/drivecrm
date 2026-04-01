# Task: Student Home Page Feed API Endpoint

## Overview
Create a dedicated home page feed API endpoint for students that returns instructor assignment status, upcoming lessons, special offers, purchased hours, learning resources, and instructor bio data.

## Phase 1: Planning ✅
**Status:** Complete

### Reflection
- Reusing existing patterns: Controller -> Service -> Action
- Identity from token, not request parameter
- Special offer comes from Instructor.meta['special_offer']
- Purchased hours = total non-draft/non-cancelled lesson count across all orders

## Phase 2: Implementation ✅
**Status:** Complete

### Tasks
- [x] Create GetStudentHomeFeedAction
- [x] Add getHomeFeed to StudentService (with caching)
- [x] Create StudentHomeFeedResource
- [x] Create StudentHomeFeedController
- [x] Add route to api.php (GET /api/v1/student/home-feed)
- [x] Write Pest test (5 test cases)
- [x] Update api.md (endpoint docs, quick reference, changelog)

### Reflection
- Followed Controller -> Service -> Action pattern strictly
- Service extends BaseService with caching via remember()
- Reused existing ResourceResource for learning_resources
- Action handles all data aggregation: upcoming lessons, purchased hours, learning resources, instructor data
- No new migrations needed
- Identity resolved from token via ResolveApiProfile middleware

## Phase 3: Finalization ✅
**Status:** Complete

**Last Updated:** 2026-04-01
