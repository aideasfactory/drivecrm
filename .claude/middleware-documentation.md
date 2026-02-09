# Middleware Documentation

This document provides information about existing middleware and patterns for implementing role-based access control in the future.

---

## 📋 Existing Middleware

### Location
All middleware files are located in: `app/Http/Middleware/`

### Current Middleware Files

#### 1. HandleInertiaRequests.php
**Purpose:** Shares common data with all Inertia pages

**Key Functionality:**
- Shares `auth.user` data with frontend
- Shares `name` (app name) from config
- Shares `sidebarOpen` state

**Shared Props:**
```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'auth' => [
            'user' => $request->user(),
        ],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') ||
                        $request->cookie('sidebar_state') === 'true',
    ];
}
```

**Note:** User role is automatically included because the User model casts the `role` field to `UserRole` enum, which serializes to JSON as a string value.

---

#### 2. HandleAppearance.php
**Purpose:** Manages user appearance/theme preferences

---

#### 3. ValidateEnquiryUuid.php
**Purpose:** Validates enquiry UUID for onboarding flow

**Usage:** Applied to onboarding routes to ensure valid enquiry IDs

---

#### 4. ValidateStepAccess.php
**Purpose:** Validates that users can only access onboarding steps they've completed

**Usage:** Applied to onboarding routes to prevent skipping steps

---

## 🔒 Role-Based Access Control (Future Implementation)

### User Roles
The application supports three user roles defined in `app/Enums/UserRole.php`:

```php
enum UserRole: string
{
    case OWNER = 'owner';
    case INSTRUCTOR = 'instructor';
    case STUDENT = 'student';
}
```

### User Model Helper Methods
The `User` model provides convenient role-checking methods:

```php
// Check if user is owner
$user->isOwner(); // Returns bool

// Check if user is instructor
$user->isInstructor(); // Returns bool

// Check if user is student
$user->isStudent(); // Returns bool

// Access role directly
$user->role; // Returns UserRole enum instance
$user->role->value; // Returns string ('owner', 'instructor', 'student')
```

---

## 🛠️ How to Implement Role-Based Middleware

When you're ready to implement role-based restrictions on routes, follow this pattern:

### Step 1: Create Role Middleware

Create middleware files in `app/Http/Middleware/`:

**Example: OwnerMiddleware.php**
```php
<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isOwner()) {
            abort(403, 'Access denied. Owner role required.');
        }

        return $next($request);
    }
}
```

**Example: InstructorMiddleware.php**
```php
<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsInstructor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->isInstructor()) {
            abort(403, 'Access denied. Instructor role required.');
        }

        return $next($request);
    }
}
```

**Example: Generic Role Middleware (Flexible Approach)**
```php
<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  array<UserRole>  $roles  Allowed roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            abort(403, 'Authentication required.');
        }

        $userRole = $request->user()->role->value;

        if (!in_array($userRole, $roles)) {
            abort(403, 'Access denied. Required role: ' . implode(' or ', $roles));
        }

        return $next($request);
    }
}
```

---

### Step 2: Register Middleware

Register the middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'owner' => \App\Http\Middleware\EnsureUserIsOwner::class,
        'instructor' => \App\Http\Middleware\EnsureUserIsInstructor::class,
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
    ]);
})
```

---

### Step 3: Apply Middleware to Routes

**Option 1: Using Named Middleware**
```php
// Owner-only routes
Route::middleware(['auth', 'owner'])->group(function () {
    Route::get('/instructors', [InstructorController::class, 'index'])
        ->name('instructors.index');
});

// Instructor-only routes
Route::middleware(['auth', 'instructor'])->group(function () {
    Route::get('/my-schedule', [ScheduleController::class, 'index'])
        ->name('instructor.schedule');
});
```

**Option 2: Using Generic Role Middleware**
```php
// Single role
Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/instructors', [InstructorController::class, 'index'])
        ->name('instructors.index');
});

