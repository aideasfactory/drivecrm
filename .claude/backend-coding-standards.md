# Agent Guide: Drive CRM App (Laravel Root)

## 1. Project Snapshot
- **Type**: Laravel Monolith with Inertia.js/Vue 3.
- **Stack**: PHP 8.4, Laravel 12, Pest v4, MySQL.
- **Frontend**: See [resources/js/AGENTS.md](resources/js/AGENTS.md).
- **Docs**: Uses `CLAUDE.md` for strict coding rules.

## 2. Root Setup Commands
```bash
# Install
composer install
npm install
cp .env.example .env && php artisan key:generate

# Run
npm run dev      # Frontend HMR
php artisan serve # Backend (or use Herd)

# Verification
./vendor/bin/pint # Fix PHP style
```

## 3. Universal Conventions
- **Code Style**: Follow Laravel Pint (PHP) and Prettier (JS/TS).
- **Commits**: Conventional Commits (e.g., `feat: add user login`, `fix: typo in model`).
- **Strictness**: PHP strict types `declare(strict_types=1);` required in new files.

## 4. Security & Secrets
- **Secrets**: NEVER commit `.env` or keys.
- **Access**: Use `config('app.name')`, NOT `env('APP_NAME')`.
- **Auth**: Use Laravel Fortify + Sanctum; do not roll custom auth logic.
- **API Auth**: All API routes MUST use `auth:sanctum` middleware.
- **Tokens**: API tokens issued via Sanctum `createToken()`. Never store plain-text tokens.

## 5. JIT Index - Directory Map

### Primary Contexts
- **Frontend (Vue/Inertia)**: `resources/js/` → [see resources/js/AGENTS.md](resources/js/AGENTS.md)
- **Backend Logic**: `app/` → **See "Backend Patterns" below**
- **Config**: `config/` → Laravel configuration.

### Quick Find Commands
- Find Artisan command: `php artisan list | grep "make:"`
- Find Route: `php artisan route:list --path="api"`
- Find Model: `find app/Models -name "*.php"`
- Find Controller: `find app/Http/Controllers -name "*Controller.php"`

## 6. Backend Patterns (Laravel)

### 🎯 Architecture: Controller -> Service -> Action Pattern

**STRICT HIERARCHY:**
```
Controller → Service → Action(s)
   ↓           ↓          ↓
  HTTP    Orchestration  Logic
```

### 📁 Action Organization (MANDATORY)

**✅ DO: Organize Actions by Domain**
```
app/Actions/
├── Instructor/
│   ├── GetInstructorPackagesAction.php
│   ├── CreateInstructorAction.php
│   └── UpdateInstructorAvailabilityAction.php
├── Student/
│   ├── EnrollStudentAction.php
│   └── CalculateStudentProgressAction.php
├── Package/
│   └── CreateBespokePackageAction.php
└── Shared/
    ├── FetchPostcodeCoordinatesAction.php
    └── SendNotificationAction.php
```

**❌ DON'T: Put Domain Actions in Root**
```
app/Actions/
├── GetInstructorPackagesAction.php  ❌ Wrong!
└── CreateInstructorAction.php       ❌ Wrong!
```

**Rules:**
1. **Domain Actions**: Place in `app/Actions/{Domain}/` (e.g., `Instructor/`, `Student/`)
2. **Shared Actions**: Place in `app/Actions/Shared/` if used across multiple domains
3. **Namespace**: Must match folder structure (e.g., `App\Actions\Instructor`)

### 🏗️ Pattern Implementation

**1. Actions (Single Responsibility)**
- ✅ Atomic, reusable business logic
- ✅ No HTTP concerns (no Request, Response, redirect)
- ✅ Invokable class with `__invoke()` method
- ✅ Type-hinted parameters and return types
- ✅ Organized by domain in subfolders

