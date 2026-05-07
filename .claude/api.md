# Drive CRM — Mobile API Reference

> **Cheat sheet for mobile app developers.** Paste this into your mobile project for a complete reference of every API endpoint, expected request data, and response format.

---

## Table of Contents

- [Authentication Setup](#authentication-setup)
- [Making API Calls](#making-api-calls)
- [Error Handling](#error-handling)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Instructor](#instructor)
    - [Profile](#put-apiv1instructorprofile)
    - [Profile Picture](#post-apiv1instructorprofilepicture)
    - [Students](#get-apiv1instructorstudents)
    - [Lessons](#get-apiv1instructorlessonsdate)
    - [Notify On Way](#post-apiv1instructorlessonslessonnotify-on-way)
    - [Notify Arrived](#post-apiv1instructorlessonslessonnotify-arrived)
    - [Update Mileage](#patch-apiv1instructorlessonslessonmileage)
    - [Packages (List)](#get-apiv1instructorpackages)
    - [Packages (Create)](#post-apiv1instructorpackages)
    - [Packages (Update)](#put-apiv1instructorpackagespackage)
    - [Calendar Items](#get-apiv1instructorcalendaritems)
    - [Finances (Config)](#get-apiv1instructorfinancesconfig)
    - [Finances (Summary)](#get-apiv1instructorfinancessummary)
    - [Finances (List)](#get-apiv1instructorfinances)
    - [Finances (Show)](#get-apiv1instructorfinancesfinance)
    - [Finances (Create)](#post-apiv1instructorfinances)
    - [Finances (Update)](#put-apiv1instructorfinancesfinance)
    - [Finances (Delete)](#delete-apiv1instructorfinancesfinance)
    - [Finances — Upload Receipt](#post-apiv1instructorfinancesfinancereceipt)
    - [Finances — Delete Receipt](#delete-apiv1instructorfinancesfinancereceipt)
    - [Mileage (List)](#get-apiv1instructormileage)
    - [Mileage (Show)](#get-apiv1instructormileagemileagelog)
    - [Mileage (Create)](#post-apiv1instructormileage)
    - [Mileage (Update)](#put-apiv1instructormileagemileagelog)
    - [Mileage (Delete)](#delete-apiv1instructormileagemileagelog)
  - [Student Home](#student-home)
    - [Instructor Profile](#get-apiv1studentinstructor)
    - [Dashboard](#get-apiv1studentdashboard)
  - [Package Pricing](#get-apiv1packagespackagepricing)
  - [Students](#students)
    - [Attach to Instructor](#post-apiv1studentsattach)
    - [CRUD](#post-apiv1students)
    - [Lessons](#get-apiv1studentsstudentlessons)
    - [Lesson Detail](#get-apiv1studentsstudentlessonslesson)
    - [Lesson Sign-Off](#post-apiv1studentsstudentlessonslessonsign-off)
    - [Lesson Resources](#post-apiv1studentsstudentlessonslessonresources)
    - [Notes](#get-apiv1studentsstudentnotes)
    - [Checklist Items](#get-apiv1studentsstudentchecklist-items)
    - [Pickup Points (List)](#get-apiv1studentsstudentpickup-points)
    - [Pickup Points (Create)](#post-apiv1studentsstudentpickup-points)
    - [Pickup Points (Update)](#put-apiv1studentsstudentpickup-pointspickuppoint)
    - [Pickup Points (Delete)](#delete-apiv1studentsstudentpickup-pointspickuppoint)
    - [Pickup Points (Set Default)](#patch-apiv1studentsstudentpickup-pointspickuppointdefault)
    - [Orders](#post-apiv1studentsstudentorders)
  - [Resources](#get-apiv1resources)
    - [Resource Detail](#get-apiv1resourcesresource)
  - [Messages](#messages)
    - [Conversations List](#get-apiv1messagesconversations)
    - [Conversation with Instructor (Student)](#get-apiv1messagesconversationsinstructor)
    - [Conversation by User ID](#get-apiv1messagesconversationsuser)
    - [Send Message](#post-apiv1messages)
  - [Push Notifications](#push-notifications)
    - [Store Push Token](#post-apiv1push-token)
  - [Mock Tests](#mock-tests)
    - [Summary](#get-apiv1studentmock-testssummary)
    - [Start Test](#post-apiv1studentmock-testsstart)
    - [Submit Test](#post-apiv1studentmock-testsmocktestsubmit)
    - [Review Test](#get-apiv1studentmock-testsmocktestreview)
  - [Hazard Perception](#hazard-perception)
    - [Videos (List)](#get-apiv1studenthazard-perceptionvideos)
    - [Submit Attempt](#post-apiv1studenthazard-perceptionvideoshazardperceptionvideosubmit)
    - [Summary](#get-apiv1studenthazard-perceptionsummary)
  - [Student Activity Log](#get-apiv1studentactivity-logs)
- [Profile Object by Role](#profile-object-by-role)
- [Appendix: User Roles](#appendix-user-roles)
- [Progress Tracker](#progress-tracker)
- [Changelog](#changelog)

---

## Authentication Setup

### Stack

| Layer | Package | Purpose |
|-------|---------|---------|
| Token Auth | **Laravel Sanctum** | Issues and validates Bearer tokens for mobile |
| Auth Features | **Laravel Fortify** | Registration, login, password reset, 2FA |
| Guard | `auth:sanctum` | Protects all API routes |

### How It Works

1. Mobile app sends `email`, `password`, `device_name`, and `role` to `/api/v1/auth/login`
2. Server validates credentials and returns a **plain-text Bearer token**
3. Mobile app stores the token securely (Keychain on iOS, EncryptedSharedPreferences on Android)
4. Every subsequent request includes the token in the `Authorization` header
5. On logout, the token is revoked server-side

### Token Rules

- Tokens do **not expire** by default (can be configured in `config/sanctum.php`)
- Each device gets its own token (identified by `device_name`)
- Users can have multiple active tokens (one per device)
- Logging out revokes **only the current token**, not all tokens
- The plain-text token is only returned **once** at login — it cannot be retrieved again

---

## Making API Calls

### Base URL

```
https://drivecrm.test/api/v1
```

> Production URL will differ. Always use the base URL from your environment config.

### Headers (Every Request)

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}
```

> The `Accept: application/json` header is **critical**. Without it, Laravel will return HTML error pages instead of JSON errors.

### Multipart Requests

For file uploads (e.g., profile picture), use `multipart/form-data` instead of `application/json`:

```http
Accept: application/json
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

### Example (cURL)

```bash
# Login (no token needed)
curl -X POST https://drivecrm.test/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "secret", "device_name": "iPhone 15", "role": "instructor"}'

# Authenticated request
curl -X GET https://drivecrm.test/api/v1/auth/user \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|abc123def456..."
```

### Example (JavaScript/React Native)

```javascript
const API_BASE = 'https://drivecrm.test/api/v1';

// Store token after login
const login = async (email, password) => {
  const response = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email,
      password,
      device_name: 'My App - iPhone 15',
      role: 'instructor', // or 'student'
    }),
  });
  const data = await response.json();
  // Store data.token securely
  return data;
};

// Authenticated request helper
const apiRequest = async (endpoint, options = {}) => {
  const token = await getStoredToken(); // Your secure storage getter
  return fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      ...options.headers,
    },
  });
};
```

---

## Error Handling

### Standard Error Response Format

All API errors follow a consistent JSON format:

#### 401 Unauthorized (invalid/missing token)
```json
{
  "message": "Unauthenticated."
}
```

#### 403 Forbidden (valid token, insufficient permissions)
```json
{
  "message": "This action is unauthorized."
}
```

#### 404 Not Found
```json
{
  "message": "No query results for model [App\\Models\\Student] 999."
}
```

#### 422 Validation Error
```json
{
  "message": "The email field is required.",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "password": [
      "The password field is required."
    ]
  }
}
```

#### 429 Too Many Requests
```json
{
  "message": "Too Many Attempts.",
  "retry_after": 60
}
```

#### 500 Server Error
```json
{
  "message": "Server Error"
}
```

### HTTP Status Code Summary

| Code | Meaning | When |
|------|---------|------|
| 200 | OK | Successful GET, PUT, PATCH |
| 201 | Created | Successful POST (resource created) |
| 204 | No Content | Successful DELETE |
| 401 | Unauthorized | Missing/invalid token |
| 403 | Forbidden | Token valid but user lacks permission |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Something broke |

---

## Endpoints

---

### Auth

---

#### `POST /api/v1/auth/login`

**Auth required:** No

Login and receive a Bearer token for subsequent API calls.

**Request Body:**
```json
{
  "email": "instructor@example.com",
  "password": "password123",
  "device_name": "iPhone 15 Pro",
  "role": "instructor"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `email` | string | Yes | User's email address |
| `password` | string | Yes | User's password |
| `device_name` | string | Yes | Human-readable device identifier (e.g., "John's iPhone 15") |
| `role` | string | Yes | Must be `student` or `instructor`. The user's actual role must match, otherwise login is rejected. |

**Success Response:** `200 OK`
```json
{
  "token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx",
  "user": {
    "id": 1,
    "name": "John Smith",
    "email": "instructor@example.com",
    "role": "instructor",
    "password_change_required": false,
    "email_verified_at": "2026-03-14T10:00:00.000000Z",
    "created_at": "2026-01-15T08:30:00.000000Z",
    "profile": {
      "id": 1,
      "bio": null,
      "transmission_type": "manual",
      "status": "active",
      "address": "1 High Street",
      "postcode": "TS7 0AB",
      "pin": "4827",
      "onboarding_complete": false,
      "charges_enabled": false,
      "payouts_enabled": false,
      "profile_picture_url": null
    }
  }
}
```

> **Note:** The `profile` object contains role-specific data. For `instructor` users it returns instructor fields; for `student` users it returns student fields. See [Profile Object by Role](#profile-object-by-role) below.
>
> **Note:** When `password_change_required` is `true`, the mobile app should force the user to change their password before proceeding. This is set when a temporary password is issued (e.g., instructor-created student accounts, admin resets). Use `POST /api/v1/auth/change-password` to update.
**Error Response (bad credentials):** `422 Unprocessable Entity`
```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": [
      "The provided credentials are incorrect."
    ]
  }
}
```

**Error Response (role mismatch):** `422 Unprocessable Entity`
```json
{
  "message": "Your account is not registered as a instructor.",
  "errors": {
    "role": [
      "Your account is not registered as a instructor."
    ]
  }
}
```

> This occurs when a student tries to log in via the instructor flow (or vice versa). The mobile app should direct the user to the correct login flow for their account type.

---

#### `POST /api/v1/auth/logout`

**Auth required:** Yes (Bearer token)

Revokes the current token. Other device tokens remain active.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "message": "Logged out successfully."
}
```

---

#### `POST /api/v1/auth/change-password`

**Auth required:** Yes (Bearer token)

Change the authenticated user's password. Validates the current password, updates to the new password, and clears the `password_change_required` flag. Use this endpoint to complete the forced password change flow after logging in with a temporary password.

**Request Body:**
```json
{
  "current_password": "temporaryPassword123",
  "password": "newSecurePassword456",
  "password_confirmation": "newSecurePassword456"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `current_password` | string | Yes | User's current password (the temporary password they received) |
| `password` | string | Yes | New password (must meet Laravel's default password rules) |
| `password_confirmation` | string | Yes | Must match `password` |

**Success Response:** `200 OK`
```json
{
  "success": true
}
```

**Error Response (wrong current password):** `422 Unprocessable Entity`
```json
{
  "message": "The provided password does not match your current password.",
  "errors": {
    "current_password": [
      "The provided password does not match your current password."
    ]
  }
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The password field confirmation does not match.",
  "errors": {
    "password": [
      "The password field confirmation does not match."
    ]
  }
}
```

---

#### `GET /api/v1/auth/user`

**Auth required:** Yes (Bearer token)

Returns the authenticated user's profile with role-specific data.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "name": "John Smith",
    "email": "instructor@example.com",
    "role": "instructor",
    "password_change_required": false,
    "email_verified_at": "2026-03-14T10:00:00.000000Z",
    "created_at": "2026-01-15T08:30:00.000000Z",
    "profile": {
      "id": 1,
      "bio": null,
      "transmission_type": "manual",
      "status": "active",
      "address": "1 High Street",
      "postcode": "TS7 0AB",
      "pin": "4827",
      "onboarding_complete": false,
      "charges_enabled": false,
      "payouts_enabled": false,
      "profile_picture_url": null
    }
  }
}
```

---

#### `POST /api/v1/auth/register/student`

**Auth required:** No

Register a new student account. Creates a base user record with the `student` role and an associated student profile. Returns a Bearer token for immediate use.

**Request Body:**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "phone": "07700900000",
  "device_name": "iPhone 15"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Full name (split into first_name/surname for student record) |
| `email` | string | Yes | Must be unique across all users |
| `password` | string | Yes | Must meet password policy (min 8 chars) |
| `password_confirmation` | string | Yes | Must match `password` |
| `phone` | string | No | Student's phone number (max 20 chars) |
| `device_name` | string | Yes | Human-readable device identifier (max 255 chars) |

**Success Response:** `201 Created`
```json
{
  "token": "2|xyz789abc123def456ghi789jkl012mno345pqr678stu",
  "user": {
    "id": 5,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "student",
    "email_verified_at": null,
    "created_at": "2026-03-15T12:00:00.000000Z",
    "profile": {
      "id": 1,
      "first_name": "Jane",
      "surname": "Doe",
      "phone": "07700900000",
      "status": "active",
      "instructor_id": null
    }
  }
}
```

**Error Response:** `422 Unprocessable Entity`
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

---

#### `POST /api/v1/auth/register/instructor`

**Auth required:** No

Register a new instructor account. Creates a base user record with the `instructor` role and an associated instructor profile. Returns a Bearer token for immediate use. The instructor will still need to complete onboarding (Stripe Connect, etc.) separately.

**Request Body:**
```json
{
  "name": "John Smith",
  "email": "john@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "phone": "07700900001",
  "postcode": "TS7 0AB",
  "address": "1 High Street",
  "transmission_type": "manual",
  "device_name": "Pixel 8"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Instructor's full name (max 255 chars) |
| `email` | string | Yes | Must be unique across all users (max 255 chars) |
| `password` | string | Yes | Must meet password policy (min 8 chars) |
| `password_confirmation` | string | Yes | Must match `password` |
| `phone` | string | No | Instructor's phone number (max 20 chars) |
| `postcode` | string | No | Business postcode (max 10 chars) |
| `address` | string | No | Business address (max 255 chars) |
| `transmission_type` | string | No | One of: `manual`, `automatic`, `both` |
| `device_name` | string | Yes | Human-readable device identifier (max 255 chars) |

**Success Response:** `201 Created`
```json
{
  "token": "3|mno345pqr678stu901vwx234abc567def890ghi123jkl",
  "user": {
    "id": 6,
    "name": "John Smith",
    "email": "john@example.com",
    "role": "instructor",
    "password_change_required": false,
    "email_verified_at": null,
    "created_at": "2026-03-15T12:05:00.000000Z",
    "profile": {
      "id": 1,
      "bio": null,
      "transmission_type": "manual",
      "status": null,
      "address": "1 High Street",
      "postcode": "TS7 0AB",
      "pin": "4827",
      "onboarding_complete": false,
      "charges_enabled": false,
      "payouts_enabled": false,
      "profile_picture_url": null
    }
  }
}
```

**Error Response:** `422 Unprocessable Entity`
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

---

### Instructor

---

#### `PUT /api/v1/instructor/profile`

**Auth required:** Yes (Bearer token — instructor only)

Update the authenticated instructor's own profile. The instructor is derived from the Bearer token — no instructor ID is accepted in the request.

**Request Body:**
```json
{
  "bio": "Experienced driving instructor with 10 years of teaching.",
  "transmission_type": "both",
  "address": "10 High Street",
  "postcode": "TS7 0AB"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `bio` | string\|null | No | Instructor biography (max 1000 chars) |
| `transmission_type` | string\|null | No | One of: `manual`, `automatic`, `both` |
| `address` | string\|null | No | Business address (max 255 chars) |
| `postcode` | string\|null | No | Business postcode (max 10 chars) |

> **Note:** All fields are optional. Only fields included in the request body will be updated. Fields not included remain unchanged.

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "bio": "Experienced driving instructor with 10 years of teaching.",
    "transmission_type": "both",
    "status": "active",
    "address": "10 High Street",
    "postcode": "TS7 0AB",
    "pin": "4827",
    "onboarding_complete": false,
    "charges_enabled": false,
    "payouts_enabled": false,
    "profile_picture_url": null
  }
}
```

**Error Response (not an instructor):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The transmission type field must be one of: manual, automatic, both.",
  "errors": {
    "transmission_type": [
      "The transmission type field must be one of: manual, automatic, both."
    ]
  }
}
```

> **Security:** An instructor can only update their own profile. The policy ensures the authenticated user's instructor record matches the target — there is no way to update another instructor's profile via this endpoint.

---

#### `POST /api/v1/instructor/profile/picture`

**Auth required:** Yes (Bearer token — instructor only)

Upload or replace the instructor's profile picture. Uses `multipart/form-data` encoding.

**Request Body (multipart/form-data):**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `profile_picture` | file | Yes | Image file. Accepted formats: `jpg`, `jpeg`, `png`, `webp`. Max size: 5 MB. |

**Example (cURL):**
```bash
curl -X POST https://drivecrm.test/api/v1/instructor/profile/picture \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|abc123..." \
  -F "profile_picture=@/path/to/photo.jpg"
```

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "bio": "Experienced driving instructor.",
    "transmission_type": "manual",
    "status": "active",
    "address": "10 High Street",
    "postcode": "TS7 0AB",
    "pin": "4827",
    "onboarding_complete": false,
    "charges_enabled": false,
    "payouts_enabled": false,
    "profile_picture_url": "https://drivecrm.test/storage/instructor-profile-pictures/abc123.jpg"
  }
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The profile picture field must be an image.",
  "errors": {
    "profile_picture": [
      "The profile picture field must be an image."
    ]
  }
}
```

> **Note:** If a profile picture already exists, it is replaced. The old file is deleted from storage.

---

#### `DELETE /api/v1/instructor/profile/picture`

**Auth required:** Yes (Bearer token — instructor only)

Delete the instructor's profile picture.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "bio": "Experienced driving instructor.",
    "transmission_type": "manual",
    "status": "active",
    "address": "10 High Street",
    "postcode": "TS7 0AB",
    "pin": "4827",
    "onboarding_complete": false,
    "charges_enabled": false,
    "payouts_enabled": false,
    "profile_picture_url": null
  }
}
```

**Error Response (not an instructor):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

---

#### `GET /api/v1/instructor/students`

**Auth required:** Yes (Bearer token — instructor only)

Returns the authenticated instructor's students grouped by status, plus a recent activity list.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "active": [
      {
        "id": 1,
        "first_name": "Jane",
        "surname": "Doe",
        "email": "jane@example.com",
        "phone": "07700900000",
        "status": "active",
        "has_app": true,
        "updated_at": "2026-03-17T10:30:00+00:00"
      }
    ],
    "passed": [
      {
        "id": 2,
        "first_name": "Tom",
        "surname": "Brown",
        "email": "tom@example.com",
        "phone": "07700900001",
        "status": "passed",
        "has_app": true,
        "updated_at": "2026-03-16T14:00:00+00:00"
      }
    ],
    "inactive": [],
    "recent_activity": [
      {
        "id": 1,
        "first_name": "Jane",
        "surname": "Doe",
        "email": "jane@example.com",
        "phone": "07700900000",
        "status": "active",
        "has_app": true,
        "updated_at": "2026-03-17T10:30:00+00:00"
      }
    ]
  }
}
```

| Group | Description |
|-------|-------------|
| `active` | Students with `status = "active"` |
| `passed` | Students with `status = "passed"` |
| `inactive` | Students with `status = "inactive"` |
| `recent_activity` | 5 most recently updated students (any status), ordered by `updated_at` descending |

**Student Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Student record ID |
| `first_name` | string | Student's first name |
| `surname` | string | Student's surname |
| `email` | string\|null | Student's email (falls back to user email) |
| `phone` | string\|null | Student's phone number |
| `status` | string\|null | Student status (e.g., `active`, `passed`, `inactive`) |
| `has_app` | boolean | Whether the student has a linked user account |
| `updated_at` | string\|null | ISO 8601 timestamp of last update |

> **Note:** Students are automatically scoped to the authenticated instructor. No instructor ID is accepted in the request — it is derived from the Bearer token.

---

#### `GET /api/v1/instructor/lessons/{date}`

**Auth required:** Yes (Bearer token — instructor only)

Returns the authenticated instructor's lessons for a specific date, ordered by start time. Each lesson includes the student details, calendar item data, payment/payout status, and resource counts — designed for a day-view screen in the mobile app.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `date` | string | Date in `YYYY-MM-DD` format (e.g., `2026-03-20`). Must be a valid date. |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "student_lesson_number": 11,
      "order_id": 1,
      "date": "2026-03-20",
      "start_time": "09:00",
      "end_time": "10:00",
      "status": "pending",
      "completed_at": null,
      "summary": null,
      "amount_pence": 3500,
      "student": {
        "id": 1,
        "first_name": "Jane",
        "surname": "Doe",
        "email": "jane@example.com",
        "phone": "07700900000",
        "status": "active"
      },
      "package_name": "10 Hour Package",
      "payment_status": "paid",
      "payment_mode": "upfront",
      "payout_status": null,
      "has_payout": false,
      "calendar_item": {
        "id": 5,
        "start_time": "09:00:00",
        "end_time": "10:00:00",
        "status": "booked",
        "item_type": "slot",
        "notes": null
      },
      "has_reflective_log": false,
      "resources_count": 0
    }
  ]
}
```

**Day Lesson Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Lesson record ID (internal — used for routing) |
| `student_lesson_number` | integer | Per-student running lesson number (1, 2, 3, …). Increments across all of the student's orders and is the user-facing reference shown in the UI |
| `order_id` | integer | The order this lesson belongs to |
| `date` | string\|null | Lesson date (YYYY-MM-DD) |
| `start_time` | string\|null | Start time (HH:MM) |
| `end_time` | string\|null | End time (HH:MM) |
| `status` | string | Lesson status: `pending`, `completed`, or `cancelled` |
| `completed_at` | string\|null | ISO 8601 timestamp when lesson was completed |
| `summary` | string\|null | Instructor lesson summary/notes |
| `amount_pence` | integer\|null | Lesson cost in pence (e.g., 3500 = £35.00) |
| `student` | object\|null | Student details (see Student Object below) |
| `package_name` | string\|null | Name of the package the lesson is part of |
| `payment_status` | string\|null | Payment status: `paid`, `due`, `refunded`, or null |
| `payment_mode` | string\|null | Package payment mode: `upfront` or `weekly` |
| `payout_status` | string\|null | Instructor payout status: `pending`, `paid`, `failed`, or null |
| `has_payout` | boolean | Whether a payout has been created for this lesson |
| `calendar_item` | object\|null | Calendar item data (see Calendar Item Object below) |
| `has_reflective_log` | boolean | Whether a reflective log exists for this lesson |
| `resources_count` | integer | Number of resources attached to this lesson |

**Nested Student Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Student record ID |
| `first_name` | string | Student first name |
| `surname` | string | Student surname |
| `email` | string\|null | Student email (falls back to user email) |
| `phone` | string\|null | Student phone number |
| `status` | string\|null | Student status |

**Nested Calendar Item Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Calendar item ID |
| `start_time` | string | Slot start time (HH:MM:SS) |
| `end_time` | string | Slot end time (HH:MM:SS) |
| `status` | string\|null | Booking lifecycle status: `draft`, `reserved`, `booked`, `completed` |
| `item_type` | string\|null | Calendar item type: `slot`, `travel`, `practical_test` |
| `notes` | string\|null | Notes about this calendar slot |

**Error Response (invalid date):** `422 Unprocessable Entity`
```json
{
  "message": "The date must be a valid date in Y-m-d format.",
  "errors": {
    "date": [
      "The date must be a valid date in Y-m-d format."
    ]
  }
}
```

> **Note:** Lessons are automatically scoped to the authenticated instructor. Lessons are sorted by start time ascending for chronological day-view display.

---

#### `POST /api/v1/instructor/lessons/{lesson}/notify-on-way`

**Auth required:** Yes (Bearer token — instructor only)

Logs that the instructor is on their way to the lesson. Currently writes an activity log entry for the instructor. Push notification to the student will be added in a future release.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `lesson` | integer | Lesson ID. Must belong to the authenticated instructor. |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "message": "On-way notification logged successfully."
}
```

**Error Response (not your lesson):** `403 Forbidden`
```json
{
  "message": "This lesson does not belong to you."
}
```

> **Note:** This is a stub endpoint. The activity is logged but no push notification is sent yet. The mobile app should call this endpoint so the integration is ready when push notifications are implemented.

---

#### `POST /api/v1/instructor/lessons/{lesson}/notify-arrived`

**Auth required:** Yes (Bearer token — instructor only)

Logs that the instructor has arrived at the lesson pickup point. Currently writes an activity log entry for the instructor. Push notification to the student will be added in a future release.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `lesson` | integer | Lesson ID. Must belong to the authenticated instructor. |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "message": "Arrived notification logged successfully."
}
```

**Error Response (not your lesson):** `403 Forbidden`
```json
{
  "message": "This lesson does not belong to you."
}
```

> **Note:** This is a stub endpoint. The activity is logged but no push notification is sent yet. The mobile app should call this endpoint so the integration is ready when push notifications are implemented.

---

#### `PATCH /api/v1/instructor/lessons/{lesson}/mileage`

**Auth required:** Yes (Bearer token — instructor only)

Records or updates the mileage (miles driven) for a lesson. The lesson must belong to the authenticated instructor.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `lesson` | integer | Lesson ID. Must belong to the authenticated instructor. |

**Request Body:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `mileage` | integer\|null | No | min:0, max:9999 | Miles driven during the lesson. Send `null` to clear. |

**Example Request:**
```json
{
  "mileage": 42
}
```

**Success Response:** `200 OK`
```json
{
  "message": "Mileage updated successfully.",
  "mileage": 42
}
```

**Error Response (not your lesson):** `403 Forbidden`
```json
{
  "message": "This lesson does not belong to you."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The mileage field must be an integer.",
  "errors": {
    "mileage": ["The mileage field must be an integer."]
  }
}
```

---

#### `GET /api/v1/instructor/packages`

**Auth required:** Yes (Bearer token — instructor only)

Returns all active packages for the authenticated instructor.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "name": "10 Hour Package",
      "description": "Ten one-hour driving lessons",
      "total_price_pence": 35000,
      "lessons_count": 10,
      "lesson_price_pence": 3500,
      "formatted_total_price": "£350.00",
      "formatted_lesson_price": "£35.00",
      "booking_fee": "10.00",
      "digital_fee": "5.00",
      "total_price": "350.00",
      "weekly_payment": "35.00",
      "active": true,
      "has_stripe_price": true
    }
  ]
}
```

**Package Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Package record ID |
| `name` | string | Package name |
| `description` | string\|null | Package description |
| `total_price_pence` | integer | Total price in pence (e.g., 35000 = £350.00) |
| `lessons_count` | integer | Number of lessons in the package |
| `lesson_price_pence` | integer | Price per lesson in pence |
| `formatted_total_price` | string | Human-readable total price (e.g., "£350.00") |
| `formatted_lesson_price` | string | Human-readable per-lesson price (e.g., "£35.00") |
| `booking_fee` | string | Booking fee amount as decimal string |
| `digital_fee` | string | Digital fee amount as decimal string |
| `total_price` | string | Total price as decimal string |
| `weekly_payment` | string | Weekly payment amount as decimal string |
| `active` | boolean | Whether the package is active |
| `has_stripe_price` | boolean | Whether a Stripe price is configured for this package |

> **Note:** Only active packages are returned. Packages without a Stripe price (`has_stripe_price: false`) cannot be used for upfront payments.

---

#### `POST /api/v1/instructor/packages`

**Auth required:** Yes (Bearer token — instructor only)

Creates a new bespoke package for the authenticated instructor. The instructor must have completed Stripe Connect onboarding (`charges_enabled: true`). A Stripe Product and Price are automatically created during this process.

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Package name (max 255 characters) |
| `description` | string | No | Package description |
| `total_price_pence` | integer | Yes | Total price in pence (e.g., 35000 = £350.00). Min: 0 |
| `lessons_count` | integer | Yes | Number of lessons in the package. Min: 1 |

**Example Request:**
```json
{
  "name": "5 Hour Starter Package",
  "description": "Five one-hour driving lessons for beginners",
  "total_price_pence": 17500,
  "lessons_count": 5
}
```

**Success Response:** `201 Created`
```json
{
  "data": {
    "id": 12,
    "name": "5 Hour Starter Package",
    "description": "Five one-hour driving lessons for beginners",
    "total_price_pence": 17500,
    "lessons_count": 5,
    "lesson_price_pence": 3500,
    "formatted_total_price": "£175.00",
    "formatted_lesson_price": "£35.00",
    "booking_fee": "10.00",
    "digital_fee": "5.00",
    "total_price": "175.00",
    "weekly_payment": "35.00",
    "active": true,
    "has_stripe_price": true
  }
}
```

**Error Responses:**

`422 Unprocessable Entity` — Validation failed:
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["Package name is required"]
  }
}
```

`500 Internal Server Error` — Stripe onboarding incomplete:
```json
{
  "message": "Instructor must complete Stripe Connect onboarding before creating packages."
}
```

---

#### `PUT /api/v1/instructor/packages/{package}`

**Auth required:** Yes (Bearer token — instructor only)

Updates an existing package owned by the authenticated instructor. Returns `403` if the package does not belong to the instructor.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `package` | integer | Package ID |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Package name (max 255 characters) |
| `description` | string | No | Package description |
| `total_price_pence` | integer | Yes | Total price in pence (e.g., 35000 = £350.00). Min: 0 |
| `lessons_count` | integer | Yes | Number of lessons in the package. Min: 1 |

**Example Request:**
```json
{
  "name": "5 Hour Starter Package (Updated)",
  "description": "Five one-hour driving lessons — now with free resources",
  "total_price_pence": 16000,
  "lessons_count": 5
}
```

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 12,
    "name": "5 Hour Starter Package (Updated)",
    "description": "Five one-hour driving lessons — now with free resources",
    "total_price_pence": 16000,
    "lessons_count": 5,
    "lesson_price_pence": 3200,
    "formatted_total_price": "£160.00",
    "formatted_lesson_price": "£32.00",
    "booking_fee": "10.00",
    "digital_fee": "5.00",
    "total_price": "160.00",
    "weekly_payment": "32.00",
    "active": true,
    "has_stripe_price": true
  }
}
```

**Error Responses:**

`403 Forbidden` — Package not owned by this instructor:
```json
{
  "message": "You do not own this package."
}
```

`422 Unprocessable Entity` — Validation failed:
```json
{
  "message": "The name field is required.",
  "errors": {
    "name": ["Package name is required"]
  }
}
```

---

### Instructor Finances

Payments and expenses tracking. Every finance record has an optional receipt (PDF/JPG/PNG) on private S3 — the `receipt.url` field is a short-lived signed URL (20-minute TTL), re-fetch the record to get a fresh URL before rendering.

**Finance Object (shared across all Finance endpoints):**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Finance record ID |
| `type` | string | `payment` or `expense` |
| `category` | string | Slug from `/finances/config`. Default `none` (uncategorised). Valid list depends on `type`: expense categories for expenses, payment categories for payments. |
| `category_label` | string\|null | Human-readable label (e.g., `"Fuel"`). `null` only when `category` is set to an unknown slug. |
| `payment_method` | string\|null | Slug from `/finances/config` (e.g., `bank_transfer`, `card`). `null` when unspecified. |
| `payment_method_label` | string\|null | Human-readable label (e.g., `"Bank Transfer"`). |
| `description` | string | Description (max 255 chars) |
| `amount_pence` | integer | Amount in pence (e.g., 3500 = £35.00) |
| `formatted_amount` | string | Human-readable GBP amount (e.g., `"£35.00"`) |
| `is_recurring` | boolean | Whether this is a recurring entry. **Display-only flag — the backend does not auto-generate future occurrences.** |
| `recurrence_frequency` | string\|null | `weekly`, `monthly`, `yearly`, or `null` when not recurring |
| `date` | string | YYYY-MM-DD |
| `notes` | string\|null | Free-text notes (max 1000 chars) |
| `receipt` | object\|null | `null` when no receipt. Object has `url` (signed S3 URL, 20-min TTL), `original_name`, `mime_type`, `size_bytes`. |
| `created_at` | string | ISO 8601 |
| `updated_at` | string | ISO 8601 |

---

#### `GET /api/v1/instructor/finances/config`

**Auth required:** Yes (Bearer token — instructor only)

Returns the dropdown options used by the finance screens. **Cache this client-side on login and refresh on-demand** — the contents change rarely.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "expense_categories": {
    "none": "None",
    "fuel": "Fuel",
    "insurance": "Insurance",
    "mot": "MOT"
  },
  "payment_categories": {
    "none": "None",
    "franchise_payout": "Franchise Payout",
    "hmrc_tax": "HMRC Tax",
    "referral": "Referral"
  },
  "payment_methods": {
    "bank_transfer": "Bank Transfer",
    "card": "Card",
    "cash": "Cash",
    "cheque": "Cheque",
    "direct_debit": "Direct Debit",
    "paypal": "PayPal",
    "standing_order": "Standing Order"
  },
  "mileage_types": {
    "business": "Business",
    "personal": "Personal"
  },
  "receipt": {
    "max_size_kb": 10240,
    "allowed_mimes": ["pdf", "jpg", "jpeg", "png"]
  }
}
```

Category + payment-method keys above are illustrative — the full list lives in `config/finances.php` on the server. When creating or updating a record, send the **slug** (e.g., `"fuel"`), not the label.

---

#### `GET /api/v1/instructor/finances/summary`

**Auth required:** Yes (Bearer token — instructor only)

Overview screen: finances + mileage for the date range, plus aggregate stats. Designed as a single call the app makes when the finance dashboard loads.

**Query Params:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `from` | string | No | YYYY-MM-DD. Defaults to 30 days before `to`. |
| `to` | string | No | YYYY-MM-DD. Defaults to today. |

When both `from` and `to` are omitted, the response covers **the last 30 days** and returns `date_range.default_applied: true` so the app can show a "Last 30 days" badge.

**Success Response:** `200 OK`
```json
{
  "date_range": {
    "from": "2026-03-25",
    "to": "2026-04-24",
    "default_applied": true
  },
  "finances": [
    { "id": 12, "type": "expense", "category": "fuel", "category_label": "Fuel", "payment_method": "card", "payment_method_label": "Card", "description": "BP Fillup", "amount_pence": 7500, "formatted_amount": "£75.00", "is_recurring": false, "recurrence_frequency": null, "date": "2026-04-22", "notes": null, "receipt": { "url": "https://...", "original_name": "receipt.pdf", "mime_type": "application/pdf", "size_bytes": 45123 }, "created_at": "2026-04-22T08:12:00+00:00", "updated_at": "2026-04-22T08:12:00+00:00" }
  ],
  "mileage": [
    { "id": 5, "date": "2026-04-22", "start_mileage": 45210, "end_mileage": 45250, "miles": 40, "type": "business", "type_label": "Business", "notes": null, "created_at": "2026-04-22T16:00:00+00:00", "updated_at": "2026-04-22T16:00:00+00:00" }
  ],
  "stats": {
    "total_records": 42,
    "total_payments_pence": 123456,
    "total_payments_formatted": "£1,234.56",
    "total_expenses_pence": 65432,
    "total_expenses_formatted": "£654.32",
    "net_balance_pence": 58024,
    "net_balance_formatted": "£580.24",
    "total_trips": 15,
    "business_miles": 450,
    "personal_miles": 80,
    "total_miles": 530
  }
}
```

**Notes:**
- `/summary` is unpaginated within its date range — intended for the overview screen where stats need to reflect the full window.
- For infinite-scroll through history, use `/finances` and `/mileage` list endpoints instead, which cursor-paginate.
- `stats.total_records` counts finance rows only (not mileage).

---

#### `GET /api/v1/instructor/finances`

**Auth required:** Yes (Bearer token — instructor only)

Cursor-paginated list of finance records, ordered by date descending. Intended for the "all finances" scrolling view.

**Query Params:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | No | Filter to `payment` or `expense`. Omit for both. |
| `from` | string | No | YYYY-MM-DD. Defaults to 30 days before `to`. |
| `to` | string | No | YYYY-MM-DD. Defaults to today. |
| `cursor` | string | No | Opaque cursor from a previous response (`next_cursor`). |
| `per_page` | integer | No | 1–100. Default 25. |

**Success Response:** `200 OK`
```json
{
  "data": [
    { "id": 12, "type": "expense", "category": "fuel", "category_label": "Fuel", "payment_method": "card", "payment_method_label": "Card", "description": "BP Fillup", "amount_pence": 7500, "formatted_amount": "£75.00", "is_recurring": false, "recurrence_frequency": null, "date": "2026-04-22", "notes": null, "receipt": null, "created_at": "2026-04-22T08:12:00+00:00", "updated_at": "2026-04-22T08:12:00+00:00" }
  ],
  "path": "https://example.test/api/v1/instructor/finances",
  "per_page": 25,
  "next_cursor": "eyJpZCI6MTIsImRhdGUiOiIyMDI2LTA0LTIyIn0",
  "next_page_url": "https://example.test/api/v1/instructor/finances?cursor=eyJpZCI6MTIsImRhdGUiOiIyMDI2LTA0LTIyIn0",
  "prev_cursor": null,
  "prev_page_url": null
}
```

**Pagination protocol:**
1. First call: omit `cursor`.
2. Response includes `next_cursor`. If it is `null`, there are no more results.
3. Otherwise, make the next call with `?cursor=<next_cursor>` (plus the same `from`/`to`/`type` params) to get the next page.

---

#### `GET /api/v1/instructor/finances/{finance}`

**Auth required:** Yes (Bearer token — instructor only)

Fetch a single finance record by ID. The record must belong to the authenticated instructor (403 otherwise). Useful for detail screens or to refresh a stale `receipt.url`.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `finance` | integer | Finance record ID |

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 12,
    "type": "expense",
    "category": "fuel",
    "category_label": "Fuel",
    "payment_method": "card",
    "payment_method_label": "Card",
    "description": "BP Fillup",
    "amount_pence": 7500,
    "formatted_amount": "£75.00",
    "is_recurring": false,
    "recurrence_frequency": null,
    "date": "2026-04-22",
    "notes": null,
    "receipt": {
      "url": "https://s3.../receipt.pdf?X-Amz-Signature=...",
      "original_name": "bp-fillup.pdf",
      "mime_type": "application/pdf",
      "size_bytes": 45123
    },
    "created_at": "2026-04-22T08:12:00+00:00",
    "updated_at": "2026-04-22T08:12:00+00:00"
  }
}
```

**Error Response (not owned):** `403 Forbidden`
```json
{ "message": "You do not own this finance record." }
```

---

#### `POST /api/v1/instructor/finances`

**Auth required:** Yes (Bearer token — instructor only)

Creates a finance record. Create first (JSON), then upload a receipt separately via `POST /finances/{finance}/receipt` if desired — this keeps the create call small and makes receipt upload independently retryable over flaky networks.

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | string | Yes | `payment` or `expense` |
| `category` | string | Yes | Slug from `/finances/config`. Must be a key of `expense_categories` (when `type=expense`) or `payment_categories` (when `type=payment`). Use `"none"` for uncategorised. |
| `payment_method` | string | No | Slug from `/finances/config.payment_methods`. Omit for unspecified. |
| `description` | string | Yes | Max 255 chars |
| `amount_pence` | integer | Yes | Min 1 |
| `is_recurring` | boolean | No | Default false. **Display-only flag.** |
| `recurrence_frequency` | string | Conditional | Required when `is_recurring=true`. One of `weekly`, `monthly`, `yearly`. |
| `date` | string | Yes | YYYY-MM-DD |
| `notes` | string | No | Max 1000 chars |

**Example Request:**
```json
{
  "type": "expense",
  "category": "insurance",
  "payment_method": "direct_debit",
  "description": "Car insurance",
  "amount_pence": 15000,
  "is_recurring": true,
  "recurrence_frequency": "monthly",
  "date": "2026-04-24",
  "notes": "Monthly DD"
}
```

**Success Response:** `201 Created` — returns the created record in the same shape as `GET /finances/{finance}`.

**Error Response (validation):** `422 Unprocessable Entity` — `errors` object keyed by field.

---

#### `PUT /api/v1/instructor/finances/{finance}`

**Auth required:** Yes (Bearer token — instructor only)

Updates a finance record. All fields optional. Category validation uses the **effective** type (either the new `type` in the payload or the record's current type).

**URL Parameters:** `finance` (integer)

**Request Body:** any subset of the `POST` fields.

**Success Response:** `200 OK` — returns the updated record.

**Error Response (not owned):** `403 Forbidden`

---

#### `DELETE /api/v1/instructor/finances/{finance}`

**Auth required:** Yes (Bearer token — instructor only)

Deletes a finance record. If a receipt is attached, it is deleted from S3 automatically.

**Success Response:** `200 OK`
```json
{ "message": "Finance record deleted successfully." }
```

**Error Response (not owned):** `403 Forbidden`

---

#### `POST /api/v1/instructor/finances/{finance}/receipt`

**Auth required:** Yes (Bearer token — instructor only)

Uploads (or replaces) a receipt for a finance record. Multipart request. If a receipt already exists on the record, it is deleted from S3 before the new one is stored.

**URL Parameters:** `finance` (integer)

**Multipart Field:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `receipt` | file | Yes | PDF, JPG/JPEG, or PNG. Max size from `/finances/config.receipt.max_size_kb` (default 10 MB). |

**Success Response:** `200 OK` — returns the updated record with the new `receipt` object populated.

**Error Responses:**
- `403 Forbidden` — record not owned.
- `422 Unprocessable Entity` — file missing, wrong mime, or over max size.

---

#### `DELETE /api/v1/instructor/finances/{finance}/receipt`

**Auth required:** Yes (Bearer token — instructor only)

Removes the receipt from a finance record. The record itself is unaffected; only the attachment is cleared. No-op if there's no receipt.

**Success Response:** `200 OK` — returns the updated record with `receipt: null`.

**Error Response (not owned):** `403 Forbidden`

---

### Instructor Mileage

Mileage logs are a separate ledger from finances. Fuel expenses are **not** linked to mileage logs — they're independent records. Business vs personal classification supports HMRC tax reporting.

**Mileage Log Object:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Mileage log ID |
| `date` | string | YYYY-MM-DD |
| `start_mileage` | integer | Starting odometer reading |
| `end_mileage` | integer | Ending odometer reading (≥ start) |
| `miles` | integer | Server-calculated `end - start` |
| `type` | string | `business` or `personal` |
| `type_label` | string | `"Business"` or `"Personal"` |
| `notes` | string\|null | Free-text notes (max 1000 chars) |
| `created_at` | string | ISO 8601 |
| `updated_at` | string | ISO 8601 |

---

#### `GET /api/v1/instructor/mileage`

**Auth required:** Yes (Bearer token — instructor only)

Cursor-paginated list of mileage logs, ordered by date descending.

**Query Params:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `from` | string | No | YYYY-MM-DD. Defaults to 30 days before `to`. |
| `to` | string | No | YYYY-MM-DD. Defaults to today. |
| `cursor` | string | No | Opaque cursor from a previous response. |
| `per_page` | integer | No | 1–100. Default 25. |

**Success Response:** `200 OK` — same cursor-paginated envelope as `/finances` (see above), with `data` as an array of mileage logs.

---

#### `GET /api/v1/instructor/mileage/{mileageLog}`

**Auth required:** Yes (Bearer token — instructor only)

Fetch a single mileage log by ID. 403 if not owned.

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 5,
    "date": "2026-04-22",
    "start_mileage": 45210,
    "end_mileage": 45250,
    "miles": 40,
    "type": "business",
    "type_label": "Business",
    "notes": "Lesson with Jane",
    "created_at": "2026-04-22T16:00:00+00:00",
    "updated_at": "2026-04-22T16:00:00+00:00"
  }
}
```

---

#### `POST /api/v1/instructor/mileage`

**Auth required:** Yes (Bearer token — instructor only)

Creates a mileage log. `miles` is calculated server-side from `end_mileage - start_mileage`.

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | string | Yes | YYYY-MM-DD |
| `start_mileage` | integer | Yes | Starting odometer reading (min 0) |
| `end_mileage` | integer | Yes | Ending odometer reading. Must be ≥ `start_mileage`. |
| `type` | string | Yes | `business` or `personal` |
| `notes` | string | No | Max 1000 chars |

**Example Request:**
```json
{
  "date": "2026-04-22",
  "start_mileage": 45210,
  "end_mileage": 45250,
  "type": "business",
  "notes": "Lesson with Jane"
}
```

**Success Response:** `201 Created` — returns the created mileage log.

**Error Response (validation):** `422 Unprocessable Entity` — includes `end_mileage` error when end < start.

---

#### `PUT /api/v1/instructor/mileage/{mileageLog}`

**Auth required:** Yes (Bearer token — instructor only)

Updates a mileage log. All fields optional. `miles` is recomputed whenever either `start_mileage` or `end_mileage` changes. The **effective** end must still be ≥ the effective start, so you can send only `end_mileage` and the server validates against the stored `start_mileage`.

**Success Response:** `200 OK` — returns the updated log.

**Error Responses:**
- `403 Forbidden` — not owned.
- `422 Unprocessable Entity` — includes `end_mileage` error if effective end < effective start.

---

#### `DELETE /api/v1/instructor/mileage/{mileageLog}`

**Auth required:** Yes (Bearer token — instructor only)

**Success Response:** `200 OK`
```json
{ "message": "Mileage log deleted successfully." }
```

**Error Response (not owned):** `403 Forbidden`

---

### Package Pricing

#### `GET /api/v1/packages/{package}/pricing`

**Auth required:** Yes (Bearer token — any role)

Returns the full pricing breakdown for a package, including booking fee, digital fee, promo discount, and calculated totals. **This is the single source of truth for all fee calculations** — the mobile app should never hardcode fee values.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `promo_code` | string | No | Promo code to apply (e.g., `SAVE10`, `SAVE20`) |

**Success Response:** `200 OK`
```json
{
  "data": {
    "package_price_pence": 35000,
    "package_price": 350.00,
    "booking_fee": 19.99,
    "digital_fee_per_lesson": 3.99,
    "digital_fee_total": 39.90,
    "lessons_count": 10,
    "promo_code": null,
    "promo_discount": 0,
    "subtotal": 409.89,
    "total": 409.89,
    "total_pence": 40989,
    "weekly_payment": 40.99
  }
}
```

**With promo code:** `GET /api/v1/packages/1/pricing?promo_code=SAVE10`
```json
{
  "data": {
    "package_price_pence": 35000,
    "package_price": 350.00,
    "booking_fee": 19.99,
    "digital_fee_per_lesson": 3.99,
    "digital_fee_total": 39.90,
    "lessons_count": 10,
    "promo_code": "SAVE10",
    "promo_discount": 35.00,
    "subtotal": 409.89,
    "total": 374.89,
    "total_pence": 37489,
    "weekly_payment": 37.49
  }
}
```

**Pricing Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `package_price_pence` | integer | Base package price in pence |
| `package_price` | float | Base package price in pounds |
| `booking_fee` | float | Flat booking fee (currently £19.99) |
| `digital_fee_per_lesson` | float | Digital fee per lesson (currently £3.99) |
| `digital_fee_total` | float | Total digital fee (`digital_fee_per_lesson × lessons_count`) |
| `lessons_count` | integer | Number of lessons in the package |
| `promo_code` | string\|null | Applied promo code (uppercase), or `null` if none |
| `promo_discount` | float | Discount amount from promo code (applied to package price only) |
| `subtotal` | float | `package_price + booking_fee + digital_fee_total` (before discounts) |
| `total` | float | `subtotal - promo_discount` |
| `total_pence` | integer | Total in pence (for Stripe) |
| `weekly_payment` | float | `total ÷ lessons_count` |

> **Important for mobile developers:** Always use this endpoint to display pricing — never hardcode fee values. The booking fee, digital fee, and promo codes are managed server-side and may change without an app update.

> **Promo codes:** Currently supported: `SAVE10` (10% off package price), `SAVE20` (20% off package price). Invalid codes are silently ignored (pricing returns without discount).

---

#### `GET /api/v1/instructor/calendar/items`

**Auth required:** Yes (Bearer token — instructor only)

Returns the authenticated instructor's calendar items for a specific date. By default, returns only available slots (excluding travel and practical test items). Set `available_only=false` to return all items for the day.

**Query Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | string | **Yes** | Date in `Y-m-d` format (e.g., `2026-03-24`) |
| `available_only` | boolean | No | `true` (default) = available slots only; `false` = all items for the day |

**Example:** `GET /api/v1/instructor/calendar/items?date=2026-03-24&available_only=false`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "date": "2026-03-24",
      "start_time": "09:00",
      "end_time": "10:00",
      "is_available": true,
      "status": "draft",
      "item_type": "slot",
      "travel_time_minutes": 15,
      "parent_item_id": null,
      "notes": "Morning lesson",
      "unavailability_reason": null,
      "recurrence_pattern": "weekly",
      "recurrence_end_date": "2026-06-24",
      "recurrence_group_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
    },
    {
      "id": 2,
      "date": "2026-03-24",
      "start_time": "10:00",
      "end_time": "10:15",
      "is_available": false,
      "status": null,
      "item_type": "travel",
      "travel_time_minutes": null,
      "parent_item_id": 1,
      "notes": null,
      "unavailability_reason": null,
      "recurrence_pattern": "none",
      "recurrence_end_date": null,
      "recurrence_group_id": null
    }
  ]
}
```

> **Note:** When `available_only=true` (default), travel and practical test items are excluded and only `is_available=true` items are returned. Use `available_only=false` to see the full day schedule.

---

#### `POST /api/v1/instructor/calendar/items`

**Auth required:** Yes (Bearer token — instructor only)

Creates a new calendar item (time slot) for the authenticated instructor. Supports all options available in the admin area: travel time, practical tests, recurrence, and unavailability.

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | string | **Yes** | Date in `Y-m-d` format. Cannot be in the past. |
| `start_time` | string | **Yes** | Start time in `H:i` format (e.g., `09:00`). Must be at or after `06:00`. |
| `end_time` | string | **Yes** | End time in `H:i` format. Must be after `start_time` and at or before `21:00`. |
| `is_available` | boolean | No | Whether the slot is available for booking. Default: `true`. |
| `notes` | string\|null | No | Optional notes (max 1000 characters) |
| `unavailability_reason` | string\|null | No | Optional reason for unavailability (max 500 chars). May be supplied when `is_available=false`; not required. |
| `recurrence_pattern` | string | No | One of: `none` (default), `weekly`, `biweekly`, `monthly` |
| `recurrence_end_date` | string\|null | No | End date for recurring series in `Y-m-d` format. Must be after `date`. If omitted, defaults to 6 months from `date`. |
| `travel_time_minutes` | integer\|null | No | Travel time block after the slot: `15`, `30`, or `45` minutes. Creates a separate travel item. |
| `is_practical_test` | boolean | No | If `true`, creates a practical test slot. Blocks 1 hour before `start_time` and 30 minutes after `end_time`. Automatically marked unavailable. |

**Example — Single slot with travel time:**
```json
{
  "date": "2026-03-25",
  "start_time": "09:00",
  "end_time": "10:00",
  "is_available": true,
  "notes": "Morning lesson",
  "travel_time_minutes": 15
}
```

**Response (201):**
```json
{
  "data": {
    "id": 42,
    "date": "2026-03-25",
    "start_time": "09:00",
    "end_time": "10:00",
    "is_available": true,
    "status": "draft",
    "item_type": "slot",
    "travel_time_minutes": 15,
    "parent_item_id": null,
    "notes": "Morning lesson",
    "unavailability_reason": null,
    "recurrence_pattern": "none",
    "recurrence_end_date": null,
    "recurrence_group_id": null
  },
  "has_travel_item": true
}
```

**Example — Recurring weekly slot:**
```json
{
  "date": "2026-03-25",
  "start_time": "14:00",
  "end_time": "15:00",
  "is_available": true,
  "recurrence_pattern": "weekly",
  "recurrence_end_date": "2026-06-25"
}
```

**Response (201):**
```json
{
  "data": {
    "id": 43,
    "date": "2026-03-25",
    "start_time": "14:00",
    "end_time": "15:00",
    "is_available": true,
    "status": "draft",
    "item_type": "slot",
    "travel_time_minutes": null,
    "parent_item_id": null,
    "notes": null,
    "unavailability_reason": null,
    "recurrence_pattern": "weekly",
    "recurrence_end_date": "2026-06-25",
    "recurrence_group_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
  },
  "recurring_count": 13
}
```

**Example — Practical test:**
```json
{
  "date": "2026-04-10",
  "start_time": "11:00",
  "end_time": "12:00",
  "is_practical_test": true
}
```

**Example — Unavailable slot:**
```json
{
  "date": "2026-03-26",
  "start_time": "12:00",
  "end_time": "13:00",
  "is_available": false,
  "unavailability_reason": "Lunch break"
}
```

**Validation errors (422):**
- Overlapping time slots (including travel time and practical test buffers) are rejected.
- `unavailability_reason` is optional and may be omitted when `is_available=false`.
- `start_time` before `06:00` or `end_time` after `21:00` is rejected (allowed diary window is `06:00`–`21:00`, governed by `config/diary.php`).

---

#### `DELETE /api/v1/instructor/calendar/items/{calendarItem}`

**Auth required:** Yes (Bearer token — instructor only)

Deletes a calendar item belonging to the authenticated instructor. For recurring items, supports deleting a single occurrence or all future occurrences.

**Path Parameters:**

| Field | Type | Description |
|-------|------|-------------|
| `calendarItem` | integer | Calendar item ID |

**Query Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `scope` | string | No | `single` (default) = delete this item only; `future` = delete this item and all future items in the recurrence group |

**Example:** `DELETE /api/v1/instructor/calendar/items/42?scope=future`

**Response — Single delete (200):**
```json
{
  "message": "Calendar item removed successfully."
}
```

**Response — Recurring future delete (200):**
```json
{
  "message": "5 recurring calendar item(s) removed successfully.",
  "deleted_count": 5
}
```

**Error — Item has booked lessons (400):**
```json
{
  "message": "Cannot delete a calendar item that has booked lessons."
}
```

**Error — Not found / not owned (404):**
```json
{
  "message": "Calendar item not found."
}
```

> **Note:** Items with booked lessons cannot be deleted. When using `scope=future`, only items without booked lessons are removed — items with bookings are preserved.

---

### Student Booking (attached instructor)

These endpoints expose the authenticated student's **attached instructor's** packages and available calendar slots, so the student mobile app can render its booking sheet without needing access to instructor-scoped endpoints. The student must be attached to an instructor (via `POST /students/attach`) before calling these routes.

---

#### `GET /api/v1/student/packages`

**Auth required:** Yes (Bearer token — student only)

Returns the active packages belonging to the authenticated student's attached instructor. Response shape matches `GET /api/v1/instructor/packages`.

**Example:** `GET /api/v1/student/packages`

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "name": "10 Hour Package",
      "lessons_count": 10,
      "price_pence": 35000,
      "lesson_price_pence": 3500,
      "payment_mode": "upfront",
      "active": true
    }
  ]
}
```

**Error Response — no attached instructor (422):**
```json
{
  "message": "You must be attached to an instructor before you can view packages."
}
```

**Error Response — student profile missing (404):**
```json
{
  "message": "Student profile not found for the authenticated user."
}
```

> Use `GET /api/v1/packages/{package}/pricing` to get the full fee breakdown for a selected package before showing the confirmation screen.

---

#### `GET /api/v1/student/calendar/items`

**Auth required:** Yes (Bearer token — student only)

Returns available calendar slots for the authenticated student's attached instructor on a given date. Unlike the instructor endpoint, this route always filters to **available slots only** and **excludes drafts** — students cannot see travel items, practical test items, unavailable slots, or draft items.

**Query Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `date` | string | **Yes** | Date in `Y-m-d` format (e.g., `2026-04-10`) |

**Example:** `GET /api/v1/student/calendar/items?date=2026-04-10`

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 42,
      "date": "2026-04-10",
      "start_time": "09:00",
      "end_time": "10:00",
      "is_available": true,
      "status": "published",
      "item_type": "slot",
      "travel_time_minutes": null,
      "parent_item_id": null,
      "notes": null,
      "unavailability_reason": null,
      "recurrence_pattern": "none",
      "recurrence_end_date": null,
      "recurrence_group_id": null
    }
  ]
}
```

**Error Response — no attached instructor (422):**
```json
{
  "message": "You must be attached to an instructor before you can view available slots."
}
```

**Error Response — validation (422):**
```json
{
  "message": "The date field is required.",
  "errors": {
    "date": ["The date field is required."]
  }
}
```

---

#### `GET /api/v1/student/instructor`

**Auth required:** Yes (Bearer token — student only)

Returns the public profile of the authenticated student's attached instructor. The student must be attached to an instructor (via PIN).

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "name": "Michael Roberts",
    "bio": "ADI qualified instructor with 15 years experience...",
    "profile_picture_url": "https://s3.example.com/instructor-pictures/abc.jpg"
  }
}
```

**Error Response — no attached instructor (422):**
```json
{
  "message": "You must be attached to an instructor to view their profile."
}
```

---

#### `GET /api/v1/student/dashboard`

**Auth required:** Yes (Bearer token — student only)

Returns aggregated dashboard data for the student home screen. Includes practice hours and suggested learning resources from lesson sign-offs.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "practice_hours": {
      "completed": 24.5,
      "total": 40.0
    },
    "suggested_resources": [
      {
        "id": 2,
        "title": "Parallel Parking Tutorial",
        "resource_type": "video_link",
        "thumbnail_url": "https://img.youtube.com/vi/abc123/mqdefault.jpg",
        "folder_name": "Manoeuvres",
        "is_watched": false,
        "suggested_at": "2026-04-10T10:30:00.000000Z"
      },
      {
        "id": 10,
        "title": "Highway Code Summary",
        "resource_type": "file",
        "thumbnail_url": null,
        "folder_name": "Theory",
        "is_watched": true,
        "suggested_at": "2026-04-08T14:00:00.000000Z"
      }
    ]
  }
}
```

**Practice Hours Calculation:**
- `completed` — sum of duration (hours) of all lessons with status `completed`
- `total` — sum of duration (hours) of ALL non-draft lessons across all orders (completed + pending + cancelled)

Duration is derived from each lesson's `start_time` and `end_time`.

**Suggested Resources:**
- Derived from the `lesson_resource` pivot — resources attached to the student's lessons via sign-offs
- Each resource includes an `is_watched` boolean based on the `resource_watches` table
- Ordered by most recently suggested first
- Returns all suggested resources (not paginated)

---

### Student Resources

---

#### `GET /api/v1/student/resource-summary`

**Auth:** Bearer token (student only)

Returns an aggregated summary of the authenticated student's resource activity for the Resources tab dashboard. One call powers the entire screen.

**Success Response:** `200 OK`

```json
{
  "data": {
    "recent_activity": [
      {
        "id": 2,
        "title": "Parallel Parking Tutorial",
        "type": "video",
        "watched_at": "2026-04-14T14:30:00.000000Z"
      },
      {
        "id": 10,
        "title": "Highway Code Summary",
        "type": "file",
        "watched_at": "2026-04-13T09:15:00.000000Z"
      }
    ],
    "stats": {
      "total_videos": 30,
      "total_files": 15,
      "videos_watched": 24,
      "files_opened": 8,
      "mock_tests_taken": 12,
      "mock_test_average": "41/50",
      "mock_test_percentage": 82,
      "hazard_attempts_taken": 15,
      "hazard_perception_average": "3.8/5",
      "hazard_perception_percentage": 76
    },
    "study_progress": [
      {
        "folder_name": "Manoeuvres",
        "total": 12,
        "watched": 9,
        "percentage": 75
      },
      {
        "folder_name": "Road Safety",
        "total": 8,
        "watched": 6,
        "percentage": 75
      }
    ],
    "recommended": [
      {
        "id": 5,
        "title": "Roundabout Navigation",
        "resource_type": "video_link",
        "thumbnail_url": null,
        "folder_name": "Road Safety"
      }
    ],
    "badges": {
      "first_test": {
        "earned": true,
        "earned_at": "2026-04-10T09:30:00+00:00"
      },
      "top_score": {
        "earned": false,
        "earned_at": null
      },
      "seven_day_streak": {
        "earned": false,
        "earned_at": null,
        "current_streak_days": 4
      },
      "expert": {
        "earned": false,
        "earned_at": null,
        "criteria": {
          "perfect_mock": false,
          "perfect_hazard": false,
          "all_resources_watched": false
        }
      }
    },
    "study_tip": "Practice hazard perception tests regularly. The more scenarios you see, the better you'll recognise potential dangers on the road."
  }
}
```

**Field Breakdown:**

| Section | Description |
|---------|-------------|
| `recent_activity` | Last 10 resource interactions (newest first). `type` is `video` or `file` derived from `resource_type`. |
| `stats.total_videos` | Count of published resources where `resource_type = 'video_link'`. |
| `stats.total_files` | Count of published resources where `resource_type = 'file'`. |
| `stats.videos_watched` | Count of `video_link` resources this student has watched. |
| `stats.files_opened` | Count of `file` resources this student has watched. |
| `stats.mock_tests_taken` | Number of mock tests the student has completed (`completed_at IS NOT NULL`). |
| `stats.mock_test_average` | Average correct/total across completed mock tests, formatted as `"{correct}/{total}"`. Returns `"0/50"` if none taken. |
| `stats.mock_test_percentage` | Average score percentage across completed mock tests. `0` if none taken. |
| `stats.hazard_attempts_taken` | Number of hazard perception attempts the student has made. |
| `stats.hazard_perception_average` | Average score normalised to a /5 scale — double-hazard attempts (max 10) are halved before averaging, so the denominator is always `/5`. Format `"{avg}/5"` to 1 decimal. Returns `"0/5"` if none. |
| `stats.hazard_perception_percentage` | Normalised hazard average as a percentage of the max (`/5`). `0` if none taken. |
| `study_progress` | Per top-level folder: total resources (including children), watched count, percentage. Only folders with ≥1 resource. |
| `recommended` | Resources suggested via lesson sign-offs (`lesson_resource` pivot). Unwatched first, then watched. Limit 5. |
| `badges.first_test` | Earned when the student has ≥1 completed mock test. `earned_at` = `completed_at` of the earliest completed test. |
| `badges.top_score` | Earned when the student has any completed mock test where `correct_answers = total_questions`. `earned_at` = earliest such test's `completed_at`. |
| `badges.seven_day_streak` | Earned when the student has taken ≥1 completed mock test on each of 7 consecutive calendar days (hazard perception attempts do **not** count). `earned_at` = the 7th day of the first qualifying run. `current_streak_days` counts the ongoing run ending today or yesterday; any missed day resets it to `0`. Once earned, stays earned — a broken streak does not revoke the badge. |
| `badges.expert` | Earned when **all three** `criteria` are true: `perfect_mock` (any 50/50 mock test), `perfect_hazard` (any hazard attempt where `total_score` = max for that clip — 5 single, 10 double), `all_resources_watched` (a `resource_watches` row exists for every published resource). `earned_at` = latest of the three timestamps. |
| `study_tip` | Random driving study tip from a pool of 20. |

**Error Responses:**

| Status | When |
|--------|------|
| 401 | Missing or invalid Bearer token |
| 403 | Authenticated user is not a student |

---

#### `GET /api/v1/student/resources`

**Auth required:** Yes (Bearer token — student only)

Returns the full resource library for the student. The response contains two top-level keys:

- **`folders`** — the complete folder tree with all published resources nested inside. Each resource includes `is_suggested` (assigned to the student via a lesson sign-off) and `is_watched` booleans.
- **`my_resources`** — a flat array of resources specifically suggested to this student (via `lesson_resource` pivot), for the "My Resources" tab.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "folders": [
      {
        "id": 1,
        "name": "Learn to Drive",
        "slug": "learn-to-drive",
        "children": [
          {
            "id": 3,
            "name": "Moving Off & Stopping",
            "slug": "moving-off-stopping",
            "resources": [
              {
                "id": 2,
                "title": "Moving Off & Stopping - Introduction",
                "description": "In this video, we introduce you to the topic of moving off & stopping.",
                "resource_type": "video_link",
                "thumbnail_url": null,
                "tags": ["moving off and stopping", "moving off", "stopping"],
                "is_suggested": true,
                "is_watched": false
              }
            ]
          }
        ],
        "resources": []
      }
    ],
    "my_resources": [
      {
        "id": 2,
        "title": "Moving Off & Stopping - Introduction",
        "resource_type": "video_link",
        "thumbnail_url": null,
        "folder_name": "Moving Off & Stopping",
        "is_watched": false,
        "suggested_at": "2026-03-22T18:30:00.000000Z"
      }
    ]
  }
}
```

**Folder Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Folder ID |
| `name` | string | Folder display name (e.g. "Manoeuvres") |
| `slug` | string | URL-safe slug |
| `children` | array | Nested child folders (same structure, recursive) |
| `resources` | array | Published resources in this folder |

**Resource Object Fields (within folders):**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Resource title |
| `description` | string\|null | Resource description |
| `resource_type` | string | `video_link` or `file` |
| `thumbnail_url` | string\|null | Thumbnail image URL |
| `tags` | array\|null | Tag strings for search/filtering |
| `is_suggested` | boolean | Whether this resource was assigned to the student via a lesson |
| `is_watched` | boolean | Whether the student has marked this resource as watched |

**My Resources Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Resource title |
| `resource_type` | string | `video_link` or `file` |
| `thumbnail_url` | string\|null | Thumbnail image URL |
| `folder_name` | string\|null | Name of the parent folder |
| `is_watched` | boolean | Whether the student has watched this resource |
| `suggested_at` | string | ISO 8601 timestamp of when the resource was assigned |

> **Note:** `video_url` and `file_url` are intentionally excluded from this endpoint to keep the payload lightweight. Use `GET /student/resources/{resource}` to retrieve the actual content URL when the user taps a resource.

---

#### `GET /api/v1/student/resources/{resource}`

**Auth required:** Yes (Bearer token — student only)

Returns a single resource with its full details including the actual content URL. For `video_link` resources, this is the YouTube/Vimeo URL. For `file` resources, this is a time-limited signed S3 URL (valid for 30 minutes).

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `resource` | integer | Resource ID |

**Request Body:** None

**Success Response:** `200 OK`

**Video link example:**
```json
{
  "data": {
    "id": 2,
    "title": "Moving Off & Stopping - Introduction",
    "description": "In this video, we introduce you to the topic of moving off & stopping.",
    "resource_type": "video_link",
    "video_url": "https://youtu.be/a3BEcrNZOkE?si=qpoSxbKfpm75SFnk",
    "file_url": null,
    "thumbnail_url": null,
    "file_name": null,
    "tags": ["moving off and stopping", "moving off", "stopping"],
    "is_watched": true
  }
}
```

**File example:**
```json
{
  "data": {
    "id": 10,
    "title": "Highway Code Summary",
    "description": "A quick-reference PDF of the Highway Code.",
    "resource_type": "file",
    "video_url": null,
    "file_url": "https://drivecrm.s3.eu-west-2.amazonaws.com/resources/highway-code.pdf?X-Amz-Expires=1800&...",
    "thumbnail_url": null,
    "file_name": "highway-code.pdf",
    "tags": ["highway code", "theory"],
    "is_watched": false
  }
}
```

**Resource Detail Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Resource title |
| `description` | string\|null | Full description |
| `resource_type` | string | `video_link` or `file` |
| `video_url` | string\|null | YouTube/Vimeo URL (video_link resources only) |
| `file_url` | string\|null | Signed S3 URL, valid 30 minutes (file resources only) |
| `thumbnail_url` | string\|null | Thumbnail image URL |
| `file_name` | string\|null | Original file name (file resources only) |
| `tags` | array\|null | Tag strings |
| `is_watched` | boolean | Whether the student has watched this resource |

**Error Response — resource not found:** `404 Not Found`
```json
{
  "message": "No query results for model [App\\Models\\Resource] 999."
}
```

---

#### `POST /api/v1/student/resources/{resource}/watched`

**Auth required:** Yes (Bearer token — student only)

Marks a resource as watched by the authenticated student. This endpoint is **idempotent** — calling it multiple times for the same resource will not create duplicate records or return an error.

Call this endpoint when the student finishes watching a video or opens a PDF document.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `resource` | integer | Resource ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "message": "Resource marked as watched."
}
```

**Error Response — resource not found:** `404 Not Found`
```json
{
  "message": "No query results for model [App\\Models\\Resource] 999."
}
```

---

### Students

---

#### `POST /api/v1/students/attach`

**Auth required:** Yes (Bearer token — student only)

Attaches the authenticated student to an instructor using the instructor's PIN code. The student must not already be attached to an instructor.

**Request Body:**
```json
{
  "pin": "ABC123"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `pin` | string | Yes | Instructor's unique PIN code (max 10 characters) |

**Success Response (200):**
```json
{
  "message": "You're all set! Your instructor can't wait to get you on the road.",
  "instructor": {
    "id": 12,
    "name": "Jane Smith",
    "avatar": "https://s3.eu-west-2.amazonaws.com/drivecrm/instructors/12/avatar.jpg"
  }
}
```

| Field | Type | Notes |
|-------|------|-------|
| `message` | string | Randomly selected thank-you message (one of 5) — display this to the user after successful attachment |
| `instructor.id` | integer | Instructor's ID |
| `instructor.name` | string | Instructor's full name |
| `instructor.avatar` | string\|null | Public URL of instructor's profile picture, or `null` if not set |

**Error Response — already attached (422):**
```json
{
  "message": "You are already attached to an instructor."
}
```

**Error Response — invalid PIN (422):**
```json
{
  "message": "The PIN you entered does not match any instructor."
}
```

**Error Response — validation (422):**
```json
{
  "message": "The pin field is required.",
  "errors": {
    "pin": ["The pin field is required."]
  }
}
```

---

#### `POST /api/v1/students`

**Auth required:** Yes (Bearer token — instructor only)

Creates a new student with a user account and assigns them to the authenticated instructor. A welcome email is sent to the student with their temporary login credentials.

**Request Body:**
```json
{
  "first_name": "Jane",
  "surname": "Doe",
  "email": "jane@example.com",
  "phone": "07700900000",
  "owns_account": true
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `first_name` | string | Yes | Student's first name (max 255) |
| `surname` | string | Yes | Student's surname (max 255) |
| `email` | string | Yes | Student's email address (must be unique across all users) |
| `phone` | string | No | Student's phone number (max 50) |
| `owns_account` | boolean | No | Whether the student owns the account (default: true) |

**Success Response:** `201 Created`
```json
{
  "data": {
    "id": 3,
    "first_name": "Jane",
    "surname": "Doe",
    "email": "jane@example.com",
    "phone": "07700900000",
    "status": "active",
    "has_app": true,
    "updated_at": "2026-03-23T10:00:00+00:00"
  }
}
```

**Error Response (not instructor):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The first name field is required.",
  "errors": {
    "first_name": ["The first name field is required."],
    "surname": ["The surname field is required."]
  }
}
```

**Error Response (duplicate email):** `422 Unprocessable Entity`
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

> **Note:** The student is automatically assigned to the authenticated instructor. A user account is created with the `student` role and a randomly generated temporary password. A welcome email is sent to the student with their login credentials. The student should change their password after first login.

**Side Effects:**
- Creates a `User` record with `role = "student"` and a random temporary password
- Creates a `Student` record linked to the new user and assigned to the instructor
- Sends a `WelcomeStudentNotification` email with the temporary password (queued)

---

#### `GET /api/v1/students/{student}`

**Auth required:** Yes (Bearer token — student or instructor)

Returns a single student record. Access is controlled by a policy:
- **Students** can only view their own record (user_id must match the authenticated user).
- **Instructors** can only view students assigned to them (instructor_id must match the authenticated instructor).

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "first_name": "Jane",
    "surname": "Doe",
    "email": "jane@example.com",
    "phone": "07700900000",
    "status": "active",
    "has_app": true,
    "updated_at": "2026-03-17T10:30:00+00:00"
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (not found):** `404 Not Found`
```json
{
  "message": "No query results for model [App\\Models\\Student] 999."
}
```

---

#### `PUT /api/v1/students/{student}`

**Auth required:** Yes (Bearer token — student or instructor)

Updates an existing student record. Access is controlled by the same policy as the view endpoint.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body (all fields optional):**
```json
{
  "first_name": "Janet",
  "surname": "Smith",
  "email": "janet@example.com",
  "phone": "07700900099"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `first_name` | string | No | Student's first name (max 255) |
| `surname` | string | No | Student's surname (max 255) |
| `email` | string\|null | No | Student's email address |
| `phone` | string\|null | No | Student's phone number (max 50) |
| `contact_first_name` | string\|null | No | Booker's first name (max 255) |
| `contact_surname` | string\|null | No | Booker's surname (max 255) |
| `contact_email` | string\|null | No | Booker's email |
| `contact_phone` | string\|null | No | Booker's phone (max 50) |
| `owns_account` | boolean\|null | No | Whether the student owns the account |
| `status` | string\|null | No | One of: `active`, `inactive`, `on_hold`, `passed`, `failed`, `completed` |

> Only send the fields you want to update. Omitted fields remain unchanged.

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "first_name": "Janet",
    "surname": "Smith",
    "email": "janet@example.com",
    "phone": "07700900099",
    "status": "active",
    "has_app": true,
    "updated_at": "2026-03-19T10:05:00+00:00"
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
**Error Response (not found):** `404 Not Found`

---

#### `DELETE /api/v1/students/{student}`

**Auth required:** Yes (Bearer token — student or instructor)

Removes a student from their assigned instructor. This is a **soft remove** — the student record and user account are preserved, but the `instructor_id` is set to `null`. Access is controlled by the same policy.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 1,
    "first_name": "Jane",
    "surname": "Doe",
    "email": "jane@example.com",
    "phone": "07700900000",
    "status": "active",
    "has_app": true,
    "updated_at": "2026-03-23T11:00:00+00:00"
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
**Error Response (not found):** `404 Not Found`

> **Note:** This does NOT permanently delete the student. It removes the student from the instructor's list by setting `instructor_id` to null. The student's user account, lessons, orders, and other data are preserved. An activity log entry is created recording the removal.

---

#### `GET /api/v1/students/{student}/lessons`

**Auth required:** Yes (Bearer token — student or instructor)

Returns lessons for a given student across all their orders. Supports optional filtering, sorting, and limiting via query parameters.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Query Parameters (all optional):**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | Filter by lesson status: `pending`, `completed`, or `cancelled` |
| `from_date` | string | — | Only return lessons on or after this date (YYYY-MM-DD) |
| `sort` | string | `desc` | Sort direction by date/time: `asc` or `desc` |
| `limit` | integer | — | Maximum number of results to return |

**Request Body:** None

**Example:** `GET /api/v1/students/5/lessons?status=pending&from_date=2026-04-10&sort=asc&limit=3`

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "student_lesson_number": 11,
      "order_id": 1,
      "instructor_name": "John Smith",
      "instructor_avatar": "https://s3.example.com/instructor-pictures/abc.jpg",
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
    },
    {
      "id": 2,
      "student_lesson_number": 10,
      "order_id": 1,
      "instructor_name": "John Smith",
      "instructor_avatar": "https://s3.example.com/instructor-pictures/abc.jpg",
      "package_name": "10 Hour Package",
      "date": "2026-03-18",
      "start_time": "14:00",
      "end_time": "15:00",
      "status": "completed",
      "completed_at": "2026-03-18T15:05:00.000000Z",
      "card_status": "signed_off",
      "has_reflective_log": true,
      "resources_count": 2,
      "payment_status": "paid"
    }
  ]
}
```

**Lesson List Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Lesson record ID (internal — used for routing) |
| `student_lesson_number` | integer | Per-student running lesson number (1, 2, 3, …). Increments across all of the student's orders and is the user-facing reference shown in the UI |
| `order_id` | integer | The order this lesson belongs to |
| `instructor_name` | string\|null | Instructor's full name |
| `instructor_avatar` | string\|null | Instructor's profile picture URL (S3) |
| `package_name` | string\|null | Name of the package the lesson is part of |
| `date` | string\|null | Lesson date (YYYY-MM-DD) |
| `start_time` | string\|null | Start time (HH:MM) |
| `end_time` | string\|null | End time (HH:MM) |
| `status` | string | Lesson status: `pending`, `completed`, or `cancelled` |
| `completed_at` | string\|null | ISO 8601 timestamp when lesson was completed |
| `card_status` | string | Computed UI card status (see Card Status Logic below) |
| `has_reflective_log` | boolean | Whether a reflective log exists for this lesson |
| `resources_count` | integer | Number of resources attached to this lesson |
| `payment_status` | string\|null | Payment status: `paid`, `due`, `refunded`, or null |

**Card Status Logic:**

| Value | Color | Condition |
|-------|-------|-----------|
| `signed_off` | Green | Past lesson that has been completed/signed off |
| `needs_sign_off` | Red | Past lesson NOT signed off (reflective log missing) |
| `current` | Orange | The next lesson (today or future) — the one to sign off next |
| `upcoming` | Blue | Future lessons beyond the next one |

---

#### `GET /api/v1/students/{student}/lessons/{lesson}`

**Auth required:** Yes (Bearer token — student or instructor)

Returns full detail for a single lesson belonging to a student. The lesson must belong to the student via one of their orders — otherwise a 404 is returned.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `lesson` | integer | The lesson record ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 2,
    "student_lesson_number": 10,
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

**Lesson Detail Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Lesson record ID (internal — used for routing) |
| `student_lesson_number` | integer | Per-student running lesson number (1, 2, 3, …). Increments across all of the student's orders and is the user-facing reference shown in the UI |
| `order_id` | integer | The order this lesson belongs to |
| `instructor_id` | integer | The instructor's record ID |
| `instructor_name` | string\|null | Instructor's full name |
| `package_name` | string\|null | Name of the package the lesson is part of |
| `amount_pence` | integer\|null | Lesson cost in pence (e.g., 3500 = £35.00) |
| `date` | string\|null | Lesson date (YYYY-MM-DD) |
| `start_time` | string\|null | Start time (HH:MM) |
| `end_time` | string\|null | End time (HH:MM) |
| `status` | string | Lesson status: `pending`, `completed`, or `cancelled` |
| `completed_at` | string\|null | ISO 8601 timestamp when lesson was completed |
| `summary` | string\|null | Instructor's lesson summary/notes |
| `payment_status` | string\|null | Payment status: `paid`, `due`, `refunded`, or null |
| `payment_mode` | string\|null | Package payment mode: `upfront` or `weekly` |
| `payout_status` | string\|null | Instructor payout status: `pending`, `paid`, `failed`, or null |
| `has_payout` | boolean | Whether a payout has been created for this lesson |
| `calendar_date` | string\|null | Calendar date for the lesson slot (YYYY-MM-DD) |
| `card_status` | string | Computed UI card status: `signed_off`, `needs_sign_off`, `current`, `upcoming` |
| `has_reflective_log` | boolean | Whether a reflective log exists for this lesson |
| `reflective_log` | object\|null | The reflective log data (see below) |
| `resources` | array | List of resources attached to this lesson (see below) |

**Reflective Log Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Reflective log ID |
| `what_i_learned` | string\|null | What the student learned |
| `what_went_well` | string\|null | What went well during the lesson |
| `what_to_improve` | string\|null | Areas to improve |
| `additional_notes` | string\|null | Any additional notes |
| `created_at` | string\|null | ISO 8601 timestamp when the log was created |

**Lesson Resource Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Resource title |
| `description` | string\|null | Resource description |
| `resource_type` | string | Type: `video_link` or `file` |
| `video_url` | string\|null | Video URL (for video_link type) |
| `file_path` | string\|null | File storage path |
| `file_name` | string\|null | Original file name |
| `file_size` | integer\|null | File size in bytes |
| `mime_type` | string\|null | MIME type of the file |
| `thumbnail_url` | string\|null | Thumbnail URL if available |

> **Note:** If the lesson exists but belongs to a different student, a 404 is returned (not 403), preventing information leakage.

---

#### `POST /api/v1/students/{student}/lessons/{lesson}/sign-off`

**Auth required:** Yes (Bearer token — student or instructor)

Sign off a lesson as completed. This is an asynchronous operation — a background job handles completion, calendar updates, Stripe payouts, activity logs, feedback emails, and AI resource recommendations.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `lesson` | integer | The lesson record ID |

**Request Body:**
```json
{
  "summary": "Good progress today. Practiced roundabouts and dual carriageway driving."
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `summary` | string | Yes | Lesson summary/completion notes (max 5000 characters) |

**Success Response:** `200 OK`
```json
{
  "message": "Lesson sign-off is being processed."
}
```

**Error Response (not authorised):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The summary field is required.",
  "errors": {
    "summary": [
      "The summary field is required."
    ]
  }
}
```

> **Important:** The lesson must have `status = "pending"` and belong to the specified student. The response is immediate (200), but the actual sign-off processing happens asynchronously in a background job. The lesson status will change to `completed` once the job finishes. Poll the lesson detail endpoint to check for completion.

**Side Effects (background job):**
- Marks the lesson as `completed` with `completed_at` timestamp
- Updates associated calendar items
- Triggers Stripe payout processing (if applicable)
- Creates activity log entries
- Sends feedback email to the student
- For weekly orders: immediately issues the next lesson's Stripe invoice + payment-link email — and queues a push notification on the student's user when a registered Expo push token exists
- Generates AI resource recommendations

---

#### `POST /api/v1/students/{student}/lessons/{lesson}/resources`

**Auth required:** Yes (Bearer token — instructor only)

Assign learning resources to a lesson. Sends an email notification to the student with the assigned resources.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `lesson` | integer | The lesson record ID |

**Request Body:**
```json
{
  "resource_ids": [1, 5, 12]
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `resource_ids` | array | Yes | Array of resource IDs (min 1 item) |
| `resource_ids.*` | integer | Yes | Each must be a valid resource ID (exists in `resources` table) |

**Success Response:** `200 OK`
```json
{
  "message": "Resources assigned successfully.",
  "data": [
    {
      "id": 1,
      "title": "Highway Code - Roundabouts",
      "description": "Official Highway Code section on roundabouts",
      "resource_type": "file",
      "video_url": null,
      "file_path": "resources/highway-code-roundabouts.pdf",
      "file_name": "highway-code-roundabouts.pdf",
      "file_size": 102400,
      "mime_type": "application/pdf",
      "thumbnail_url": null
    },
    {
      "id": 5,
      "title": "Parallel Parking Guide",
      "description": "Step-by-step guide to parallel parking",
      "resource_type": "video_link",
      "video_url": "https://www.youtube.com/watch?v=example",
      "file_path": null,
      "file_name": null,
      "file_size": null,
      "mime_type": null,
      "thumbnail_url": "https://img.youtube.com/vi/example/hqdefault.jpg"
    }
  ]
}
```

**Error Response (not instructor):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The resource ids field is required.",
  "errors": {
    "resource_ids": [
      "The resource ids field is required."
    ]
  }
}
```

> **Note:** The lesson must belong to the specified student. The instructor must be the student's assigned instructor.

---

#### `GET /api/v1/students/{student}/notes`

**Auth required:** Yes (Bearer token — student or instructor)

Returns all notes for a given student, ordered by most recent first. Access is controlled by a policy:
- **Students** can only view their own notes.
- **Instructors** can only view notes for students assigned to them.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 2,
      "note": "Needs more practice with parallel parking.",
      "created_at": "2026-03-18T14:30:00+00:00",
      "updated_at": "2026-03-18T14:30:00+00:00"
    },
    {
      "id": 1,
      "note": "Good progress on roundabouts today.",
      "created_at": "2026-03-17T10:00:00+00:00",
      "updated_at": "2026-03-17T10:00:00+00:00"
    }
  ]
}
```

**Note Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Note record ID |
| `note` | string | The note content |
| `created_at` | string | ISO 8601 timestamp when the note was created |
| `updated_at` | string | ISO 8601 timestamp when the note was last updated |

**Error Response (not authorised):** `403 Forbidden`
**Error Response (not found):** `404 Not Found`

---

#### `POST /api/v1/students/{student}/notes`

**Auth required:** Yes (Bearer token — student or instructor)

Creates a new note on a student record. Access is controlled by the same policy as the notes list.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:**
```json
{
  "note": "Great lesson today - nailed the bay parking."
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `note` | string | Yes | Note content (max 5000 characters) |

**Success Response:** `201 Created`
```json
{
  "data": {
    "id": 3,
    "note": "Great lesson today - nailed the bay parking.",
    "created_at": "2026-03-18T15:00:00+00:00",
    "updated_at": "2026-03-18T15:00:00+00:00"
  }
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The note field is required.",
  "errors": {
    "note": [
      "The note field is required."
    ]
  }
}
```

**Error Response (not authorised):** `403 Forbidden`

---

#### `PUT /api/v1/students/{student}/notes/{note}`

**Auth required:** Yes (Bearer token — student or instructor)

Updates an existing note on a student record. The note must belong to the specified student. Access is controlled by the same policy as the notes list:
- **Students** can only update notes on their own record.
- **Instructors** can only update notes on students assigned to them.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `note` | integer | The note record ID |

**Request Body:**
```json
{
  "note": "Updated note content with corrections."
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `note` | string | Yes | Note content (max 5000 characters) |

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 3,
    "note": "Updated note content with corrections.",
    "created_at": "2026-03-18T15:00:00+00:00",
    "updated_at": "2026-03-24T09:30:00+00:00"
  }
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The note field is required.",
  "errors": {
    "note": [
      "The note field is required."
    ]
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
**Error Response (note not found on student):** `404 Not Found`

---

#### `DELETE /api/v1/students/{student}/notes/{note}`

**Auth required:** Yes (Bearer token — student or instructor)

Soft deletes an existing note on a student record. The note must belong to the specified student. Access is controlled by the same policy as the notes list:
- **Students** can only delete notes on their own record.
- **Instructors** can only delete notes on students assigned to them.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `note` | integer | The note record ID |

**Request Body:** None

**Success Response:** `204 No Content`

**Error Response (not authorised):** `403 Forbidden`
**Error Response (note not found on student):** `404 Not Found`

---

#### `GET /api/v1/students/{student}/checklist-items`

**Auth required:** Yes (Bearer token — student or instructor)

Returns all checklist items for a given student. If the student has no checklist items yet, default items are automatically seeded on first access. Access is controlled by a policy:
- **Students** can only view their own checklist items.
- **Instructors** can only view checklist items for students assigned to them.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "key": "book_theory_test",
      "label": "Book theory test",
      "category": "Theory Test",
      "is_checked": false,
      "date": null,
      "notes": null,
      "sort_order": 1
    },
    {
      "id": 2,
      "key": "sit_theory_test",
      "label": "Sit theory test",
      "category": "Theory Test",
      "is_checked": true,
      "date": "2026-03-10",
      "notes": "Passed first time",
      "sort_order": 2
    }
  ]
}
```

**Checklist Item Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Checklist item record ID |
| `key` | string | Unique key identifier (e.g., `book_theory_test`) |
| `label` | string | Human-readable label |
| `category` | string | Category grouping (e.g., `Theory Test`, `Practical Test`, `General`) |
| `is_checked` | boolean | Whether the item is checked/completed |
| `date` | string\|null | Associated date (YYYY-MM-DD), e.g., when the item was completed |
| `notes` | string\|null | Additional notes |
| `sort_order` | integer | Display order |

> **Note:** Items are returned ordered by `sort_order` ascending. On first access for a student with no checklist items, default items are automatically seeded.

**Error Response (not authorised):** `403 Forbidden`
**Error Response (not found):** `404 Not Found`

---

#### `PUT /api/v1/students/{student}/checklist-items/{checklistItem}`

**Auth required:** Yes (Bearer token — student or instructor)

Updates a single checklist item for a student. The checklist item must belong to the specified student — otherwise a 404 is returned.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `checklistItem` | integer | The checklist item record ID |

**Request Body (all fields optional):**
```json
{
  "is_checked": true,
  "date": "2026-03-18",
  "notes": "Passed first time"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `is_checked` | boolean | No | Whether the item is checked/completed |
| `date` | string\|null | No | Associated date (YYYY-MM-DD format) |
| `notes` | string\|null | No | Additional notes (max 1000 characters) |

> All fields are optional — you can send a partial update with only the fields you want to change.

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 2,
    "key": "sit_theory_test",
    "label": "Sit theory test",
    "category": "Theory Test",
    "is_checked": true,
    "date": "2026-03-18",
    "notes": "Passed first time",
    "sort_order": 2
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (checklist item not found or not owned by student):** `404 Not Found`
```json
{
  "message": "Not Found"
}
```

**Error Response (validation failed):** `422 Unprocessable Entity`
```json
{
  "message": "The is checked field must be true or false.",
  "errors": {
    "is_checked": [
      "The is checked field must be true or false."
    ]
  }
}
```

> **Note:** If the checklist item exists but belongs to a different student, a 404 is returned (not 403), preventing information leakage.

---

#### `GET /api/v1/students/{student}/pickup-points`

**Auth required:** Yes (Bearer token — student or instructor)

Returns all pickup points for a given student, ordered by default first then alphabetically by label. Access is controlled by a policy:
- **Students** can only view their own pickup points.
- **Instructors** can only view pickup points for students assigned to them.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "label": "Home",
      "address": "1 High Street, Middlesbrough",
      "postcode": "TS1 1AA",
      "latitude": "54.57623000",
      "longitude": "-1.23456000",
      "is_default": true,
      "created_at": "2026-03-10T09:00:00+00:00",
      "updated_at": "2026-03-10T09:00:00+00:00"
    },
    {
      "id": 2,
      "label": "School",
      "address": "50 Borough Road, Middlesbrough",
      "postcode": "TS1 2HJ",
      "latitude": "54.57500000",
      "longitude": "-1.23000000",
      "is_default": false,
      "created_at": "2026-03-12T14:30:00+00:00",
      "updated_at": "2026-03-12T14:30:00+00:00"
    }
  ]
}
```

**Pickup Point Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Pickup point record ID |
| `label` | string | Human-readable label (e.g., "Home", "School") |
| `address` | string\|null | Full address |
| `postcode` | string\|null | UK postcode |
| `latitude` | string\|null | Latitude coordinate (decimal, 8 places) |
| `longitude` | string\|null | Longitude coordinate (decimal, 8 places) |
| `is_default` | boolean | Whether this is the student's default pickup point |
| `created_at` | string\|null | ISO 8601 timestamp |
| `updated_at` | string\|null | ISO 8601 timestamp |

---

#### `POST /api/v1/students/{student}/pickup-points`

**Auth required:** Yes (Bearer token — student or instructor)

Creates a new pickup point for a student. The postcode is geocoded automatically. Access requires `update` permission on the student (same policy as editing the student).

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `label` | string | Yes | Human-readable label (e.g., "Home", "School"). Max 255 chars |
| `address` | string | Yes | Full address. Max 1000 chars |
| `postcode` | string | Yes | Valid UK postcode. Max 10 chars |
| `is_default` | boolean | No | Set as default pickup point (unsets any existing default) |

```json
{
  "label": "Home",
  "address": "1 High Street, Middlesbrough",
  "postcode": "TS1 1AA",
  "is_default": true
}
```

**Success Response:** `201 Created`
```json
{
  "data": {
    "id": 3,
    "label": "Home",
    "address": "1 High Street, Middlesbrough",
    "postcode": "TS1 1AA",
    "latitude": "54.57623000",
    "longitude": "-1.23456000",
    "is_default": true,
    "created_at": "2026-03-25T10:00:00+00:00",
    "updated_at": "2026-03-25T10:00:00+00:00"
  }
}
```

**Validation Errors:** `422 Unprocessable Entity`
```json
{
  "message": "A label for this pickup point is required.",
  "errors": {
    "label": ["A label for this pickup point is required."]
  }
}
```

> **Note:** Pickup points are ordered with the default point first, then alphabetically by label. If no pickup points exist, `data` will be an empty array.

**Error Response (not authorised):** `403 Forbidden`
**Error Response (not found):** `404 Not Found`

---

#### `PUT /api/v1/students/{student}/pickup-points/{pickupPoint}`

**Auth required:** Yes (Bearer token — student or instructor)

Updates an existing pickup point. The postcode is re-geocoded only when it changes (normalised, case- and whitespace-insensitive). If `is_default: true` is provided, any other default pickup point for the same student is unset. Access requires `update` permission on the student (same dual-role policy as Create / Delete / Set Default — students may only edit their own; instructors may only edit pickup points of students assigned to them).

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `pickupPoint` | integer | The pickup point ID to update |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `label` | string | Yes | Human-readable label (e.g., "Home", "School"). Max 255 chars |
| `address` | string | Yes | Full address. Max 1000 chars |
| `postcode` | string | Yes | Valid UK postcode. Max 10 chars |
| `is_default` | boolean | No | Set as default pickup point (unsets any existing default for this student) |

```json
{
  "label": "Home",
  "address": "1 High Street, Middlesbrough",
  "postcode": "TS1 1AA",
  "is_default": true
}
```

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 3,
    "label": "Home",
    "address": "1 High Street, Middlesbrough",
    "postcode": "TS1 1AA",
    "latitude": "54.57623000",
    "longitude": "-1.23456000",
    "is_default": true,
    "created_at": "2026-03-25T10:00:00+00:00",
    "updated_at": "2026-04-28T14:00:00+00:00"
  }
}
```

**Validation Errors:** `422 Unprocessable Entity`
```json
{
  "message": "A label for this pickup point is required.",
  "errors": {
    "label": ["A label for this pickup point is required."],
    "postcode": ["Please enter a valid UK postcode."]
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (wrong student):** `404 Not Found`
```json
{
  "message": "Pickup point not found for this student."
}
```

> **Note:** The response shape is identical to `POST /api/v1/students/{student}/pickup-points`. The mobile app uses the same `PickupPoint` type for both flows.

---

#### `DELETE /api/v1/students/{student}/pickup-points/{pickupPoint}`

**Auth required:** Yes (Bearer token — student or instructor)

Deletes a pickup point for a student. Access requires `update` permission on the student.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `pickupPoint` | integer | The pickup point ID |

**Success Response:** `200 OK`
```json
{
  "message": "Pickup point deleted successfully."
}
```

**Error Response (not authorised):** `403 Forbidden`
**Error Response (wrong student):** `404 Not Found`
```json
{
  "message": "Pickup point not found for this student."
}
```

---

#### `PATCH /api/v1/students/{student}/pickup-points/{pickupPoint}/default`

**Auth required:** Yes (Bearer token — student or instructor)

Sets a pickup point as the default (primary) for a student. Automatically unsets any other default pickup point for the same student. Access requires `update` permission on the student.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |
| `pickupPoint` | integer | The pickup point ID to set as default |

**Success Response:** `200 OK`
```json
{
  "data": {
    "id": 3,
    "label": "Home",
    "address": "1 High Street, Middlesbrough",
    "postcode": "TS1 1AA",
    "latitude": "54.57623000",
    "longitude": "-1.23456000",
    "is_default": true,
    "created_at": "2026-03-25T10:00:00+00:00",
    "updated_at": "2026-03-31T14:00:00+00:00"
  }
}
```

**Error Response (not authorised):** `403 Forbidden`
**Error Response (wrong student):** `404 Not Found`
```json
{
  "message": "Pickup point not found for this student."
}
```

---

#### `POST /api/v1/students/{student}/orders`

**Auth required:** Yes (Bearer token — student or instructor)

Book lessons — creates an order, calendar items, and lessons.

For `upfront` payment the Stripe Checkout session is handled differently based on who is booking:
- **Student (mobile app):** the Stripe Checkout URL is returned in the response as `checkout_url`. The mobile app should load this URL in an in-app browser so the student can complete payment.
- **Instructor:** the payment link is emailed to the student (or their contact person). No `checkout_url` is returned.

For `weekly` payment the order is activated immediately and a confirmation email is sent.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `student` | integer | The student record ID |

**Request Body:**
```json
{
  "package_id": 1,
  "payment_mode": "upfront",
  "first_lesson_date": "2026-04-01",
  "start_time": "09:00",
  "end_time": "10:00"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `package_id` | integer | Yes | Package ID to book (must exist in `packages` table) |
| `payment_mode` | string | Yes | One of: `upfront`, `weekly` |
| `first_lesson_date` | string | Yes | First lesson date (YYYY-MM-DD, must be after today) |
| `start_time` | string | Yes | Lesson start time (HH:MM format) |
| `end_time` | string | Yes | Lesson end time (HH:MM format, must be after start_time) |

**Success Response (upfront payment — student-initiated):** `201 Created`
```json
{
  "message": "Order created. Open the checkout URL to complete payment.",
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_abc123...",
  "data": {
    "id": 1,
    "student_id": 1,
    "instructor_id": 1,
    "package_id": 1,
    "package_name": "10 Hour Package",
    "package_total_price_pence": 35000,
    "package_lesson_price_pence": 3500,
    "package_lessons_count": 10,
    "booking_fee_pence": 1999,
    "digital_fee_pence": 3990,
    "total_price_pence": 40989,
    "payment_mode": "upfront",
    "status": "pending",
    "lessons_count": 10,
    "created_at": "2026-03-23T10:00:00.000000Z"
  }
}
```

> The `checkout_url` field is only included when the request is authenticated as a **student**. If Stripe session creation fails, `checkout_url` will be `null` — the client should treat this as an error and not proceed to the in-app browser.

**Success Response (upfront payment — instructor-initiated):** `201 Created`
```json
{
  "message": "Order created. A payment link has been emailed to the student.",
  "data": {
    "id": 1,
    "student_id": 1,
    "instructor_id": 1,
    "package_id": 1,
    "package_name": "10 Hour Package",
    "package_total_price_pence": 35000,
    "package_lesson_price_pence": 3500,
    "package_lessons_count": 10,
    "booking_fee_pence": 1999,
    "digital_fee_pence": 3990,
    "total_price_pence": 40989,
    "payment_mode": "upfront",
    "status": "pending",
    "lessons_count": 10,
    "created_at": "2026-03-23T10:00:00.000000Z"
  }
}
```

**Success Response (weekly payment):** `201 Created`
```json
{
  "message": "Order created and activated. Lesson invoices will be sent before each lesson.",
  "data": {
    "id": 2,
    "student_id": 1,
    "instructor_id": 1,
    "package_id": 1,
    "package_name": "10 Hour Package",
    "package_total_price_pence": 35000,
    "package_lesson_price_pence": 3500,
    "package_lessons_count": 10,
    "booking_fee_pence": 1999,
    "digital_fee_pence": 3990,
    "total_price_pence": 40989,
    "payment_mode": "weekly",
    "status": "active",
    "lessons_count": 10,
    "created_at": "2026-03-23T10:00:00.000000Z"
  }
}
```

**Order Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Order record ID |
| `student_id` | integer | Student record ID |
| `instructor_id` | integer | Instructor record ID |
| `package_id` | integer | Package record ID |
| `package_name` | string | Name of the booked package |
| `package_total_price_pence` | integer | Base package price in pence (before fees) |
| `package_lesson_price_pence` | integer | Per-lesson price in pence |
| `package_lessons_count` | integer | Number of lessons in the package |
| `booking_fee_pence` | integer | Booking fee in pence (e.g., 1999 = £19.99) |
| `digital_fee_pence` | integer | Total digital fee in pence (£3.99 × lessons) |
| `total_price_pence` | integer | Total charge amount sent to Stripe (package + booking fee + digital fees) |
| `payment_mode` | string | `upfront` or `weekly` |
| `status` | string | Order status: `pending`, `active`, `completed`, `cancelled` |
| `lessons_count` | integer\|null | Number of lessons created |
| `created_at` | string\|null | ISO 8601 timestamp |

**Error Response (student has no instructor):** `422 Unprocessable Entity`
```json
{
  "message": "Student must have an assigned instructor before booking."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The package id field is required.",
  "errors": {
    "package_id": ["The package id field is required."],
    "payment_mode": ["The payment mode field is required."]
  }
}
```

> **Mobile App Flow (upfront payment — student booking via mobile app):**
> 1. POST to create order as an authenticated student → response includes `checkout_url`
> 2. Open `checkout_url` in an in-app browser so the student can complete Stripe Checkout
> 3. After payment, Stripe redirects to the verify endpoint which confirms payment server-side
> 4. On success, the order becomes `active` and a confirmation email is sent
>
> **Mobile App Flow (upfront payment — instructor booking on behalf of a student):**
> 1. POST to create order as an authenticated instructor → no `checkout_url` returned
> 2. A Stripe Checkout link is emailed to the student
> 3. The student opens the email link and completes Stripe Checkout
> 4. On success, the order becomes `active` and a confirmation email is sent
>
> **Mobile App Flow (weekly payment):**
> 1. POST to create order with `payment_mode: "weekly"` → order is immediately `active`
> 2. A confirmation email is sent to the student (or contact if booked on their behalf)
> 3. The first Stripe invoice + payment-link email is sent **immediately at booking time** for the earliest scheduled lesson
> 4. Each subsequent invoice + payment-link email is sent **immediately when the previous lesson is signed off**, for the next earliest unpaid lesson
> 5. Stripe webhooks (`invoice.paid`) automatically mark lesson payments as paid
>
> Whenever the payment-reminder email is sent, an **additive push notification** is also queued for the student's user — but only when an Expo push token is registered on that user. If the student does not own the account (parent/contact booked on their behalf) or has not registered a push token, the email still goes out and no push is queued. Push delivery is processed by the every-minute `push:send-queued` cron and uses payload `{ type: "lesson_payment", lesson_payment_id, lesson_id, hosted_invoice_url }`.
>
> The legacy `lessons:send-invoices` command remains as a manual fallback to sweep any LessonPayments that slipped through (e.g. if a Stripe call failed during the event-driven send) but is no longer scheduled.

---

#### `GET /api/v1/orders/{order}/checkout/verify`

**Auth required:** Yes (Bearer token — student or instructor)

Verify a Stripe Checkout payment and activate the order. Call this after the user completes payment in the Stripe Checkout flow. On successful verification, a confirmation email is sent to the student.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `order` | integer | The order record ID |

**Query Parameters:**

| Parameter | Type | Required | Notes |
|-----------|------|----------|-------|
| `session_id` | string | Yes | The Stripe Checkout session ID (returned by Stripe after payment) |

**Example Request:**
```
GET /api/v1/orders/1/checkout/verify?session_id=cs_test_abc123...
```

**Success Response (payment verified):** `200 OK`
```json
{
  "verified": true,
  "message": "Payment verified and order activated.",
  "data": {
    "id": 1,
    "student_id": 1,
    "instructor_id": 1,
    "package_id": 1,
    "package_name": "10 Hour Package",
    "package_total_price_pence": 35000,
    "package_lesson_price_pence": 3500,
    "package_lessons_count": 10,
    "payment_mode": "upfront",
    "status": "active",
    "lessons_count": 10,
    "created_at": "2026-03-23T10:00:00.000000Z"
  }
}
```

**Error Response (payment not completed):** `422 Unprocessable Entity`
```json
{
  "verified": false,
  "message": "Payment not completed.",
  "data": {
    "id": 1,
    "status": "pending"
  }
}
```

**Error Response (missing session_id):** `422 Unprocessable Entity`
```json
{
  "message": "Session ID is required."
}
```

---

### Resources

---

#### `GET /api/v1/resources`

**Auth required:** Yes (Bearer token)

Returns published learning resources, optionally filtered by audience. Resources can be videos or files (PDFs, documents, images, etc.). Use this endpoint to power either a student-only or instructor-only resource library in the mobile app.

**Query Parameters:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `audience` | string | No | `student` → only student resources. `instructor` → only instructor resources. Omit to return both. |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "title": "Highway Code - Roundabouts",
      "description": "Official Highway Code section on roundabouts",
      "tags": ["roundabouts", "junctions", "highway code"],
      "audience": "student",
      "resource_type": "file",
      "video_url": null,
      "file_path": "resources/highway-code-roundabouts.pdf",
      "file_name": "highway-code-roundabouts.pdf",
      "file_size": 102400,
      "mime_type": "application/pdf",
      "thumbnail_url": null
    },
    {
      "id": 2,
      "title": "Instructor Training — Managing Nervous Pupils",
      "description": "Coaching techniques for first-time drivers",
      "tags": ["coaching", "soft skills"],
      "audience": "instructor",
      "resource_type": "video_link",
      "video_url": "https://www.youtube.com/watch?v=example",
      "file_path": null,
      "file_name": null,
      "file_size": null,
      "mime_type": null,
      "thumbnail_url": "https://img.youtube.com/vi/example/hqdefault.jpg"
    }
  ]
}
```

**Resource Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Resource title |
| `description` | string\|null | Resource description |
| `tags` | array | Array of tag strings for categorisation |
| `audience` | string | `student` or `instructor` — who this resource is intended for |
| `resource_type` | string | Type: `video_link` or `file` |
| `video_url` | string\|null | Video URL (for `video_link` type) |
| `file_path` | string\|null | File storage path (for `file` type) |
| `file_name` | string\|null | Original file name |
| `file_size` | integer\|null | File size in bytes |
| `mime_type` | string\|null | MIME type (e.g., `application/pdf`, `image/png`) |
| `thumbnail_url` | string\|null | Thumbnail URL if available |

**Validation Errors:** `422 Unprocessable Entity`
- `audience` must be `student` or `instructor` if provided.

> **Notes:**
> - Only published resources are returned.
> - The instructor mobile app should call `GET /api/v1/resources?audience=instructor`; the student app can either call this with `?audience=student` or use the richer `/api/v1/student/resources` tree view.
> - All `/api/v1/student/...` resource endpoints (`/student/resources`, `/student/resources/{resource}`, `/student/resource-summary`) are hard-filtered server-side to `audience = 'student'`. Instructor resources never appear in the student app, even if dropped into a shared folder. No client-side filtering needed.

---

#### `GET /api/v1/resources/{resource}`

**Auth required:** Yes (Bearer token — any authenticated user)

Returns a single published resource with its full details including the actual content URL. For `video_link` resources, this is the YouTube/Vimeo URL. For `file` resources, this is a time-limited signed S3 URL (valid for 30 minutes).

Unlike `GET /api/v1/student/resources/{resource}`, this endpoint is **not student-scoped** — no `is_watched` / `is_suggested` flags are returned, and instructors can call it. Use this from the instructor mobile app (or whenever the caller is not a student).

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `resource` | integer | Resource ID |

**Request Body:** None

**Success Response:** `200 OK`

**Video link example:**
```json
{
  "data": {
    "id": 2,
    "title": "Instructor Training — Managing Nervous Pupils",
    "description": "Coaching techniques for first-time drivers.",
    "tags": ["coaching", "soft skills"],
    "audience": "instructor",
    "resource_type": "video_link",
    "video_url": "https://www.youtube.com/watch?v=example",
    "file_url": null,
    "file_name": null,
    "thumbnail_url": null
  }
}
```

**File example:**
```json
{
  "data": {
    "id": 10,
    "title": "Highway Code — Roundabouts",
    "description": "Official Highway Code section on roundabouts.",
    "tags": ["roundabouts", "junctions"],
    "audience": "student",
    "resource_type": "file",
    "video_url": null,
    "file_url": "https://drivecrm.s3.eu-west-2.amazonaws.com/resources/highway-code-roundabouts.pdf?X-Amz-Expires=1800&...",
    "file_name": "highway-code-roundabouts.pdf",
    "thumbnail_url": null
  }
}
```

**Resource Detail Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Resource ID |
| `title` | string | Resource title |
| `description` | string\|null | Full description |
| `tags` | array\|null | Tag strings |
| `audience` | string | `student` or `instructor` |
| `resource_type` | string | `video_link` or `file` |
| `video_url` | string\|null | YouTube/Vimeo URL (video_link resources only) |
| `file_url` | string\|null | Signed S3 URL, valid 30 minutes (file resources only) |
| `file_name` | string\|null | Original file name (file resources only) |
| `thumbnail_url` | string\|null | Thumbnail image URL |

**Error Response — resource not found or unpublished:** `404 Not Found`
```json
{
  "message": "No query results for model [App\\Models\\Resource] 999."
}
```

---

### Messages

---

#### `GET /api/v1/messages/conversations`

**Auth required:** Yes (Bearer token — instructor or student)

Returns all conversations for the authenticated user, grouped by the other participant. Each conversation includes the latest message preview. Ordered by most recent message first.

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "user": {
        "id": 5,
        "name": "Jane Doe"
      },
      "latest_message": {
        "id": 42,
        "message": "See you at 9am tomorrow!",
        "is_own": true,
        "created_at": "2026-03-22T18:30:00+00:00"
      }
    },
    {
      "user": {
        "id": 8,
        "name": "Tom Brown"
      },
      "latest_message": {
        "id": 38,
        "message": "Thanks for the feedback on today's lesson.",
        "is_own": false,
        "created_at": "2026-03-21T15:00:00+00:00"
      }
    }
  ]
}
```

**Conversation Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `user.id` | integer | The other participant's user ID |
| `user.name` | string | The other participant's name |
| `latest_message.id` | integer | Message ID |
| `latest_message.message` | string | Message content |
| `latest_message.is_own` | boolean | Whether the authenticated user sent this message |
| `latest_message.created_at` | string | ISO 8601 timestamp |

> **Note:** Conversations are automatically scoped to the authenticated user.

---

#### `GET /api/v1/messages/conversations/instructor`

**Auth required:** Yes (Bearer token — **student only**)

Convenience endpoint for students. Returns paginated messages between the authenticated student and their assigned instructor. The instructor is resolved automatically from the student's `instructor_id` — no ID parameter needed.

Messages are ordered newest first for pagination (30 per page).

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 42,
      "sender_id": 1,
      "sender_name": "John Smith",
      "recipient_id": 5,
      "message": "See you at 9am tomorrow!",
      "is_own": false,
      "created_at": "2026-03-22T18:30:00+00:00"
    },
    {
      "id": 41,
      "sender_id": 5,
      "sender_name": "Jane Doe",
      "recipient_id": 1,
      "message": "What time is my lesson tomorrow?",
      "is_own": true,
      "created_at": "2026-03-22T18:25:00+00:00"
    }
  ]
}
```

**Message Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Message ID |
| `sender_id` | integer | Sender's user ID |
| `sender_name` | string\|null | Sender's name |
| `recipient_id` | integer | Recipient's user ID |
| `message` | string | Message content |
| `is_own` | boolean | Whether the authenticated user sent this message |
| `created_at` | string | ISO 8601 timestamp |

> **Note:** Messages are returned newest first. The mobile app should reverse the order for chronological display in the chat UI.

**Error Response (no instructor assigned):** `404 Not Found`
```json
{
  "message": "No instructor assigned."
}
```

**Error Response (not authorised):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

---

#### `GET /api/v1/messages/conversations/{user}`

**Auth required:** Yes (Bearer token — instructor or student)

Returns paginated messages between the authenticated user and the specified user. Messages are ordered newest first for pagination (30 per page). Authorization requires an instructor-student relationship between the two users.

> **Tip for students:** Prefer `GET /api/v1/messages/conversations/instructor` — it resolves the instructor automatically so you don't need to know their user ID.

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `user` | integer | The other participant's user ID |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 42,
      "sender_id": 1,
      "sender_name": "John Smith",
      "recipient_id": 5,
      "message": "See you at 9am tomorrow!",
      "is_own": true,
      "created_at": "2026-03-22T18:30:00+00:00"
    },
    {
      "id": 41,
      "sender_id": 5,
      "sender_name": "Jane Doe",
      "recipient_id": 1,
      "message": "What time is my lesson tomorrow?",
      "is_own": false,
      "created_at": "2026-03-22T18:25:00+00:00"
    }
  ]
}
```

**Message Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Message ID |
| `sender_id` | integer | Sender's user ID |
| `sender_name` | string\|null | Sender's name |
| `recipient_id` | integer | Recipient's user ID |
| `message` | string | Message content |
| `is_own` | boolean | Whether the authenticated user sent this message |
| `created_at` | string | ISO 8601 timestamp |

> **Note:** Messages are returned newest first. The mobile app should reverse the order for chronological display in the chat UI.

**Error Response (not authorised):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

---

#### `POST /api/v1/messages`

**Auth required:** Yes (Bearer token — instructor or student)

Send a new message to another user. Authorization ensures only instructor-student pairs can message each other.

- **Instructors** must provide `recipient_id` (the student ID from the students table).
- **Students** may omit `recipient_id` — the backend automatically resolves their assigned instructor.

**Request Body (instructor):**
```json
{
  "recipient_id": 5,
  "message": "Great lesson today! Keep up the good work."
}
```

**Request Body (student — recipient_id optional):**
```json
{
  "message": "What time is my lesson tomorrow?"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `recipient_id` | integer | Instructors: Yes, Students: No | For instructors: the student ID from the students table. For students: omit to auto-resolve instructor, or pass the instructor's user ID. |
| `message` | string | Yes | Message content (max 5000 characters) |

**Success Response:** `201 Created`
```json
{
  "data": {
    "id": 43,
    "sender_id": 1,
    "sender_name": "John Smith",
    "recipient_id": 5,
    "message": "Great lesson today! Keep up the good work.",
    "is_own": true,
    "created_at": "2026-03-22T19:00:00+00:00"
  }
}
```

**Error Response (no instructor assigned — student without recipient_id):** `404 Not Found`
```json
{
  "message": "No instructor assigned."
}
```

**Error Response (no instructor-student relationship):** `403 Forbidden`
```json
{
  "message": "This action is unauthorized."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "A recipient is required.",
  "errors": {
    "recipient_id": ["A recipient is required."],
    "message": ["A message is required."]
  }
}
```

**Custom Validation Messages:**
- `recipient_id.required`: "A recipient is required." (instructors only)
- `recipient_id.exists`: "The selected recipient does not exist."
- `message.required`: "A message is required."
- `message.max`: "The message must not exceed 5000 characters."

---

### Push Notifications

#### `POST /api/v1/push-token`

**Auth required:** Yes (Bearer token)

Stores the user's Expo push token for receiving push notifications. If the user already has a token stored, it will be overwritten with the new one. Works for both instructors and students.

**Request Body:**
```json
{
  "expo_push_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"
}
```

| Field | Type | Required | Rules | Description |
|-------|------|----------|-------|-------------|
| `expo_push_token` | string | Yes | Must match format `ExponentPushToken[...]` | The Expo push token obtained from the device |

**Success Response:** `200 OK`
```json
{
  "message": "Push token stored successfully."
}
```

**Error Response (validation):** `422 Unprocessable Entity`
```json
{
  "message": "The expo push token field is required.",
  "errors": {
    "expo_push_token": ["The expo push token field is required."]
  }
}
```

**Error Response (invalid format):** `422 Unprocessable Entity`
```json
{
  "message": "The expo push token field format is invalid.",
  "errors": {
    "expo_push_token": ["The expo push token field format is invalid."]
  }
}
```

**Error Response (unauthenticated):** `401 Unauthorized`
```json
{
  "message": "Unauthenticated."
}
```

**Mobile Integration Notes:**
- Call this endpoint after login and whenever the Expo push token changes (e.g., app reinstall, token refresh).
- The token is stored directly on the user record (`expo_push_token` column).
- Only one token per user is stored — the latest call wins.

**Events that queue a push notification (additive — fires only when `expo_push_token` is set on the recipient):**

| Event | Title | Body | Data payload |
|-------|-------|------|--------------|
| New in-app message received | `"New message from {sender name}"` | First 140 chars of the message text | `{ type: "message", message_id, from_user_id }` |
| Weekly-payment reminder issued (booking-time and on prior-lesson sign-off) | `"Time to pay for your lesson"` | `"Check your email to pay for your upcoming lesson on {Day D Mon}."` | `{ type: "lesson_payment", lesson_payment_id, lesson_id, hosted_invoice_url }` |

Pushes are queued to the `push_notifications` table and delivered by the every-minute `push:send-queued` cron (so up to ~60s latency is normal). Email and in-app delivery are unaffected by push success or failure — push is a strictly additive layer.

---

## Profile Object by Role

The `profile` key in user responses contains role-specific data. The shape depends on the user's `role`:

### Instructor Profile

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Instructor record ID |
| `bio` | string\|null | Instructor biography |
| `transmission_type` | string\|null | `manual`, `automatic`, or `both` |
| `status` | string\|null | Instructor status |
| `address` | string\|null | Business address |
| `postcode` | string\|null | Business postcode |
| `pin` | string\|null | Instructor's attach PIN — students enter this on the mobile app to link themselves to the instructor (`POST /api/v1/students/attach`) |
| `onboarding_complete` | boolean | Whether Stripe onboarding is done |
| `charges_enabled` | boolean | Whether Stripe charges are enabled |
| `payouts_enabled` | boolean | Whether Stripe payouts are enabled |
| `profile_picture_url` | string\|null | URL to profile picture (null if not set) |

### Student Profile

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Student record ID |
| `first_name` | string | Student's first name |
| `surname` | string | Student's surname |
| `phone` | string\|null | Student's phone number |
| `status` | string\|null | Student status (e.g., `active`) |
| `instructor_id` | integer\|null | Assigned instructor ID (null if unassigned) |

---

## Appendix: User Roles

The system has three user roles. The mobile app will likely serve **instructors** and **students**.

| Role | Value | Description |
|------|-------|-------------|
| Owner | `owner` | System admin, manages instructors and all data |
| Instructor | `instructor` | Driving instructor, manages students, lessons, calendar |
| Student | `student` | Learner driver, views lessons, packages, resources |

The `role` field is always returned in user responses. Use it to determine which screens/features to show in the mobile app.

---

## Quick Route Reference

| Method | Path | Auth | Role | Description |
|--------|------|------|------|-------------|
| POST | `/api/v1/auth/login` | No | Any | Login |
| POST | `/api/v1/auth/register/student` | No | — | Register student |
| POST | `/api/v1/auth/register/instructor` | No | — | Register instructor |
| POST | `/api/v1/auth/logout` | Yes | Any | Logout |
| POST | `/api/v1/auth/change-password` | Yes | Any | Change password (clears forced-change flag) |
| GET | `/api/v1/auth/user` | Yes | Any | Get current user |
| PUT | `/api/v1/instructor/profile` | Yes | Instructor | Update profile |
| POST | `/api/v1/instructor/profile/picture` | Yes | Instructor | Upload profile picture |
| DELETE | `/api/v1/instructor/profile/picture` | Yes | Instructor | Delete profile picture |
| GET | `/api/v1/instructor/students` | Yes | Instructor | List students (grouped) |
| GET | `/api/v1/instructor/lessons/{date}` | Yes | Instructor | Day view lessons |
| PATCH | `/api/v1/instructor/lessons/{lesson}/mileage` | Yes | Instructor | Update lesson mileage |
| GET | `/api/v1/instructor/packages` | Yes | Instructor | List packages |
| POST | `/api/v1/instructor/packages` | Yes | Instructor | Create package |
| PUT | `/api/v1/instructor/packages/{package}` | Yes | Instructor | Update package |
| GET | `/api/v1/instructor/calendar/items` | Yes | Instructor | List calendar items for a date |
| POST | `/api/v1/instructor/calendar/items` | Yes | Instructor | Create calendar item |
| DELETE | `/api/v1/instructor/calendar/items/{calendarItem}` | Yes | Instructor | Delete calendar item |
| GET | `/api/v1/instructor/finances` | Yes | Instructor | List finance records |
| POST | `/api/v1/instructor/finances` | Yes | Instructor | Create finance record |
| PUT | `/api/v1/instructor/finances/{finance}` | Yes | Instructor | Update finance record |
| DELETE | `/api/v1/instructor/finances/{finance}` | Yes | Instructor | Delete finance record |
| GET | `/api/v1/student/packages` | Yes | Student | List attached instructor's packages |
| GET | `/api/v1/student/calendar/items` | Yes | Student | List attached instructor's available slots |
| GET | `/api/v1/student/instructor` | Yes | Student | View attached instructor's public profile |
| GET | `/api/v1/student/dashboard` | Yes | Student | Student dashboard data (practice hours, suggested resources) |
| GET | `/api/v1/student/resource-summary` | Yes | Student | Aggregated resource dashboard (recent activity, stats, progress, tips) |
| GET | `/api/v1/student/resources` | Yes | Student | Full resource library (folder tree + my resources + watched flags) |
| GET | `/api/v1/student/resources/{resource}` | Yes | Student | Single resource detail with video/file URL |
| POST | `/api/v1/student/resources/{resource}/watched` | Yes | Student | Mark resource as watched (idempotent) |
| POST | `/api/v1/students` | Yes | Instructor | Create student |
| POST | `/api/v1/students/attach` | Yes | Student | Attach to instructor via PIN |
| GET | `/api/v1/students/{student}` | Yes | Both | View student |
| PUT | `/api/v1/students/{student}` | Yes | Both | Update student |
| DELETE | `/api/v1/students/{student}` | Yes | Both | Remove student (soft) |
| GET | `/api/v1/students/{student}/lessons` | Yes | Both | List lessons |
| GET | `/api/v1/students/{student}/lessons/{lesson}` | Yes | Both | Lesson detail |
| POST | `/api/v1/students/{student}/lessons/{lesson}/sign-off` | Yes | Both | Sign off lesson |
| POST | `/api/v1/students/{student}/lessons/{lesson}/resources` | Yes | Instructor | Assign resources |
| GET | `/api/v1/students/{student}/notes` | Yes | Both | List notes |
| POST | `/api/v1/students/{student}/notes` | Yes | Both | Create note |
| PUT | `/api/v1/students/{student}/notes/{note}` | Yes | Both | Update note |
| DELETE | `/api/v1/students/{student}/notes/{note}` | Yes | Both | Delete note (soft) |
| GET | `/api/v1/students/{student}/checklist-items` | Yes | Both | List checklist |
| PUT | `/api/v1/students/{student}/checklist-items/{item}` | Yes | Both | Update checklist item |
| GET | `/api/v1/students/{student}/pickup-points` | Yes | Both | List pickup points |
| POST | `/api/v1/students/{student}/pickup-points` | Yes | Both | Create pickup point |
| PUT | `/api/v1/students/{student}/pickup-points/{pickupPoint}` | Yes | Both | Update pickup point |
| DELETE | `/api/v1/students/{student}/pickup-points/{pickupPoint}` | Yes | Both | Delete pickup point |
| PATCH | `/api/v1/students/{student}/pickup-points/{pickupPoint}/default` | Yes | Both | Set default pickup point |
| POST | `/api/v1/students/{student}/orders` | Yes | Both | Create order/booking |
| GET | `/api/v1/orders/{order}/checkout/verify` | Yes | Both | Verify payment |
| GET | `/api/v1/packages/{package}/pricing` | Yes | Any | Package pricing breakdown |
| GET | `/api/v1/resources` | Yes | Any | List resources |
| GET | `/api/v1/resources/{resource}` | Yes | Any | Resource detail with signed file URL |
| GET | `/api/v1/messages/conversations` | Yes | Both | List conversations |
| GET | `/api/v1/messages/conversations/instructor` | Yes | Student | View conversation with assigned instructor |
| GET | `/api/v1/messages/conversations/{user}` | Yes | Both | View conversation by user ID |
| POST | `/api/v1/messages` | Yes | Both | Send message (students: recipient_id optional) |
| GET | `/api/v1/student/mock-tests/summary` | Yes | Student | Mock test dashboard summary |
| POST | `/api/v1/student/mock-tests/start` | Yes | Student | Start a new mock test (generates 50 random questions) |
| POST | `/api/v1/student/mock-tests/{mockTest}/submit` | Yes | Student | Submit answers for a mock test |
| GET | `/api/v1/student/mock-tests/{mockTest}/review` | Yes | Student | Review a completed mock test |

---

## Mock Tests

Mock theory test system. Students take randomised 50-question tests from a bank of ~2,923 questions across 4 categories (Car, ADI, Motorcycle, LGV-PCV). Records every answer for per-category performance tracking.

### `GET /api/v1/student/mock-tests/summary`

Returns aggregated mock test statistics for the authenticated student. Includes tests taken, average score, pass count, last 5 scores, a random test-taking tip, and per-category performance breakdown.

**Auth:** Bearer token (student role)

**Query Parameters:**

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| category | string | No | Filter to a specific category: `Car`, `ADI`, `Motorcycle`, `LGV-PCV` |

**Response (200):**

```json
{
  "data": {
    "tests_taken": 12,
    "average_score": 84.5,
    "tests_passed": 9,
    "recent_scores": [
      {
        "id": 45,
        "category": "Car",
        "topic": null,
        "total_questions": 50,
        "correct_answers": 43,
        "passed": true,
        "started_at": "2026-04-14T10:00:00+00:00",
        "completed_at": "2026-04-14T10:30:00+00:00"
      }
    ],
    "tip": "Focus on understanding the 'why' behind each answer, not just memorising.",
    "category_performance": [
      {
        "topic": "Alertness",
        "total_answered": 15,
        "correct": 12,
        "percentage": 80.0
      },
      {
        "topic": "Road and traffic signs",
        "total_answered": 30,
        "correct": 18,
        "percentage": 60.0
      }
    ]
  }
}
```

---

### `POST /api/v1/student/mock-tests/start`

Starts a new mock test. Generates 50 random questions. If no category is provided, questions are drawn from all categories (stored as `"Mixed"`). Optionally filter by topic within a category. Returns the test ID and all questions (without correct answers).

**Auth:** Bearer token (student role)

**Request Body:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| category | string | No | `in:Car,ADI,Motorcycle,LGV-PCV` | Question bank to draw from. Omit for a mixed test across all categories. |
| topic | string | No | max:100 | Filter to a specific topic (e.g. "Alertness", "Road and traffic signs") |

**Response (201):**

```json
{
  "data": {
    "mock_test": {
      "id": 46,
      "category": "Car",
      "topic": null,
      "total_questions": 50,
      "correct_answers": 0,
      "passed": false,
      "started_at": "2026-04-14T11:00:00+00:00",
      "completed_at": null
    },
    "questions": [
      {
        "id": 123,
        "stem": "What should you do before making a U-turn?",
        "stem_image": null,
        "option_a": "Give an arm signal as well as using your indicators",
        "option_a_image": null,
        "option_b": "Check road markings to see that U-turns are permitted",
        "option_b_image": null,
        "option_c": "Look over your shoulder for a final check",
        "option_c_image": null,
        "option_d": "Select a higher gear than normal",
        "option_d_image": null,
        "topic": "Alertness",
        "explanation": "If you have to make a U-turn, slow down and make sure that the road is clear..."
      },
      {
        "id": 456,
        "stem": "Which instrument-panel warning light would show that headlights are on main beam?",
        "stem_image": null,
        "option_a": null,
        "option_a_image": "/storage/mock-test-images/Car/BB1591a.gif",
        "option_b": null,
        "option_b_image": "/storage/mock-test-images/Car/BB1591b.gif",
        "option_c": null,
        "option_c_image": "/storage/mock-test-images/Car/BB1591c.gif",
        "option_d": null,
        "option_d_image": "/storage/mock-test-images/Car/BB1591d.gif",
        "topic": "Attitude",
        "explanation": "You should be aware of all the warning lights and visual aids on the vehicle..."
      }
    ]
  }
}
```

**Notes:**
- Questions are returned WITHOUT the correct answer — the mobile app should not know the answer until submission
- If `option_a` is null but `option_a_image` is not, the answer is image-based — render the image instead of text
- Image URLs are relative to the API base URL (or absolute when using S3)

---

### `POST /api/v1/student/mock-tests/{mockTest}/submit`

Submits all answers for a mock test. Scores the test, records each answer, and returns the full review with correct answers and explanations.

**Auth:** Bearer token (student role)

**URL Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| mockTest | integer | The mock test ID (from the start endpoint) |

**Request Body:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| answers | array | Yes | min:1 | Array of answer objects |
| answers.*.question_id | integer | Yes | exists:mock_test_questions,id | Question ID |
| answers.*.selected_answer | string | Yes | in:A,B,C,D,a,b,c,d | Selected answer |

**Example Request:**

```json
{
  "answers": [
    { "question_id": 123, "selected_answer": "C" },
    { "question_id": 456, "selected_answer": "A" }
  ]
}
```

**Response (200):**

```json
{
  "data": {
    "id": 46,
    "category": "Car",
    "topic": null,
    "total_questions": 50,
    "correct_answers": 43,
    "passed": true,
    "started_at": "2026-04-14T11:00:00+00:00",
    "completed_at": "2026-04-14T11:25:00+00:00",
    "answers": [
      {
        "question_id": 123,
        "stem": "What should you do before making a U-turn?",
        "stem_image": null,
        "option_a": "Give an arm signal as well as using your indicators",
        "option_a_image": null,
        "option_b": "Check road markings to see that U-turns are permitted",
        "option_b_image": null,
        "option_c": "Look over your shoulder for a final check",
        "option_c_image": null,
        "option_d": "Select a higher gear than normal",
        "option_d_image": null,
        "topic": "Alertness",
        "selected_answer": "C",
        "correct_answer": "C",
        "is_correct": true,
        "explanation": "If you have to make a U-turn, slow down and make sure that the road is clear..."
      }
    ]
  }
}
```

**Error Responses:**
- `403` — Mock test does not belong to the authenticated student
- `422` — Test has already been submitted (`completed_at` is not null)

---

### `GET /api/v1/student/mock-tests/{mockTest}/review`

Returns the full review of a completed mock test, including all questions, the student's answers, correct answers, and explanations.

**Auth:** Bearer token (student role)

**URL Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| mockTest | integer | The mock test ID |

**Response (200):** Same shape as the submit response (see above).

**Error Responses:**
- `403` — Mock test does not belong to the authenticated student

---

## Hazard Perception

Hazard perception video system for the student mobile app. Students watch video clips and identify developing hazards by tapping at the right moment. Each clip has 1 or 2 hazards with scored timing windows. Videos are categorised by category and topic.

**Scoring:** Each hazard's timing window is divided into 5 equal bands. Responding in the earliest band scores 5 points, the latest band scores 1 point. Responding outside the window scores 0. Single hazard clips have a max score of 5, double hazard clips have a max score of 10.

---

#### `GET /api/v1/student/hazard-perception/videos`

**Auth required:** Yes (Bearer token — student only)

Returns all hazard perception videos grouped by category and topic. Optionally filter by category.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `category` | string | No | Filter by category: `Car`, `ADI`, `Motorcycle`, `LGV-PCV` |

**Success Response:** `200 OK`
```json
{
  "data": {
    "Car": {
      "Junctions": [
        {
          "id": 1,
          "title": "Junction approach with pedestrian",
          "description": "A car approaching a T-junction with a pedestrian stepping into the road.",
          "category": "Car",
          "topic": "Junctions",
          "video_url": "hazard-perception/abc123.mp4",
          "duration_seconds": 60,
          "is_double_hazard": false,
          "thumbnail_url": null
        }
      ],
      "Roundabouts": [
        {
          "id": 2,
          "title": "Roundabout with cyclist",
          "description": "Approaching a roundabout with a cyclist merging from the left.",
          "category": "Car",
          "topic": "Roundabouts",
          "video_url": "hazard-perception/def456.mp4",
          "duration_seconds": 75,
          "is_double_hazard": true,
          "thumbnail_url": null
        }
      ]
    }
  }
}
```

**Video Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Video record ID |
| `title` | string | Video title |
| `description` | string\|null | Brief scenario description |
| `category` | string | Category: Car, ADI, Motorcycle, LGV-PCV |
| `topic` | string | Topic within category |
| `video_url` | string | Path/URL to the video file |
| `duration_seconds` | integer | Video length in seconds |
| `is_double_hazard` | boolean | Whether this clip has two hazards (max score 10 instead of 5) |
| `thumbnail_url` | string\|null | Optional thumbnail image URL |

> **Note:** Hazard timing windows are NOT returned to the client — scoring is calculated server-side when the student submits response times.

---

#### `POST /api/v1/student/hazard-perception/videos/{hazardPerceptionVideo}/submit`

**Auth required:** Yes (Bearer token — student only)

Submit all of the student's tap timestamps from the video. The mobile app sends every tap the user made during playback as an array of seconds. The backend looks up the video's hazard timing windows, finds the best-matching tap for each hazard, and calculates scores based on closeness to the hazard start time.

**Scoring algorithm:**
- The scoring window runs from `hazard_X_start` to `hazard_X_end` (stored on the video, not sent to the client).
- The window is divided into 5 equal bands based on elapsed time from the start.
- A tap in the **first 20%** of the window (closest to the hazard appearing) = **5 points**.
- **20%-40%** = 4 points, **40%-60%** = 3 points, **60%-80%** = 2 points, **80%-100%** = 1 point.
- A tap **outside** the window = 0 points.
- If multiple taps land in the window, the **best-scoring** tap is used.
- For double hazard clips, each hazard is scored independently (max 10 total).

**URL Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `hazardPerceptionVideo` | integer | The hazard perception video ID |

**Request Body:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `taps` | array | Yes | array of numbers | Every tap timestamp in seconds (e.g., every time the user tapped the screen during the video) |
| `taps.*` | number | Yes | numeric, min:0 | Individual tap timestamp (seconds into the video) |

**Example Request:**
```json
{
  "taps": [3.20, 12.85, 23.50, 31.00, 45.20, 58.10]
}
```

**Success Response:** `201 Created`
```json
{
  "data": {
    "id": 1,
    "hazard_perception_video_id": 2,
    "hazard_1_response_time": "23.50",
    "hazard_1_score": 4,
    "hazard_2_response_time": "45.20",
    "hazard_2_score": 3,
    "total_score": 7,
    "completed_at": "2026-04-14T15:30:00+00:00"
  }
}
```

> **Note:** `hazard_1_response_time` / `hazard_2_response_time` are the specific taps the backend selected as the best match for each hazard window. They will be `null` if no tap fell within that hazard's window (score = 0).

**Attempt Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Attempt record ID |
| `hazard_perception_video_id` | integer | The video this attempt is for |
| `hazard_1_response_time` | string\|null | The best-matching tap for hazard 1 (null if no tap hit the window) |
| `hazard_1_score` | integer | Score 0-5 for hazard 1 |
| `hazard_2_response_time` | string\|null | The best-matching tap for hazard 2 (null if single hazard or no tap hit the window) |
| `hazard_2_score` | integer\|null | Score 0-5 for hazard 2 (null if single hazard clip) |
| `total_score` | integer | Combined score (max 5 single, max 10 double) |
| `completed_at` | string | ISO 8601 timestamp |

**Error Response (validation):** `422 Unprocessable Entity`

---

#### `GET /api/v1/student/hazard-perception/summary`

**Auth required:** Yes (Bearer token — student only)

Returns the student's hazard perception performance summary. Optionally filter by category.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `category` | string | No | Filter by category: `Car`, `ADI`, `Motorcycle`, `LGV-PCV` |

**Success Response:** `200 OK`
```json
{
  "data": {
    "attempts_taken": 15,
    "average_score": 3.8,
    "best_score": 5,
    "recent_attempts": [
      {
        "id": 15,
        "hazard_perception_video_id": 8,
        "hazard_1_response_time": "18.30",
        "hazard_1_score": 5,
        "hazard_2_response_time": null,
        "hazard_2_score": null,
        "total_score": 5,
        "completed_at": "2026-04-14T15:00:00+00:00"
      }
    ],
    "topic_performance": [
      {
        "topic": "Junctions",
        "total_attempts": 5,
        "average_score": 4.2
      },
      {
        "topic": "Roundabouts",
        "total_attempts": 3,
        "average_score": 3.0
      }
    ]
  }
}
```

**Summary Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `attempts_taken` | integer | Total completed attempts |
| `average_score` | number | Average score across all attempts |
| `best_score` | integer | Highest single attempt score |
| `recent_attempts` | array | Last 10 completed attempts (most recent first) |
| `topic_performance` | array | Per-topic breakdown with attempt count and average score |

---

### Student Activity Log

---

#### `GET /api/v1/student/activity-logs`

**Auth required:** Yes (Bearer token — student only)

Returns paginated activity log entries for the authenticated student, newest first. The student is resolved from the token — no ID in the URL.

Entries are written automatically by the backend (lesson events, bookings, payments, notes, messages, notifications, profile changes, etc.) — this endpoint is read-only.

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `category` | string | No | Filter by category. Common values: `lesson`, `booking`, `payment`, `note`, `message`, `notification`, `profile`. Pass `all` or omit for no filter. |
| `search` | string | No | Case-insensitive substring match on the `message` field. |
| `page` | integer | No | Page number (default `1`). Laravel standard pagination. |
| `per_page` | integer | No | Items per page (default `20`). |

**Request Body:** None

**Success Response:** `200 OK`
```json
{
  "data": [
    {
      "id": 482,
      "category": "payment",
      "message": "Paid £199.99 for 5-hour lesson package",
      "metadata": {
        "invoice_url": "https://invoice.stripe.com/i/acct_xxx/test_xxx",
        "amount_pence": 19999,
        "order_id": 37
      },
      "created_at": "2026-04-20T14:12:05+00:00"
    },
    {
      "id": 481,
      "category": "lesson",
      "message": "Lesson completed with John Smith",
      "metadata": {
        "lesson_id": 204,
        "duration_minutes": 60
      },
      "created_at": "2026-04-20T10:45:00+00:00"
    },
    {
      "id": 480,
      "category": "note",
      "message": "Instructor added a note",
      "metadata": null,
      "created_at": "2026-04-19T16:30:12+00:00"
    }
  ],
  "links": {
    "first": "https://drivecrm.test/api/v1/student/activity-logs?page=1",
    "last": "https://drivecrm.test/api/v1/student/activity-logs?page=8",
    "prev": null,
    "next": "https://drivecrm.test/api/v1/student/activity-logs?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 8,
    "path": "https://drivecrm.test/api/v1/student/activity-logs",
    "per_page": 20,
    "to": 20,
    "total": 156
  }
}
```

**Activity Log Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Activity log record ID |
| `category` | string | Category bucket (e.g. `lesson`, `booking`, `payment`, `note`, `message`, `notification`, `profile`). Use for grouping / filtering in the UI. |
| `message` | string | Human-readable description of the event. |
| `metadata` | object\|null | Extra context keyed per category (e.g. `invoice_url`, `lesson_id`, `amount_pence`). Shape is not fixed — treat as opaque and check for keys you need. |
| `created_at` | string | ISO 8601 timestamp of when the event was logged. |

> **Pagination:** Response follows Laravel's standard `AnonymousResourceCollection` format (`data`, `links`, `meta`). Use `meta.current_page` / `meta.last_page` to drive load-more.

---

## Progress Tracker

Each instructor owns a personal framework of driving-skill categories and subcategories (seeded from `config/progress_tracker.php` on instructor creation). Instructors score their students 1–5 on each subcategory. Scores overwrite in place (no history kept). Soft-deleted subcategories are hidden from new scoring but still returned in the payload (with `archived: true`) if the student has an existing score against them, so the app can render past progress read-only.

The 1–5 scale labels are returned in the top-level `score_labels` field on every response:

```json
{
  "1": "Introduced",
  "2": "Instructed",
  "3": "Prompted",
  "4": "Seldom prompted",
  "5": "Independent"
}
```

### GET `/api/v1/student/progress`

Returns the authenticated student's progress across their instructor's framework.

**Auth:** Bearer token (student role). Fails with 404 if the authenticated user has no student profile.

**Response 200:**

```json
{
  "data": [
    {
      "id": 3,
      "name": "Junctions",
      "sort_order": 2,
      "subcategories": [
        { "id": 17, "name": "Left Turn", "sort_order": 0, "score": 4, "archived": false },
        { "id": 18, "name": "Right Turn", "sort_order": 1, "score": 3, "archived": false },
        { "id": 19, "name": "Emerging", "sort_order": 2, "score": null, "archived": false }
      ]
    }
  ],
  "score_labels": { "1": "Introduced", "2": "Instructed", "3": "Prompted", "4": "Seldom prompted", "5": "Independent" }
}
```

`score` is `null` when the student has not yet been scored on that subcategory.

### GET `/api/v1/instructor/students/{student}/progress`

Returns a specific student's progress for the authenticated instructor.

**Auth:** Bearer token (instructor role). Returns 403 if the target student is not taught by this instructor.

**Response:** Identical shape to `GET /api/v1/student/progress`.

### POST `/api/v1/instructor/students/{student}/progress`

Bulk-upserts scores for a student. One request per save click (payload holds every changed score at once).

**Auth:** Bearer token (instructor role). Returns 403 if the target student is not taught by this instructor.

**Request body:**

```json
{
  "scores": [
    { "progress_subcategory_id": 17, "score": 4 },
    { "progress_subcategory_id": 18, "score": 3 }
  ]
}
```

**Validation:**
- `scores` — required array, min 1 entry.
- `scores.*.progress_subcategory_id` — required, must exist in `progress_subcategories`.
- `scores.*.score` — required integer between 1 and 5.

**Silent filtering:** Entries whose subcategory is soft-deleted or belongs to a different instructor are silently skipped (not an error). The mobile app shouldn't attempt to POST archived items, but the server defends either way.

**Response 200:** Same shape as `GET /api/v1/instructor/students/{student}/progress` — returns the full refreshed progress payload so the app can replace its local state without a second round-trip.

---

## Changelog

| Date | Change | Endpoints Affected |
|------|--------|--------------------|
| 2026-03-14 | Initial API documentation created | Auth (login, logout, user) |
| 2026-03-15 | Added student and instructor registration endpoints | Auth (register/student, register/instructor) |
| 2026-03-17 | Added `profile` object to all user responses (role-specific data) | Auth (login, user, register/student, register/instructor) |
| 2026-03-17 | Login now requires `role` field; rejects role mismatches | Auth (login) |
| 2026-03-17 | Added grouped students endpoint for instructors | Instructor (students) |
| 2026-03-17 | Added individual student record endpoint with access policy | Student (show) |
| 2026-03-17 | Added student lessons list and lesson detail endpoints with access policy | Student (lessons index, lessons show) |
| 2026-03-17 | Added card_status, has_reflective_log, resources_count, payment_status to lesson list | Student (lessons index) |
| 2026-03-17 | Added card_status, reflective_log, resources, has_reflective_log to lesson detail | Student (lessons show) |
| 2026-03-17 | Fixed authorize bug in StudentLessonController (Gate::authorize) | Student (lessons index, lessons show) |
| 2026-03-19 | Added student create, update, and delete endpoints with policy enforcement | Student (store, update, destroy) |
| 2026-03-23 | Full API documentation audit — added all missing endpoints and fixed broken sections | All endpoints |
| 2026-03-23 | Student creation now creates a User account with temp password and sends welcome email; email field now required and unique | Student (store) |
| 2026-03-23 | DELETE student endpoint changed from hard delete (204) to soft remove (200) — sets instructor_id to null, preserves student data | Student (destroy) |
| 2026-03-24 | Added stub endpoints for instructor on-way and arrived notifications (activity log only, push TBD) | Instructor (notify-on-way, notify-arrived) |
| 2026-03-24 | Added update and delete endpoints for student notes (PUT and DELETE with soft delete) | Student Notes (update, destroy) |
| 2026-03-24 | Added calendar management API — GET (with available_only filter), POST (create with all options: travel, recurrence, practical test), DELETE (single or future recurring) | Instructor Calendar (index, store, destroy) |
| 2026-03-24 | Added package pricing endpoint — returns full fee breakdown (booking fee, digital fee per lesson, promo discounts, totals) as raw numeric values for mobile consumption | Package Pricing (show) |
| 2026-03-24 | Fixed Stripe charge amount — now includes booking fee (£19.99) + digital fees (£3.99 × lessons) in the total sent to Stripe. Added `booking_fee_pence`, `digital_fee_pence`, `total_price_pence` to order response. | Orders (store), Checkout |
| 2026-03-25 | Extended messages API for student mobile app — added `GET conversations/instructor` (auto-resolves instructor from student record), made `recipient_id` optional for students on `POST /messages` (auto-resolves to assigned instructor) | Messages (conversations/instructor, store) |
| 2026-03-30 | Added confirmation email for weekly and upfront API orders — weekly orders send email on creation, upfront orders send email after checkout verification. Matches web onboarding behaviour. Documented weekly payment flow in Orders endpoint. | Orders (store), Checkout (verify) |
| 2026-03-30 | Added mileage update endpoint for instructors — PATCH to record miles driven per lesson | Instructor Lessons (mileage) |
| 2026-03-31 | Added delete and set-default endpoints for student pickup points — reuses existing web Actions | Pickup Points (destroy, default) |
| 2026-03-31 | Added create and update endpoints for instructor packages — reuses existing CreateInstructorPackageAction and UpdatePackageAction, with ownership check on update | Instructor Packages (store, update) |
| 2026-03-31 | Added instructor finances API — CRUD endpoints for recording payments and expenses, with recurring support (weekly/monthly/yearly) | Instructor Finances (index, store, update, destroy) |
| 2026-03-31 | Added `status` as an updatable field on PUT students endpoint — accepts: active, inactive, on_hold, passed, failed, completed | Student (update) |
| 2026-04-06 | Added `password_change_required` field to users table and all user responses. Added `POST /api/v1/auth/change-password` endpoint for forced password change flow. Flag is set when temporary passwords are issued (instructor-created pupils, onboarding, admin resets) and cleared on password change. | Auth (login, user, change-password), User responses |
| 2026-03-31 | Upfront payment no longer returns `checkout_url` — instead emails the Stripe payment link to the student (or contact person). API response confirms email was sent. | Orders (store) |
| 2026-04-06 | Added student-to-instructor attach endpoint — student submits instructor PIN to link themselves. Requires `pin` column on instructors table (migration included). | Students (attach) |
| 2026-04-07 | Added student-scoped booking endpoints — `GET /student/packages` and `GET /student/calendar/items` expose the attached instructor's packages and available slots so the mobile app can render the student booking sheet. Student calendar endpoint always filters to available, non-draft slots. | Student Booking (packages, calendar/items) |
| 2026-04-07 | Upfront order creation now returns `checkout_url` when the request is student-initiated (mobile app loads it in an in-app browser) instead of emailing the payment link. Instructor-initiated upfront orders still email the link as before. | Orders (store) |
| 2026-04-08 | `POST /api/v1/students/attach` now returns a JSON object containing a randomly selected thank-you message (1 of 5) and the attached instructor's `id`, `name`, and `avatar` URL — replaces the previous `true` response. | Students (attach) |
| 2026-04-10 | Enhanced student lessons endpoint with query parameters: `status`, `from_date`, `sort`, `limit` for filtering/sorting/limiting. Added `instructor_avatar` field to lesson list responses. | Student Lessons (index) |
| 2026-04-10 | Added `GET /student/instructor` — returns attached instructor's public profile (name, bio, avatar). Returns 422 if no instructor attached. | Student Home (instructor) |
| 2026-04-10 | Added `GET /student/dashboard` — returns aggregated student dashboard data. Currently includes practice hours (completed vs total, derived from lesson durations). Designed to be extended with future sections. | Student Home (dashboard) |
| 2026-04-14 | Documented `POST /api/v1/push-token` endpoint — stores Expo push token on user record for push notification delivery. Accepts `expo_push_token` (must match `ExponentPushToken[...]` format). | Push Notifications (push-token) |
| 2026-04-14 | Added student resources API — `GET /student/resources` returns full folder tree with published resources (annotated with `is_suggested` and `is_watched` booleans) plus a flat `my_resources` array derived from `lesson_resource` pivot. `GET /student/resources/{resource}` returns single resource with video_url or signed S3 file_url. `POST /student/resources/{resource}/watched` marks a resource as watched (idempotent). New `resource_watches` table tracks watched state. | Student Resources (index, show, watched) |
| 2026-04-14 | Added `GET /student/resource-summary` — aggregated dashboard for Resources tab. Returns recent activity (last 10 watched), stats (total/watched counts, hardcoded mock test & hazard perception scores), per-folder study progress, recommended resources (from lesson sign-offs, unwatched first), and a random study tip from 20 seeded tips. | Student Resources (resource-summary) |
| 2026-04-14 | Extended `GET /student/dashboard` — now includes `suggested_resources` array alongside `practice_hours`. Uses existing `getMyResources()` from ResourceApiService to return resources suggested via lesson sign-offs, each with `is_watched` status. | Student Home (dashboard) |
| 2026-04-14 | Added hazard perception system — `GET /student/hazard-perception/videos` returns all clips grouped by category/topic. `POST /student/hazard-perception/videos/{id}/submit` records student response times and calculates 5-band scores per hazard. `GET /student/hazard-perception/summary` returns performance stats (attempts taken, avg/best score, per-topic breakdown). Supports double hazard clips (max 10 points). New tables: `hazard_perception_videos`, `hazard_perception_attempts`. | Hazard Perception (videos, submit, summary) |
| 2026-04-14 | Added mock test system — `GET /student/mock-tests/summary` returns dashboard stats (tests taken, avg score, pass count, last 5 scores, random tip, per-category performance). `POST /student/mock-tests/start` generates 50 random questions. `POST /student/mock-tests/{id}/submit` scores and records all answers. `GET /student/mock-tests/{id}/review` returns full test review with correct answers and explanations. New tables: `mock_test_questions` (2,923 questions), `mock_tests`, `mock_test_answers`. | Mock Tests (summary, start, submit, review) |
| 2026-04-21 | Added student activity log API — `GET /api/v1/student/activity-logs` exposes the existing `activity_logs` table for the mobile app. Student is resolved from the token (no ID in URL). Supports `category` / `search` / `page` / `per_page` query params. Read-only — activity entries are still written exclusively by the backend via `LogActivityAction`. | Student Activity Log (index) |
| 2026-04-21 | Extended `GET /student/resource-summary` — replaced hardcoded mock-test/hazard stats with live aggregates; split `total_resources` into `total_videos` + `total_files`; added `mock_tests_taken` and `hazard_attempts_taken`; normalised hazard average to a /5 scale (double-hazard scores halved before averaging); added `badges` object with earned/locked state + progress for First Test, Top Score, 7 Day Streak, and Expert. Streak badge, once earned, stays earned regardless of later gaps. | Student Resources (resource-summary) |
| 2026-04-22 | Added `audience` flag to resources (`student` or `instructor`). `GET /api/v1/resources` accepts `?audience=student\|instructor` (omit for all) and returns `audience` on every resource. All `/api/v1/student/...` resource endpoints are hard-filtered server-side to `audience=student` (folder tree, single resource, my_resources, random fallback, summary stats, Expert badge denominator). Admin upload/edit requires an audience; CSV import template gains an `audience` column. | Resources (index), Student Resources (index, show, summary) |
| 2026-04-22 | Added `GET /api/v1/resources/{resource}` — instructor-accessible single-resource endpoint. Reuses the 30-minute signed S3 URL logic from `GET /api/v1/student/resources/{resource}` but is not student-scoped: no `is_watched` / `is_suggested` fields, no policy restricting to students. 404 on unpublished resources. | Resources (show) |
| 2026-04-22 | Added progress-tracker API — instructors score their students 1–5 on driving-skill subcategories (framework is per-instructor, editable via admin). `GET /api/v1/student/progress` returns own scores; `GET /api/v1/instructor/students/{student}/progress` returns a specific student's scores; `POST` of the same URL bulk-upserts scores (scores overwrite — no history). Soft-deleted subcategories with existing scores are returned with `archived: true` for read-only display. New tables: `progress_categories`, `progress_subcategories`, `student_progress`. | Progress Tracker (student/progress, instructor/students/.../progress) |
| 2026-04-24 | Extended instructor finances API with `category` (type-gated, config-backed), `payment_method`, and receipt attachment. Added `GET /finances/config` (dropdown options for the app to cache), `GET /finances/summary` (overview with stats + full-range finances & mileage for a date range, default last 30 days), `GET /finances/{finance}` (single-record detail), `POST`/`DELETE /finances/{finance}/receipt` (multipart receipt upload + removal on private S3, 20-min signed URLs). List endpoint is now cursor-paginated with `type`/`from`/`to`/`per_page` filters. Added full instructor mileage API (`GET/POST /mileage`, `GET/PUT/DELETE /mileage/{mileageLog}`) — mileage is an independent ledger from finances (not linked to fuel expenses). | Instructor Finances (all), Instructor Mileage (all) |
| 2026-04-27 | `unavailability_reason` on calendar items is now fully optional — instructors can save an unavailable diary entry without entering a reason. Field still accepts up to 500 chars when supplied. | Instructor Calendar (store, update) |
| 2026-04-27 | Weekly-payment invoice + email is now event-driven: the first invoice is issued immediately at booking, and each subsequent invoice is issued immediately when the previous lesson is signed off. The hourly `lessons:send-invoices` cron has been unscheduled — the command is retained as a manual fallback only. No request/response shape changed; only documented side-effects updated on `POST /students/{student}/lessons/{lesson}/sign-off` and the weekly-payment booking flow. | Booking Flow (weekly), Lesson Sign-Off |
| 2026-04-27 | Push notifications are now queued additively alongside existing emails for two events: (1) new in-app messages — push is queued for the recipient when they have a registered Expo token; (2) weekly-payment reminders — push is queued for the student's user on every event-driven send (booking-time, on prior-lesson sign-off, and the manual fallback command), again only when an Expo token is present. Email + in-app delivery is unchanged and unaffected by push outcome. No request/response shape changes; documented in the Push Notifications section. | Push Notifications, Booking Flow (weekly), Lesson Sign-Off, Messages (any send) |
| 2026-04-27 | Widened the allowed diary time window from `08:00`–`18:00` to `06:00`–`21:00`. `start_time` must now be ≥ `06:00` and `end_time` ≤ `21:00` on `POST /api/v1/instructor/calendar/items` (and the matching web Form Requests). Bounds are sourced from `config/diary.php`; frontend mirror is `resources/js/lib/diary-hours.ts`. | Instructor Calendar (store) |
| 2026-04-28 | Added pickup-point update endpoint — closes the last missing CRUD on student pickup points. Reuses existing `UpdatePickupPointAction`, `UpdatePickupPointRequest`, `StudentPickupPointResource`, and the dual-role `PickupPointPolicy`. Postcode is re-geocoded only when it changes; setting `is_default: true` unsets any other default for the student. Response shape matches POST exactly. | Pickup Points (update) |
| 2026-04-28 | Added `student_lesson_number` field to lesson responses — a per-student running lesson number (starts at 1, increments across all the student's orders, immutable after assignment). Now exposed on `GET /api/v1/students/{student}/lessons`, `GET /api/v1/students/{student}/lessons/{lesson}`, and `GET /api/v1/instructor/lessons/{date}`. The internal `id` is retained for routing/internal references; `student_lesson_number` is the user-facing reference for support queries. Backed by a new `lessons.student_lesson_number` column populated via backfill migration. | Student Lessons (index, show), Instructor Lessons (day) |
| 2026-04-28 | Added `pin` field to the instructor profile object — surfaces the instructor's attach PIN (the same PIN students enter on `POST /api/v1/students/attach`) so the mobile app can display it after the instructor logs in. Returned on every endpoint that already returns the instructor profile object: login, `/auth/user`, instructor registration, `PUT /instructor/profile`, and the profile-picture upload/delete endpoints. | Auth (login, user, register/instructor), Instructor (profile, profile/picture) |

---

> **IMPORTANT FOR DEVELOPERS:** This file is the single source of truth for the mobile API. Every new API feature MUST be documented here before it is considered complete. If the endpoint isn't in this file, it doesn't exist for the mobile app.
