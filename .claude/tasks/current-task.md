# Task: Extend Resource Upload to Support Video Links (Vimeo/YouTube)

**Created:** 2026-03-05
**Last Updated:** 2026-03-05T15:30:00Z
**Status:** Complete

---

## 📋 Overview

### Goal
Extend the existing resource upload functionality to support two upload types: file upload (existing) and video link (Vimeo/YouTube URL). Update the slide-out sheet UI to allow choosing between upload types while keeping common fields (title, description, tags) for both.

### Success Criteria
- [x] Button text changed from "Upload File" to "Upload Resource"
- [x] Upload sheet has a type selector: "File" or "Video Link"
- [x] When "Video Link" selected, shows URL input field (for Vimeo/YouTube)
- [x] When "File" selected, keeps existing file upload functionality
- [x] Both types share title, description, and tags fields
- [x] Database migration adds `resource_type` and `video_url` fields to resources table
- [x] Resource model updated with new fields
- [x] Existing file uploads continue to work after changes
- [x] Feature test covers both upload types

### Context
- **Existing**: Full resources CRUD with S3 file upload, nested folders, tags, preview
- **Architecture**: ResourceController → ResourceService → Actions (app/Actions/Resource/)
- **Frontend**: UploadResourceSheet.vue with drag-drop file upload, TagInput component
- **Storage**: S3 for files; video links will be URL-only (no file storage)

---

## 🎯 PHASE 1: PLANNING
**Status:** ✅ Complete

### Architecture

**Database Changes:**
- Add `resource_type` column defaulting to 'file' to `resources` table
- Add `video_url` nullable string(500) column to `resources` table
- Make `file_path`, `file_name`, `file_size`, `mime_type` nullable (video links won't have files)

**Backend Changes:**
- Update `Resource` model — add new fillable fields, add `isVideoLink()` and `isFile()` methods
- Update `StoreResourceRequest` — conditional validation based on `resource_type`
- Create `StoreVideoLinkResourceAction` in `app/Actions/Resource/`
- Update `ResourceService` — inject and expose new action
- Update `ResourceController::storeResource()` — branch on resource_type
- Update `ResourceController::getFileUrl()` — return video_url for video links
- Update `ResourceController::emailView()` — redirect to video_url for video links

**Frontend Changes:**
- Update `UploadResourceSheet.vue` — type selector buttons, conditional file/URL input
- Update `Resources/Index.vue` — change button text to "Upload Resource"
- Update `ResourceCard.vue` — purple link icon for video links, conditional file info
- Update `ResourcePreview.vue` — YouTube/Vimeo iframe embed with 16:9 aspect ratio
- Update `EditResourceSheet.vue` — show video URL instead of file info for video links

### Reflection
Planning was thorough. Identified all files that needed changes across the full stack.

---

## 🔨 PHASE 2: IMPLEMENTATION
**Status:** ✅ Complete

### Tasks

**Database & Backend:**
- [x] Create migration to add `resource_type`, `video_url` and make file columns nullable
- [x] Update `.claude/database-schema.md` with new fields
- [x] Update `Resource` model with new fields, `isVideoLink()`, `isFile()` methods
- [x] Update `StoreResourceRequest` with conditional validation rules
- [x] Create `StoreVideoLinkResourceAction` in `app/Actions/Resource/`
- [x] Update `ResourceService` with `storeVideoLinkResource()` method
- [x] Update `ResourceController::storeResource()` to handle both types
- [x] Update `ResourceController::getFileUrl()` to return video_url for video links
- [x] Update `ResourceController::emailView()` to redirect to video_url for video links
- [x] Run Wayfinder generation

**Frontend:**
- [x] Update `UploadResourceSheet.vue` with type selector and conditional inputs
- [x] Update `Resources/Index.vue` button text to "Upload Resource"
- [x] Update `ResourceCard.vue` to handle video link display
- [x] Update `ResourcePreview.vue` to handle video link embed
- [x] Update `EditResourceSheet.vue` to show video URL info for video links

**Testing:**
- [x] Create feature test for video link resource creation
- [x] Create feature test verifying file upload still works

### Reflection
Implementation went cleanly. All existing patterns were followed (Controller → Service → Action). Conditional validation in FormRequest keeps the API clean. The frontend type selector uses Button variant toggling since no Tabs/RadioGroup ShadCN components were available.

---

## 💭 PHASE 3: FINAL REFLECTION & DOCUMENTATION
**Status:** ✅ Complete

### Summary
Extended the resource upload system to support both file uploads and video links (YouTube/Vimeo). Added `resource_type` and `video_url` columns to the resources table, made file-specific columns nullable, created a new `StoreVideoLinkResourceAction`, updated all layers (Controller, Service, FormRequest, Model), and updated 5 Vue components to handle the new resource type with embedded video previews.

### Files Changed
**Backend (7 files):**
1. `database/migrations/2026_03_05_133615_add_resource_type_and_video_url_to_resources_table.php` — NEW
2. `app/Models/Resource.php` — Added fillable fields, `isVideoLink()`, `isFile()`, null-safe `isVideo()`
3. `app/Http/Requests/StoreResourceRequest.php` — Conditional validation for file vs video_link
4. `app/Actions/Resource/StoreVideoLinkResourceAction.php` — NEW
5. `app/Services/ResourceService.php` — Injected new action, added `storeVideoLinkResource()`
6. `app/Http/Controllers/ResourceController.php` — Branching in storeResource, getFileUrl, emailView

**Frontend (5 files):**
7. `resources/js/components/Resources/UploadResourceSheet.vue` — Type selector, conditional inputs
8. `resources/js/pages/Resources/Index.vue` — Button text + ResourceItem interface
9. `resources/js/components/Resources/ResourceCard.vue` — Video link icon/info display
10. `resources/js/components/Resources/ResourcePreview.vue` — YouTube/Vimeo iframe embed
11. `resources/js/components/Resources/EditResourceSheet.vue` — Video URL in read-only info

**Documentation (1 file):**
12. `.claude/database-schema.md` — Updated resources table docs

**Tests (1 file):**
13. `tests/Feature/Resources/ResourceUploadTest.php` — NEW (6 test cases)

### Potential Overhead / Anti-patterns
- The `resource_type` column uses a plain string rather than a DB enum — this is actually better for Laravel as DB enums are harder to modify. Could consider a PHP Enum backing if more types are added later.
- The regex validation for YouTube/Vimeo URLs in StoreResourceRequest may be too strict or miss edge cases (e.g., YouTube Shorts URLs, Vimeo private videos with hash). Consider relaxing if users report issues.
- No migration for a `ResourceFactory` — tests create ResourceFolder manually. Could benefit from factories if more resource tests are needed.

### Score
8/10
