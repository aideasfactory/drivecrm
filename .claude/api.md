# Drive CRM — Mobile API Reference

> **Cheat sheet for mobile app developers.** Paste this into your mobile project for a complete reference of every API endpoint, expected request data, and response format.

---

## Table of Contents

- [Authentication Setup](#authentication-setup)
- [Making API Calls](#making-api-calls)
- [Error Handling](#error-handling)
- [Endpoints](#endpoints)
  - [Auth](#auth)

---

## Authentication Setup

### Stack

| Layer | Package | Purpose |
|-------|---------|---------|
| Token Auth | **Laravel Sanctum** | Issues and validates Bearer tokens for mobile |
| Auth Features | **Laravel Fortify** | Registration, login, password reset, 2FA |
| Guard | `auth:sanctum` | Protects all API routes |

### How It Works

1. Mobile app sends `email`, `password`, and `device_name` to `/api/v1/auth/login`
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
  -d '{"email": "user@example.com", "password": "secret", "device_name": "iPhone 15"}'

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
  "device_name": "iPhone 15 Pro"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `email` | string | Yes | User's email address |
| `password` | string | Yes | User's password |
| `device_name` | string | Yes | Human-readable device identifier (e.g., "John's iPhone 15") |

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
    "created_at": "2026-01-15T08:30:00.000000Z"
  }
}
```

**Error Response:** `422 Unprocessable Entity`
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
    "created_at": "2026-01-15T08:30:00.000000Z"
  }
}
```

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

---

> **IMPORTANT FOR DEVELOPERS:** This file is the single source of truth for the mobile API. Every new API feature MUST be documented here before it is considered complete. If the endpoint isn't in this file, it doesn't exist for the mobile app.
