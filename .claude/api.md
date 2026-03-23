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
    - [Packages](#get-apiv1instructorpackages)
  - [Students](#students)
    - [CRUD](#post-apiv1students)
    - [Lessons](#get-apiv1studentsstudentlessons)
    - [Lesson Detail](#get-apiv1studentsstudentlessonslesson)
    - [Lesson Sign-Off](#post-apiv1studentsstudentlessonslessonsign-off)
    - [Lesson Resources](#post-apiv1studentsstudentlessonslessonresources)
    - [Notes](#get-apiv1studentsstudentnotes)
    - [Checklist Items](#get-apiv1studentsstudentchecklist-items)
    - [Pickup Points](#get-apiv1studentsstudentpickup-points)
    - [Orders](#post-apiv1studentsstudentorders)
  - [Resources](#get-apiv1resources)
  - [Messages](#messages)
- [Profile Object by Role](#profile-object-by-role)
- [Appendix: User Roles](#appendix-user-roles)
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
    "email_verified_at": "2026-03-14T10:00:00.000000Z",
    "created_at": "2026-01-15T08:30:00.000000Z",
    "profile": {
      "id": 1,
      "bio": null,
      "transmission_type": "manual",
      "status": "active",
      "address": "1 High Street",
      "postcode": "TS7 0AB",
      "onboarding_complete": false,
      "charges_enabled": false,
      "payouts_enabled": false,
      "profile_picture_url": null
    }
  }
}
```

> **Note:** The `profile` object contains role-specific data. For `instructor` users it returns instructor fields; for `student` users it returns student fields. See [Profile Object by Role](#profile-object-by-role) below.

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
    "email_verified_at": "2026-03-14T10:00:00.000000Z",
    "created_at": "2026-01-15T08:30:00.000000Z",
    "profile": {
      "id": 1,
      "bio": null,
      "transmission_type": "manual",
      "status": "active",
      "address": "1 High Street",
      "postcode": "TS7 0AB",
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
    "email_verified_at": null,
    "created_at": "2026-03-15T12:05:00.000000Z",
    "profile": {
      "id": 1,
      "bio": null,
      "transmission_type": "manual",
      "status": null,
      "address": "1 High Street",
      "postcode": "TS7 0AB",
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
| `id` | integer | Lesson record ID |
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

### Students

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

Returns all lessons for a given student across all their orders. Sorted by date descending, then start time descending (most recent first).

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
    },
    {
      "id": 2,
      "order_id": 1,
      "instructor_name": "John Smith",
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
| `id` | integer | Lesson record ID |
| `order_id` | integer | The order this lesson belongs to |
| `instructor_name` | string\|null | Instructor's full name |
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
| `id` | integer | Lesson record ID |
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

> **Note:** Pickup points are ordered with the default point first, then alphabetically by label. If no pickup points exist, `data` will be an empty array.

**Error Response (not authorised):** `403 Forbidden`
**Error Response (not found):** `404 Not Found`

---

#### `POST /api/v1/students/{student}/orders`

**Auth required:** Yes (Bearer token — student or instructor)

Book lessons — creates an order, calendar items, and lessons. For `upfront` payment, initiates a Stripe Checkout session and returns a URL for the mobile app to open. For `weekly` payment, the order is activated immediately.

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

**Success Response (upfront payment):** `201 Created`
```json
{
  "message": "Order created. Complete payment to activate.",
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
    "status": "pending",
    "lessons_count": 10,
    "created_at": "2026-03-23T10:00:00.000000Z"
  },
  "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_abc123..."
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
| `package_total_price_pence` | integer | Total package price in pence |
| `package_lesson_price_pence` | integer | Per-lesson price in pence |
| `package_lessons_count` | integer | Number of lessons in the package |
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

> **Mobile App Flow (upfront payment):**
> 1. POST to create order → receive `checkout_url`
> 2. Open `checkout_url` in an in-app browser / WebView
> 3. After Stripe redirects back, call the verify endpoint to confirm payment
> 4. On success, the order becomes `active`

---

#### `GET /api/v1/orders/{order}/checkout/verify`

**Auth required:** Yes (Bearer token — student or instructor)

Verify a Stripe Checkout payment and activate the order. Call this after the user completes payment in the Stripe Checkout flow.

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

Returns all published learning resources. Resources can be videos or files (PDFs, documents, images, etc.).

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
      "title": "Parallel Parking Tutorial",
      "description": "Video tutorial on parallel parking technique",
      "tags": ["parking", "manoeuvres"],
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
| `resource_type` | string | Type: `video_link` or `file` |
| `video_url` | string\|null | Video URL (for `video_link` type) |
| `file_path` | string\|null | File storage path (for `file` type) |
| `file_name` | string\|null | Original file name |
| `file_size` | integer\|null | File size in bytes |
| `mime_type` | string\|null | MIME type (e.g., `application/pdf`, `image/png`) |
| `thumbnail_url` | string\|null | Thumbnail URL if available |

> **Note:** Only published resources are returned. Use `tags` for filtering/categorising in the mobile app UI.

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

#### `GET /api/v1/messages/conversations/{user}`

**Auth required:** Yes (Bearer token — instructor or student)

Returns paginated messages between the authenticated user and the specified user. Messages are ordered newest first for pagination (30 per page). Authorization requires an instructor-student relationship between the two users.

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

**Request Body:**
```json
{
  "recipient_id": 5,
  "message": "Great lesson today! Keep up the good work."
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `recipient_id` | integer | Yes | The recipient's user ID. Must exist in the users table. |
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
- `recipient_id.required`: "A recipient is required."
- `recipient_id.exists`: "The selected recipient does not exist."
- `message.required`: "A message is required."
- `message.max`: "The message must not exceed 5000 characters."

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
| GET | `/api/v1/auth/user` | Yes | Any | Get current user |
| PUT | `/api/v1/instructor/profile` | Yes | Instructor | Update profile |
| POST | `/api/v1/instructor/profile/picture` | Yes | Instructor | Upload profile picture |
| DELETE | `/api/v1/instructor/profile/picture` | Yes | Instructor | Delete profile picture |
| GET | `/api/v1/instructor/students` | Yes | Instructor | List students (grouped) |
| GET | `/api/v1/instructor/lessons/{date}` | Yes | Instructor | Day view lessons |
| GET | `/api/v1/instructor/packages` | Yes | Instructor | List packages |
| POST | `/api/v1/students` | Yes | Instructor | Create student |
| GET | `/api/v1/students/{student}` | Yes | Both | View student |
| PUT | `/api/v1/students/{student}` | Yes | Both | Update student |
| DELETE | `/api/v1/students/{student}` | Yes | Both | Remove student (soft) |
| GET | `/api/v1/students/{student}/lessons` | Yes | Both | List lessons |
| GET | `/api/v1/students/{student}/lessons/{lesson}` | Yes | Both | Lesson detail |
| POST | `/api/v1/students/{student}/lessons/{lesson}/sign-off` | Yes | Both | Sign off lesson |
| POST | `/api/v1/students/{student}/lessons/{lesson}/resources` | Yes | Instructor | Assign resources |
| GET | `/api/v1/students/{student}/notes` | Yes | Both | List notes |
| POST | `/api/v1/students/{student}/notes` | Yes | Both | Create note |
| GET | `/api/v1/students/{student}/checklist-items` | Yes | Both | List checklist |
| PUT | `/api/v1/students/{student}/checklist-items/{item}` | Yes | Both | Update checklist item |
| GET | `/api/v1/students/{student}/pickup-points` | Yes | Both | List pickup points |
| POST | `/api/v1/students/{student}/orders` | Yes | Both | Create order/booking |
| GET | `/api/v1/orders/{order}/checkout/verify` | Yes | Both | Verify payment |
| GET | `/api/v1/resources` | Yes | Any | List resources |
| GET | `/api/v1/messages/conversations` | Yes | Both | List conversations |
| GET | `/api/v1/messages/conversations/{user}` | Yes | Both | View conversation |
| POST | `/api/v1/messages` | Yes | Both | Send message |

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

---

> **IMPORTANT FOR DEVELOPERS:** This file is the single source of truth for the mobile API. Every new API feature MUST be documented here before it is considered complete. If the endpoint isn't in this file, it doesn't exist for the mobile app.
