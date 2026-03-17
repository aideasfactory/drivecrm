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
  - [Student](#student)

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
  "message": "No query results for model [App\\Models\\Instructor] 999."
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

### Auth

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
      "payouts_enabled": false
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
      "payouts_enabled": false
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
| `phone` | string | No | Student's phone number |
| `device_name` | string | Yes | Human-readable device identifier |

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
| `name` | string | Yes | Instructor's full name |
| `email` | string | Yes | Must be unique across all users |
| `password` | string | Yes | Must meet password policy (min 8 chars) |
| `password_confirmation` | string | Yes | Must match `password` |
| `phone` | string | No | Instructor's phone number |
| `postcode` | string | No | Business postcode (max 10 chars) |
| `address` | string | No | Business address |
| `transmission_type` | string | No | One of: `manual`, `automatic`, `both` |
| `device_name` | string | Yes | Human-readable device identifier |

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
      "payouts_enabled": false
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

### Student

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

> **Note:** The policy checks two conditions: (1) is the authenticated user the student themselves, or (2) is the authenticated user an instructor with this student assigned. All other access is denied with a 403.

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

## Changelog

| Date | Change | Endpoints Affected |
|------|--------|--------------------|
| 2026-03-14 | Initial API documentation created | Auth (login, logout, user) |
| 2026-03-15 | Added student and instructor registration endpoints | Auth (register/student, register/instructor) |
| 2026-03-17 | Added `profile` object to all user responses (role-specific data) | Auth (login, user, register/student, register/instructor) |
| 2026-03-17 | Login now requires `role` field; rejects role mismatches | Auth (login) |
| 2026-03-17 | Added grouped students endpoint for instructors | Instructor (students) |
| 2026-03-17 | Added individual student record endpoint with access policy | Student (show) |

---

> **IMPORTANT FOR DEVELOPERS:** This file is the single source of truth for the mobile API. Every new API feature MUST be documented here before it is considered complete. If the endpoint isn't in this file, it doesn't exist for the mobile app.