**Example:**
```php
<?php

namespace App\Actions\Instructor;

use App\Models\Instructor;
use Illuminate\Support\Collection;

class GetInstructorPackagesAction
{
    public function __invoke(Instructor $instructor, bool $onlyActive = true): Collection
    {
        // Pure business logic - no HTTP, no redirects
        return Package::where('instructor_id', $instructor->id)
            ->when($onlyActive, fn($q) => $q->where('active', true))
            ->get();
    }
}
```

**2. Services (Orchestration)**
- ✅ Inject Actions via constructor
- ✅ Orchestrate multiple Actions
- ✅ Handle transactions & caching
- ✅ Invoke Actions using: `($this->actionName)($params)`
- ✅ Return domain data (Collections, Models, DTOs)

**Example:**
```php
<?php

namespace App\Services;

use App\Actions\Instructor\GetInstructorPackagesAction;
use App\Actions\Instructor\CreateInstructorAction;

class InstructorService
{
    public function __construct(
        protected GetInstructorPackagesAction $getInstructorPackages,
        protected CreateInstructorAction $createInstructor
    ) {}

    public function getPackages(Instructor $instructor): Collection
    {
        return ($this->getInstructorPackages)($instructor);
    }
}
```

**3. Controllers (HTTP Layer)**
- ✅ Inject Service via constructor
- ✅ Handle HTTP requests/responses only
- ✅ Use FormRequests for validation
- ✅ Keep methods under 20 lines
- ✅ No business logic - delegate to Service

**Example:**
```php
<?php

namespace App\Http\Controllers;

use App\Services\InstructorService;
use Illuminate\Http\JsonResponse;

class InstructorController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    public function packages(Instructor $instructor): JsonResponse
    {
        $packages = $this->instructorService->getPackages($instructor);

        return response()->json(['packages' => $packages]);
    }
}
```

### 🚨 Pattern Violations

**DON'T:**
- ❌ Put business logic in Controllers (Web OR API)
- ❌ Make HTTP calls from Actions
- ❌ Query models directly in Controllers
- ❌ Skip Services and call Actions from Controllers
- ❌ Put Actions in root `app/Actions/` folder without domain organization
- ❌ Return raw models/arrays from API controllers — use Eloquent Resources
- ❌ Create separate Service classes for API — reuse existing Services
- ❌ Add HTTP/Inertia concerns to Services (they must stay transport-agnostic)

### 📋 Checklist for New Features

When adding a new feature:
1. [ ] Create Action in `app/Actions/{Domain}/`
2. [ ] Add Action to Service constructor
3. [ ] Create Service method that invokes Action
4. [ ] Inject Service into Controller
5. [ ] Controller calls Service method only

**Why This Pattern?**
- ✅ **Reusability**: Actions can be used in Web, API, CLI, Jobs
- ✅ **Testability**: Test Actions independently of HTTP
- ✅ **Maintainability**: Clear separation of concerns
- ✅ **Domain Organization**: Easy to find related functionality

---

---

### 🌐 API Development Rules (STRICT)

**CRITICAL: Read `.claude/api.md` before building ANY API feature.**

#### API Architecture

API routes follow the **same** Controller → Service → Action pattern. The only difference is the HTTP layer:

```
API Controller → Service → Action(s) → Eloquent Resource (response)
     ↓              ↓          ↓              ↓
  JSON only    Orchestration  Logic     Formatted output
```

#### 🚨 MANDATORY Rules for API Features

1. **Separate API Controllers**: API controllers live in `app/Http/Controllers/Api/V1/`
   - Namespace: `App\Http\Controllers\Api\V1`
   - Never mix Inertia and API responses in the same controller
2. **Eloquent API Resources**: ALL API JSON responses MUST use Eloquent Resources
   - Resources live in `app/Http/Resources/V1/`
   - Namespace: `App\Http\Resources\V1`
   - NEVER return raw models or arrays from API controllers — always wrap in a Resource
3. **FormRequest validation**: Same rule as web — ALL validation in FormRequest classes
   - API FormRequests live in `app/Http/Requests/Api/V1/`