// Multiple roles (owner OR instructor)
Route::middleware(['auth', 'role:owner,instructor'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');
});
```

**Option 3: Multiple Middleware Groups**
```php
// Owner routes
Route::middleware(['auth', 'owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index']);
        Route::resource('instructors', InstructorManagementController::class);
    });

// Instructor routes
Route::middleware(['auth', 'instructor'])
    ->prefix('instructor')
    ->name('instructor.')
    ->group(function () {
        Route::get('/dashboard', [InstructorDashboardController::class, 'index']);
        Route::resource('packages', PackageController::class);
    });

// Student routes
Route::middleware(['auth', 'student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/packages', [StudentPackageController::class, 'index']);
        Route::post('/checkout', [CheckoutController::class, 'create']);
    });
```

---

## 🎯 Current Routes Requiring Protection

Based on the current navigation structure, here are the routes that will need middleware:

### Routes Requiring Owner Role
```php
Route::middleware(['auth', 'owner'])->group(function () {
    Route::get('/instructors', [InstructorController::class, 'index'])
        ->name('instructors.index');
});
```

### Routes Available to All Authenticated Users
Currently, these routes are accessible to all authenticated users:
- `/pupils` - Pupils management
- `/teams` - Team management
- `/reports` - Reports and analytics
- `/resources` - Learning resources
- `/apps` - App integrations
- `/settings/profile` - User settings

**Future Consideration:** You may want to restrict some of these based on role. For example:
- Only owners and instructors should see Reports
- Only owners should manage Teams
- Resources might be visible to all but have role-specific content

---

## 📝 Frontend Role-Based UI (Current Implementation)

The frontend already implements role-based visibility using the `useRole()` composable:

### Usage in Components
```vue
<script setup>
import { useRole } from '@/composables/useRole';

const { role, isOwner, isInstructor, isStudent, hasRole } = useRole();
</script>

<template>
  <!-- Show only to owners -->
  <div v-if="isOwner">
    Owner-only content
  </div>

  <!-- Show to owners or instructors -->
  <div v-if="hasRole(['owner', 'instructor'])">
    Content for owners and instructors
  </div>

  <!-- Show based on computed role -->
  <div v-if="role === 'student'">
    Student content
  </div>
</template>
```

### Navigation Implementation
The `AppSidebar.vue` component already implements role-based filtering:

```typescript
const allNavItems: NavItem[] = [
    {
        title: 'Instructors',
        href: instructorsIndex(),
        icon: GraduationCap,
        roles: ['owner'], // Only visible to owners
    },
    // ... other items without role restrictions
];

// Automatically filters based on user role
const mainNavItems = computed(() =>
    allNavItems.filter((item) => canSeeNavItem(item.roles)),
);
```

---

## ⚠️ Important Security Notes

1. **Backend Protection is Essential**
   - Frontend role-based UI is for UX only (hiding links)
   - Backend middleware is required for true security
   - Never rely solely on frontend checks

2. **Defense in Depth**
   - Always apply middleware to routes requiring protection
   - Frontend checks improve UX by hiding unavailable options
   - Backend checks enforce actual security

3. **Testing Recommendations**
   - Test routes with different user roles
   - Attempt to access restricted URLs directly
   - Verify 403 responses for unauthorized access

---

## 🔗 Related Files

- User Model: `app/Models/User.php`
- UserRole Enum: `app/Enums/UserRole.php`
- Frontend Composable: `resources/js/composables/useRole.ts`
- Frontend Types: `resources/js/types/roles.ts`
- Routes: `routes/web.php`
- Migration: `database/migrations/*_create_users_table.php`
- Database Schema Docs: `.claude/database-schema.md`

---

## 📚 Next Steps

When you're ready to implement role-based middleware:

1. Create the middleware files using the examples above
2. Register middleware aliases in `bootstrap/app.php`
3. Apply middleware to routes in `routes/web.php`
4. Test with users of different roles
5. Update this documentation with any changes

---

**Last Updated:** 2026-02-09
**Status:** Ready for implementation when needed
