# Task: Hazard Perception System

## Overview

Build the hazard perception video system for the student mobile app. Students can watch hazard perception video clips and identify hazards. Each video can contain up to 2 hazards (double-scoring clips). Videos are categorised by category and topic (similar to mock test questions). The API returns all videos and records student attempt scores.

### Design Decisions

| Question | Decision |
|----------|----------|
| Video storage | `hazard_perception_videos` table with video URL, duration, and hazard timing windows |
| Hazard timing | Each video has hazard_1 start/end times; hazard_2 is nullable for double-scoring clips |
| Scoring | 5-band scoring per hazard (5=earliest, 1=latest, 0=missed). Double hazard clips max 10 points |
| Categorisation | `category` + `topic` columns (same pattern as mock_test_questions) |
| Attempt recording | `hazard_perception_attempts` table records each student attempt per video |
| API pattern | Follows mock test pattern: Controller → Service → Action, Eloquent Resources |

## Phase 1: Planning ✅

- [x] Design schema for hazard_perception_videos
- [x] Design schema for hazard_perception_attempts
- [x] Design API endpoints
- [x] Confirm architecture follows Controller → Service → Action

### Reflection
- 2 tables: videos (content) and attempts (student scores) — clean separation
- Double hazard support via nullable hazard_2 fields
- Category/topic pattern mirrors mock_test_questions for consistency

## Phase 2: Implementation ✅

- [x] Create `hazard_perception_videos` migration
- [x] Create `hazard_perception_attempts` migration
- [x] Create `HazardPerceptionVideo` model + factory
- [x] Create `HazardPerceptionAttempt` model + factory
- [x] Create Actions (GetHazardPerceptionVideosAction, RecordHazardPerceptionAttemptAction, GetHazardPerceptionSummaryAction)
- [x] Create `HazardPerceptionService extends BaseService`
- [x] Create API Resources
- [x] Create `HazardPerceptionController` + FormRequest + routes
- [x] Add Student relationship
- [x] Update `database-schema.md`
- [x] Update `api.md`

### Reflection
- Followed exact same patterns as MockTest feature for consistency
- HazardPerceptionService extends BaseService with caching on reads, invalidation on writes
- Scoring logic is clean: 5-band system within each hazard window, calculated server-side
- Hazard timing windows are NOT exposed to the client — prevents cheating
- All 3 Actions are pure business logic, no HTTP concerns
- Videos grouped by category → topic for easy mobile app rendering

## Phase 3: Reflection ✅

- [x] All implementation complete
- [x] Architecture follows Controller → Service → Action pattern
- [x] HazardPerceptionService extends BaseService
- [x] All API responses use Eloquent Resources
- [x] api.md updated with full documentation
- [x] database-schema.md updated

### Files Created
- `database/migrations/2026_04_14_151415_create_hazard_perception_videos_table.php`
- `database/migrations/2026_04_14_151415_create_hazard_perception_attempts_table.php`
- `app/Models/HazardPerceptionVideo.php`
- `app/Models/HazardPerceptionAttempt.php`
- `database/factories/HazardPerceptionVideoFactory.php`
- `database/factories/HazardPerceptionAttemptFactory.php`
- `app/Actions/HazardPerception/GetHazardPerceptionVideosAction.php`
- `app/Actions/HazardPerception/RecordHazardPerceptionAttemptAction.php`
- `app/Actions/HazardPerception/GetHazardPerceptionSummaryAction.php`
- `app/Services/HazardPerceptionService.php`
- `app/Http/Resources/V1/HazardPerceptionVideoResource.php`
- `app/Http/Resources/V1/HazardPerceptionAttemptResource.php`
- `app/Http/Resources/V1/HazardPerceptionSummaryResource.php`
- `app/Http/Controllers/Api/V1/HazardPerceptionController.php`
- `app/Http/Requests/Api/V1/SubmitHazardPerceptionAttemptRequest.php`

### Files Modified
- `app/Models/Student.php` — added `hazardPerceptionAttempts()` HasMany relationship
- `routes/api.php` — 3 new routes under `student` prefix
- `.claude/api.md` — full documentation for 3 new endpoints, changelog updated
- `.claude/database-schema.md` — added 2 new table documentations

### Technical Debt / Follow-Up
- **Video hosting**: Videos need to be uploaded to S3 or a CDN — the `video_url` field stores the path
- **Seeder**: No seeder created yet — videos will need to be imported from a data source
- **Multiple attempts per video**: Students can attempt the same video multiple times — the summary tracks all attempts. Consider whether to limit retries.

---

**Status:** All phases complete.
**Last Updated:** 2026-04-14