4. **Sanctum auth**: ALL API routes use `auth:sanctum` middleware (except login/register)
5. **Versioned routes**: All API routes prefixed with `/api/v1/`
6. **Services are shared**: API and Web controllers share the SAME Service classes
   - Services contain zero HTTP concerns — this is what makes them reusable
   - If a Service returns Inertia responses, it's WRONG — refactor it
7. **Actions are shared**: Same Actions used by web and API — no duplication

#### ❌ API Pattern Violations

- ❌ Returning `response()->json($model)` without a Resource
- ❌ Putting API logic in web controllers
- ❌ Creating duplicate Services for API vs Web
- ❌ Skipping FormRequest validation in API controllers
- ❌ Using session-based auth for API routes
- ❌ Returning Inertia responses from API controllers
- ❌ Hardcoding response structures instead of using Resources

#### 📁 API File Structure

```
app/Http/Controllers/Api/V1/
├── AuthController.php          (login, register, logout, user)
├── InstructorController.php
├── StudentController.php
├── PackageController.php
└── ...

app/Http/Resources/V1/
├── UserResource.php
├── InstructorResource.php
├── StudentResource.php
├── PackageResource.php
└── ...

app/Http/Requests/Api/V1/
├── LoginRequest.php
├── ...

routes/
├── api.php                     (all API routes, versioned)
```

#### 📋 Checklist for New API Features

When adding a new API endpoint:
1. [ ] Create/reuse Action in `app/Actions/{Domain}/`
2. [ ] Create/reuse Service method (shared with web)
3. [ ] Create API Controller in `app/Http/Controllers/Api/V1/`
4. [ ] Create Eloquent Resource in `app/Http/Resources/V1/`
5. [ ] Create FormRequest in `app/Http/Requests/Api/V1/` (if POST/PUT/PATCH)
6. [ ] Add route to `routes/api.php` with `auth:sanctum` middleware
7. [ ] **Update `.claude/api.md`** with endpoint documentation
8. [ ] Include request body, response example, and auth requirements

**Rule: No API feature is complete until `api.md` is updated.**

---

### Other Backend Standards

- **Caching**: All Service reads must use `BaseService::remember()`. Writes must `invalidate()`.
- **Models**: Use `app/Models`. Always add `casts()` method.
- **DB**: Prefer Eloquent relationships over `DB::table`.
- **API**: Use Eloquent Resources for JSON responses.

## 7. Database Structure & Relationships
**Structure mysql**: → [see DATABASE_SCHEMA.md](database-schema.md)

### 🚨 CRITICAL: Migration Documentation Rule

**After creating or updating ANY migration file:**
1. **MUST immediately update `.claude/database-schema.md`**
2. Document the new/changed table structure
3. Update relationships section if applicable
4. Update ERD diagram if structure changed

**Example workflow:**
```bash
# 1. Create migration
php artisan make:migration create_bookings_table

# 2. Write migration code
# 3. Update .claude/database-schema.md immediately
# 4. Announce: "Migration created and database-schema.md updated"
```

**This is NON-NEGOTIABLE.** Database schema documentation must stay in sync with migrations.

### Technology Stack
- **Database**: MYSQL
- **Storage**: S3 (original files + extracted text)
- **Queue**: SQS

## 8. Forbidden Commands & Rules

### 🚫 NEVER Run These Commands

**Absolutely forbidden:**
- ❌ `php artisan test` - User runs tests manually
- ❌ `./vendor/bin/pint` - User handles code style
- ❌ `npm run lint` - User handles linting
- ❌ `prettier`, `eslint` - User handles formatting

**Why:** User prefers to control when these run. Focus on implementation only.

**Always acknowledge:** "I understand I must not run tests or linting commands."

## Lastly
- Always explain the changes you are going to make
- Always give a summary of the changes and include any potential overhead or anti-patterns that have been used. Do NOT implement them just provide the summary
- Always finish with a score out of 10 of the solution implemented