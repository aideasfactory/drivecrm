# Lesson Sign-Off Process

## Overview

The lesson sign-off flow allows instructors to mark a lesson as completed, triggering a Stripe payout to their Connect account, activity logging, and a feedback request email to the student. The entire process runs asynchronously via a queued job.

---

## Flow

```
Instructor clicks "Sign Off" on a lesson (admin sheet or app)
  → Enters a single Lesson Summary (what was covered) — same field as admin
  → Confirms
  → POST .../sign-off with { "summary": "..." }
  → Controller validates (lesson belongs to student, lesson is pending, instructor assigned)
  → Mobile API: saves summary, marks the lesson completed in this request
    (calendar + order), returns the lesson. Four-prompt reflective log is NOT required.
  → Admin web: still dispatches the job and returns "being processed"
  → Dispatches ProcessLessonSignOffJob (payout + emails + AI from summary)
  → Job runs SignOffLessonAction (idempotent if already completed):
      1. MarkLessonCompletedAction (status=completed, completed_at=now) — skipped if done
      2. UpdateCalendarItemCompletedAction (calendar_item status=completed)
      3. CheckOrderCompletionAction (if all lessons done → order completed)
      4. CreateLessonPayoutAction OUTSIDE the completion transaction
         (Stripe failure does not roll back signed-off status)
  → LessonSignOffService also:
      5. Logs activity for student AND instructor
      6. Sends LessonFeedbackRequest email to student
```

---

## Files

### Backend

| File | Purpose |
|------|---------|
| `app/Http/Controllers/PupilController.php` | `lessons()` and `signOffLesson()` endpoints |
| `app/Http/Controllers/Api/V1/LessonSignOffController.php` | Mobile API sign-off (persist + complete + return lesson) |
| `app/Http/Controllers/Api/V1/LessonReflectiveLogController.php` | Mobile API reflective-log upsert |
| `app/Services/LessonSignOffService.php` | Orchestrates actions + activity log + email |
| `app/Jobs/ProcessLessonSignOffJob.php` | Queued job (3 retries, 30s backoff) |
| `app/Actions/Student/Lesson/GetStudentLessonsAction.php` | Fetches lessons with all relations |
| `app/Actions/Student/Lesson/SaveReflectiveLogAction.php` | Upserts `reflective_logs` for a lesson |
| `app/Actions/Student/Lesson/SignOffLessonAction.php` | Completes the lesson, then payout (not in the same transaction) |
| `app/Actions/Student/Lesson/MarkLessonCompletedAction.php` | Sets lesson status=completed |
| `app/Actions/Student/Lesson/UpdateCalendarItemCompletedAction.php` | Sets calendar_item status=completed |
| `app/Actions/Student/Lesson/CreateLessonPayoutAction.php` | Creates Payout + Stripe Transfer |
| `app/Actions/Student/Lesson/CheckOrderCompletionAction.php` | Checks/marks order as completed |
| `app/Mail/LessonFeedbackRequest.php` | Mailable for feedback request |
| `resources/views/emails/lesson-feedback-request.blade.php` | Email template |

### Frontend

| File | Purpose |
|------|---------|
| `resources/js/components/Instructors/Tabs/Student/LessonsSubTab.vue` | Lesson list + sign-off UI |

### Routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/students/{student}/lessons` | `students.lessons` |
| POST | `/students/{student}/lessons/{lesson}/sign-off` | `students.lessons.sign-off` |
| PUT/POST | `/api/v1/students/{student}/lessons/{lesson}/reflective-log` | (API only) |

---

## API Response Shapes

### GET /students/{student}/lessons

```json
{
  "lessons": [
    {
      "id": 1,
      "order_id": 1,
      "instructor_id": 1,
      "instructor_name": "John Doe",
      "package_name": "10 Lesson Package",
      "amount_pence": 3500,
      "date": "2026-02-10",
      "start_time": "09:00",
      "end_time": "10:00",
      "status": "pending",
      "completed_at": null,
      "payment_status": "paid",
      "payment_mode": "upfront",
      "payout_status": null,
      "has_payout": false,
      "calendar_date": "2026-02-10"
    }
  ]
}
```

### POST /students/{student}/lessons/{lesson}/sign-off

**Admin web success (200):**
```json
{ "message": "Lesson sign-off is being processed." }
```

**Mobile API success (200):**
```json
{ "message": "Lesson signed off.", "data": { "status": "completed", "card_status": "signed_off" } }
```

**Error (422):**
```json
{ "message": "This lesson has already been completed." }
```

### PUT /api/v1/students/{student}/lessons/{lesson}/reflective-log

Leftover four-prompt upsert. Not part of sign-off. Kept so old app builds do not 404.

---

## Validation Rules

The controller validates:
1. Lesson belongs to the student (via order relationship)
2. Lesson status is `pending` (not already completed)
3. Instructor is assigned to the lesson

The SignOffLessonAction validates:
1. Lesson is pending (or already completed — job is then payout-only)
2. For weekly payment mode: LessonPayment must be `paid`
3. For upfront mode: order must be `active`

The four-prompt reflective log is leftover and is not required for sign-off
on admin or the mobile API. Stripe Connect onboarding is checked when creating
the payout, not when marking the lesson complete.

---

## Stripe Payout Logic

Mirrors `v1/LessonController@complete`:
1. Create Payout record (status=pending)
2. Call `StripeService::createTransfer()` with amount, instructor's Connect account ID, and metadata
3. On success: update Payout with transfer_id, status=paid, paid_at
4. On failure: mark Payout status=failed, re-throw exception

---

## Activity Logs

Two activity log entries are created:
1. **Student log**: "Lesson on {date} signed off by {instructor}" (category: lesson)
2. **Instructor log**: "Signed off lesson for {student} on {date}" (category: lesson)
