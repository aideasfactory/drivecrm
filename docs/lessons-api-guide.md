# Lessons API — Mobile Developer Guide

> This document explains how the Lessons API works, how lessons relate to other data, and how to render lesson cards in the mobile app. Use this alongside the main API reference (`api.md`).

---

## Table of Contents

- [The Big Picture](#the-big-picture)
- [Lesson Lifecycle](#lesson-lifecycle)
- [Card Status System](#card-status-system)
- [Reflective Logs](#reflective-logs)
- [Resources](#resources)
- [Endpoints](#endpoints)
  - [Lesson List](#lesson-list)
  - [Lesson Detail](#lesson-detail)
- [How to Render the Lesson List Screen](#how-to-render-the-lesson-list-screen)
- [How to Render the Lesson Detail Screen](#how-to-render-the-lesson-detail-screen)
- [Data Relationships Diagram](#data-relationships-diagram)
- [Field Reference](#field-reference)

---

## The Big Picture

A **Student** purchases a **Package** (e.g. "10 Hour Package") which creates an **Order**. That order generates individual **Lessons** — one for each hour in the package. Each lesson is taught by an **Instructor** on a scheduled date/time.

```
Student → Order (package purchase) → Lessons (individual hours)
                                        ├── Reflective Log (student fills in after lesson)
                                        ├── Resources (PDFs, videos attached to lesson)
                                        ├── Lesson Payment (payment tracking)
                                        └── Payout (instructor payment)
```

Both **students** and **instructors** can view lessons via the API. The backend enforces that:
- Students can only see their own lessons
- Instructors can only see lessons for students assigned to them

---

## Lesson Lifecycle

A lesson moves through these stages:

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────┐
│  SCHEDULED  │ ──► │  LESSON HAPPENS  │ ──► │  SIGN-OFF   │
│  (future)   │     │  (date passes)   │     │  (complete)  │
└─────────────┘     └──────────────────┘     └─────────────┘
                           │                        │
                           │ If NOT signed off:     │ Requires:
                           │ • Reflective log       │ • Reflective log ✓
                           │   is missing           │ • Instructor signs off
                           │ • Shows as RED card    │ • Shows as GREEN card
                           │                        │
                           ▼                        ▼
                    ┌──────────────┐         ┌──────────────┐
                    │ NEEDS SIGN   │         │  SIGNED OFF  │
                    │ OFF (red)    │         │  (green)     │
                    └──────────────┘         └──────────────┘
```

**Key rule:** A lesson cannot be signed off until the student has submitted a reflective log. Past lessons without a reflective log will always show as red ("needs sign-off").

---

## Card Status System

Every lesson returned by the API includes a `card_status` field. This tells the app which colour/style to use for the lesson card. **The backend computes this — the app should not re-calculate it.**

| `card_status` | Card Colour | Meaning | When |
|---------------|-------------|---------|------|
| `signed_off` | **Green** | Lesson complete, all done | Past lesson where `completed_at` is set |
| `needs_sign_off` | **Red** | Lesson happened but wasn't signed off | Past lesson where `completed_at` is null (reflective log is missing) |
| `current` | **Orange** | The next lesson to deal with | The chronologically next lesson from today onwards that isn't completed |
| `upcoming` | **Blue** | Future lesson, nothing to do yet | Any future lesson beyond the "current" one |

### Visual Example (timeline)

```
Past ◄──────────────────────────────────────────────────► Future

 Mar 10        Mar 12        Mar 15      ► TODAY ◄    Mar 20        Mar 25
┌────────┐  ┌────────┐  ┌────────┐              ┌────────┐  ┌────────┐
│ GREEN  │  │ GREEN  │  │  RED   │              │ ORANGE │  │  BLUE  │
│signed  │  │signed  │  │needs   │              │current │  │upcoming│
│off     │  │off     │  │sign-off│              │        │  │        │
└────────┘  └────────┘  └────────┘              └────────┘  └────────┘
 completed   completed   NOT completed          Next lesson   Future
 ✓ log       ✓ log       ✗ no log               to sign off   lesson
```

### Important Notes

- There is only ever **one** `current` (orange) lesson — the very next one chronologically
- A lesson on **today's date** can be the `current` lesson
- If a lesson is in the past but `completed_at` is set, it's always `signed_off` (green) regardless of reflective log
- If a lesson is in the past and `completed_at` is null, it's always `needs_sign_off` (red)
- The `status` field (`pending`, `completed`, `cancelled`) is the database status. The `card_status` is the computed UI status — **use `card_status` for rendering cards**

---

## Reflective Logs

A reflective log is the student's self-assessment after a lesson. It has four text fields:

| Field | Purpose |
|-------|---------|
| `what_i_learned` | What the student learned during the lesson |
| `what_went_well` | What went well |
| `what_to_improve` | Areas the student needs to improve |
| `additional_notes` | Any extra notes |

### How reflective logs relate to card status

- `has_reflective_log: false` + past lesson = **red card** (needs sign-off, blocked by missing log)
- `has_reflective_log: true` + past lesson + signed off = **green card**
- `has_reflective_log` is irrelevant for `current` and `upcoming` lessons (they haven't happened yet)

### Where reflective log data appears

- **Lesson list:** Only `has_reflective_log` (boolean) — use this to show an indicator icon
- **Lesson detail:** Full `reflective_log` object with all fields, or `null` if not submitted

---

## Resources

Resources are learning materials (PDFs, videos) attached to a lesson. A lesson can have zero or many resources. Resources are added by the instructor or recommended by the system.

### Where resource data appears

- **Lesson list:** `resources_count` (integer) — use this to show a count badge
- **Lesson detail:** Full `resources` array with each resource's metadata

### Resource object fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Display title |
| `description` | string\|null | Description text |
| `resource_type` | string | Either `video_link` or `file` |
| `video_url` | string\|null | URL for video resources (Vimeo/YouTube) |
| `file_path` | string\|null | Server file path for file resources |
| `file_name` | string\|null | Original uploaded file name |
| `file_size` | integer\|null | File size in bytes |
| `mime_type` | string\|null | MIME type (e.g. `application/pdf`, `video/mp4`) |
| `thumbnail_url` | string\|null | Thumbnail image URL if available |

**Rendering tip:** Check `resource_type` to decide how to display:
- `video_link` → show video player or link to `video_url`
- `file` → show download button, use `mime_type` for icon (PDF icon, image preview, etc.)

---

## Endpoints

### Lesson List

```
GET /api/v1/students/{student}/lessons
Authorization: Bearer {token}
Accept: application/json
```

Returns all lessons for a student, sorted by date descending (most recent first). Each lesson includes `card_status` for rendering.

**Response shape:**
```json
{
  "data": [
    {
      "id": 1,
      "order_id": 1,
      "instructor_name": "John Smith",
      "package_name": "10 Hour Package",
      "date": "2026-03-20",
      "start_time": "09:00",
      "end_time": "10:00",
      "status": "pending",
      "completed_at": null,
      "card_status": "current",
      "has_reflective_log": false,
      "resources_count": 0,
      "payment_status": "paid"
    }
  ]
}
```

**List fields summary:**

| Field | Type | Use for |
|-------|------|---------|
| `id` | integer | Unique lesson ID, use for navigation to detail |
| `order_id` | integer | Which order/package this lesson is from |
| `instructor_name` | string\|null | Display on card |
| `package_name` | string\|null | Display on card |
| `date` | string\|null | Display date (YYYY-MM-DD) |
| `start_time` | string\|null | Display time (HH:MM) |
| `end_time` | string\|null | Display time (HH:MM) |
| `status` | string | Database status: `pending`, `completed`, `cancelled` |
| `completed_at` | string\|null | ISO 8601 timestamp |
| `card_status` | string | **Use this for card colour/style** |
| `has_reflective_log` | boolean | Show indicator icon on card |
| `resources_count` | integer | Show count badge on card |
| `payment_status` | string\|null | `paid`, `due`, `refunded`, or null |

---

### Lesson Detail

```
GET /api/v1/students/{student}/lessons/{lesson}
Authorization: Bearer {token}
Accept: application/json
```

Returns full detail for a single lesson including the reflective log and attached resources.

**Response shape:**
```json
{
  "data": {
    "id": 2,
    "order_id": 1,
    "instructor_id": 1,
    "instructor_name": "John Smith",
    "package_name": "10 Hour Package",
    "amount_pence": 3500,
    "date": "2026-03-18",
    "start_time": "14:00",
    "end_time": "15:00",
    "status": "completed",
    "completed_at": "2026-03-18T15:05:00.000000Z",
    "summary": "Good progress on parallel parking. Needs more practice with mirrors.",
    "payment_status": "paid",
    "payment_mode": "upfront",
    "payout_status": "paid",
    "has_payout": true,
    "calendar_date": "2026-03-18",
    "card_status": "signed_off",
    "has_reflective_log": true,
    "reflective_log": {
      "id": 1,
      "what_i_learned": "How to parallel park between two cars",
      "what_went_well": "Managed to park first time in a tight space",
      "what_to_improve": "Need to check mirrors more frequently",
      "additional_notes": null,
      "created_at": "2026-03-18T15:10:00.000000Z"
    },
    "resources": [
      {
        "id": 5,
        "title": "Parallel Parking Guide",
        "description": "Step-by-step guide to parallel parking",
        "resource_type": "file",
        "video_url": null,
        "file_path": "resources/parallel-parking-guide.pdf",
        "file_name": "parallel-parking-guide.pdf",
        "file_size": 245760,
        "mime_type": "application/pdf",
        "thumbnail_url": null
      }
    ]
  }
}
```

**Additional detail fields (not in list):**

| Field | Type | Use for |
|-------|------|---------|
| `instructor_id` | integer | Instructor record ID |
| `amount_pence` | integer\|null | Lesson cost (3500 = £35.00) |
| `summary` | string\|null | Instructor's notes about the lesson |
| `payment_mode` | string\|null | `upfront` or `weekly` |
| `payout_status` | string\|null | `pending`, `paid`, `failed`, or null |
| `has_payout` | boolean | Whether instructor has been paid |
| `calendar_date` | string\|null | Calendar slot date |
| `reflective_log` | object\|null | Full reflective log (see fields above), or null |
| `resources` | array | List of attached resources (can be empty `[]`) |

---

## How to Render the Lesson List Screen

```
┌─────────────────────────────────────────┐
│  My Lessons                              │
├─────────────────────────────────────────┤
│                                          │
│  ┌─ ORANGE ─────────────────────────┐   │
│  │ 📅 20 Mar 2026 · 09:00–10:00    │   │
│  │ 👤 John Smith                     │   │
│  │ 📦 10 Hour Package               │   │
│  │ 💳 Paid                           │   │
│  │ Status: Current                   │   │
│  └──────────────────────────────────┘   │
│                                          │
│  ┌─ GREEN ──────────────────────────┐   │
│  │ 📅 18 Mar 2026 · 14:00–15:00    │   │
│  │ 👤 John Smith                     │   │
│  │ 📦 10 Hour Package               │   │
│  │ 💳 Paid · 📝 Log ✓ · 📎 2       │   │
│  │ Status: Signed Off                │   │
│  └──────────────────────────────────┘   │
│                                          │
│  ┌─ RED ────────────────────────────┐   │
│  │ 📅 15 Mar 2026 · 10:00–11:00    │   │
│  │ 👤 John Smith                     │   │
│  │ 📦 10 Hour Package               │   │
│  │ 💳 Paid · 📝 Log ✗              │   │
│  │ Status: Needs Sign-Off            │   │
│  │ ⚠️ Reflective log required        │   │
│  └──────────────────────────────────┘   │
│                                          │
│  ┌─ BLUE ───────────────────────────┐   │
│  │ 📅 25 Mar 2026 · 09:00–10:00    │   │
│  │ 👤 John Smith                     │   │
│  │ 📦 10 Hour Package               │   │
│  │ Status: Upcoming                  │   │
│  └──────────────────────────────────┘   │
│                                          │
└─────────────────────────────────────────┘
```

**Rendering logic pseudocode:**

```
for each lesson in data:
    switch lesson.card_status:
        case "signed_off":    → green card, show checkmark
        case "needs_sign_off": → red card, show warning + "Reflective log required"
        case "current":        → orange card, highlight as active
        case "upcoming":       → blue card, muted/dimmed style

    if lesson.has_reflective_log:
        show log indicator ✓
    else if lesson.card_status == "needs_sign_off":
        show log indicator ✗ with warning

    if lesson.resources_count > 0:
        show badge with count

    if lesson.payment_status == "paid":
        show "Paid" label
    else if lesson.payment_status == "due":
        show "Payment Due" label
    else if lesson.payment_status is null:
        show "Awaiting Payment" for current, hide for upcoming
```

---

## How to Render the Lesson Detail Screen

```
┌─────────────────────────────────────────┐
│  ← Back                                 │
│                                          │
│  Lesson Detail              [GREEN BADGE]│
│  ─────────────────────────────────────── │
│  📅 18 March 2026                        │
│  🕐 14:00 – 15:00                        │
│  👤 John Smith                           │
│  📦 10 Hour Package                      │
│  💰 £35.00                               │
│  💳 Payment: Paid (upfront)              │
│                                          │
│  ─── Instructor Summary ───              │
│  "Good progress on parallel parking.     │
│   Needs more practice with mirrors."     │
│                                          │
│  ─── My Reflective Log ───              │
│  What I learned:                         │
│    "How to parallel park between two     │
│     cars"                                │
│  What went well:                         │
│    "Managed to park first time in a      │
│     tight space"                         │
│  What to improve:                        │
│    "Need to check mirrors more           │
│     frequently"                          │
│                                          │
│  ─── Resources (1) ───                   │
│  📄 Parallel Parking Guide               │
│     PDF · 240 KB                         │
│     [Download]                           │
│                                          │
└─────────────────────────────────────────┘
```

**Conditional sections:**

| Section | Show when |
|---------|-----------|
| Instructor Summary | `summary` is not null |
| Reflective Log | `reflective_log` is not null |
| "Add Reflective Log" button | `has_reflective_log` is false AND `card_status` is `needs_sign_off` or `current` |
| Resources | `resources` array is not empty |
| Payment info | Always show, format based on `payment_status` and `payment_mode` |

---

## Data Relationships Diagram

```
Student
  └── Orders (1 per package purchase)
        ├── Package (name, hours, price)
        └── Lessons (1 per hour in package)
              ├── card_status (computed: signed_off | needs_sign_off | current | upcoming)
              ├── Instructor (who teaches)
              ├── Lesson Payment (payment tracking for weekly mode)
              ├── Payout (instructor payment after sign-off)
              ├── Reflective Log (0 or 1 per lesson — student self-assessment)
              │     ├── what_i_learned
              │     ├── what_went_well
              │     ├── what_to_improve
              │     └── additional_notes
              └── Resources (0 to many — learning materials)
                    ├── title, description
                    ├── resource_type (video_link | file)
                    ├── video_url (for videos)
                    └── file_path, file_name, mime_type (for files)
```

---

## Field Reference

### card_status values

| Value | Colour | Icon suggestion | User action |
|-------|--------|-----------------|-------------|
| `signed_off` | Green (#22C55E) | Checkmark | View only |
| `needs_sign_off` | Red (#EF4444) | Warning/exclamation | Tap to add reflective log |
| `current` | Orange (#F59E0B) | Clock/arrow | Tap to view/prepare |
| `upcoming` | Blue (#3B82F6) | Calendar | View only |

### payment_status values

| Value | Meaning | Display suggestion |
|-------|---------|-------------------|
| `paid` | Payment received | "Paid" in green |
| `due` | Payment is due | "Payment Due" in orange |
| `refunded` | Payment was refunded | "Refunded" in grey |
| `null` | No payment record yet | "Awaiting Payment" for current, hide for upcoming |

### status vs card_status

These are **different fields** — don't confuse them:

| Field | Source | Purpose |
|-------|--------|---------|
| `status` | Database column | Internal lesson state: `pending`, `completed`, `cancelled` |
| `card_status` | Computed by API | UI rendering: `signed_off`, `needs_sign_off`, `current`, `upcoming` |

**Always use `card_status` for card colours.** The `status` field is useful for filtering or conditional logic (e.g. hide cancelled lessons) but should not drive the card appearance.

---

> **Last updated:** 2026-03-17
> **API version:** v1
> **Base URL:** `https://drivecrm.test/api/v1`
