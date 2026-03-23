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

### 🚨 API Identity Resolution (NON-NEGOTIABLE)

**The `ResolveApiProfile` middleware automatically resolves the authenticated user's role-specific profile (Instructor or Student) from their Bearer token on every authenticated API request.**

**CRITICAL RULES:**
1. **NEVER accept an instructor ID or student ID as a request parameter** for scoping the current user's data. The profile is ALWAYS derived from the token.
2. **NEVER trust client-sent IDs** to identify who the current user is. The mobile app does not send its own instructor/student ID — the backend resolves it.
3. **ALWAYS use `$request->user()->profile`** to get the current user's Instructor or Student model.
4. **ALWAYS use `$request->user()->instructor`** or **`$request->user()->student`** when you need the typed model.

**How it works:**
- Middleware: `App\Http\Middleware\ResolveApiProfile` (applied to all authenticated API routes)
- The middleware eager-loads the `instructor` or `student` relationship based on `User::role`
- `User::profile` accessor returns `$this->instructor ?? $this->student`

**Example — "Get instructor's students" endpoint:**
```php
// ✅ CORRECT: Derive instructor from the authenticated user
public function index(Request $request): StudentCollection
{
    $instructor = $request->user()->instructor;
    return new StudentCollection($this->studentService->getByInstructor($instructor));
}

// ❌ WRONG: Accept instructor ID from the client
public function index(Request $request, Instructor $instructor): StudentCollection
{
    return new StudentCollection($this->studentService->getByInstructor($instructor));
}
```

**When planning any API endpoint, assume:**
- "Get instructor's students" → scope by `$request->user()->instructor`
- "Get student's lessons" → scope by `$request->user()->student`
- "Update instructor profile" → target `$request->user()->instructor`
- The ID is NEVER in the URL or request body for the current user's own resources

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
- 🚨 **MUST extend `App\Services\BaseService`** — this is NON-NEGOTIABLE for ALL Service classes
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

class InstructorService extends BaseService
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
- ❌ **Create a Service class that does NOT extend `BaseService`** — EVERY Service MUST extend `App\Services\BaseService`, no exceptions
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
2. [ ] **Service MUST extend `BaseService`** — verify `extends BaseService` is present
3. [ ] Add Action to Service constructor
4. [ ] Create Service method that invokes Action
5. [ ] Inject Service into Controller
6. [ ] Controller calls Service method only

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
4. **Sanctum auth**: ALL API routes use `auth:sanctum` + `ResolveApiProfile` middleware (except login/register)
5. **Versioned routes**: All API routes prefixed with `/api/v1/`
6. **Services are shared**: API and Web controllers share the SAME Service classes
   - Services contain zero HTTP concerns — this is what makes them reusable
   - If a Service returns Inertia responses, it's WRONG — refactor it
7. **Actions are shared**: Same Actions used by web and API — no duplication
8. **Identity from token, not request**: NEVER accept instructor/student ID from the client to scope the current user's data — always derive from `$request->user()->profile` (see Section 4)

#### 🚨 REUSE-FIRST RULE (NON-NEGOTIABLE)

**Before creating ANY new Service or Action for an API endpoint, you MUST check if a web equivalent already exists.** API endpoints must reuse the same Actions and Services that power the web UI. Creating duplicate logic is a violation.

**How to apply:**
1. **Check the web controller first** — find the web route that performs the same operation and trace it to its Service/Action
2. **Reuse that exact Action** — the API controller calls the same Service method, which calls the same Action
3. **Never create a new Action** for an API endpoint if an existing Action already does the same thing for the web
4. **Never create a new Service** for an API endpoint — add a method to the existing domain Service instead
5. **If the existing Action returns web-specific data** (e.g., mapped arrays for Inertia), refactor it to return models and move the mapping to the web controller — Actions should return reusable data structures

**The test:** If you can describe what an API Action does and a web Action does using the same sentence, they should be the same Action.

**Examples of violations this rule prevents:**
- ❌ Creating `GetStudentNotesAction` (API) when `GetNotesAction` (shared) already exists
- ❌ Creating `CreateStudentNoteAction` (API) when `CreateNoteAction` (shared, with activity logging) already exists
- ❌ Creating `InstructorPackageService` with inline Eloquent when `GetInstructorPackagesAction` already exists
- ❌ Creating `DeleteStudentAction` (hard delete) for API when web uses `RemoveStudentFromInstructorAction` (soft remove)
- ❌ Creating `CreateStudentAction` (student-only) for API when web uses `CreatePupilAction` (user + student)

**If the web and API need different behaviour for the same concept, that is a design discussion — not a reason to silently create a parallel action.**

#### ❌ API Pattern Violations

- ❌ Returning `response()->json($model)` without a Resource
- ❌ Putting API logic in web controllers
- ❌ Creating duplicate Services for API vs Web
- ❌ Creating a new Action when a web Action already performs the same operation
- ❌ Creating a new Service class for an API domain when an existing Service covers that domain
- ❌ Implementing different behaviour (e.g., hard delete vs soft remove) without explicit approval
- ❌ Skipping FormRequest validation in API controllers
- ❌ Using session-based auth for API routes
- ❌ Returning Inertia responses from API controllers
- ❌ Hardcoding response structures instead of using Resources
- ❌ Accepting instructor/student ID from the client to scope the current user's own data
- ❌ Using route model binding for the current user's profile (e.g., `Route::get('instructors/{instructor}/students')` for "my students")

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
1. [ ] **FIRST: Find the web equivalent** — check web controllers for the same operation and identify which Service/Action it uses
2. [ ] **Reuse the existing Action** from `app/Actions/{Domain}/` — do NOT create a new one if a web Action already exists
3. [ ] **Reuse the existing Service method** (shared with web) — do NOT create a new Service class
4. [ ] Create API Controller in `app/Http/Controllers/Api/V1/`
5. [ ] Create Eloquent Resource in `app/Http/Resources/V1/`
6. [ ] Create FormRequest in `app/Http/Requests/Api/V1/` (if POST/PUT/PATCH)
7. [ ] Add route to `routes/api.php` inside the `auth:sanctum` + `ResolveApiProfile` group
8. [ ] **Scope by `$request->user()->profile`** — never accept the user's own ID from the client
9. [ ] **Update `.claude/api.md`** with endpoint documentation
10. [ ] Include request body, response example, and auth requirements

**Rule: No API feature is complete until `api.md` is updated.**
**Rule: No new Action or Service is justified until you've confirmed no web equivalent exists.**

---

### Other Backend Standards

- **Models**: Use `app/Models`. Always add `casts()` method.
- **DB**: Prefer Eloquent relationships over `DB::table`.
- **API**: Use Eloquent Resources for JSON responses.

---

### 🗄️ Caching Strategy (Service-Level)

**All caching happens at the Service layer.** Actions remain pure (no caching logic). Controllers remain thin (no caching logic). Services orchestrate when to cache and when to invalidate.

#### BaseService (NON-NEGOTIABLE)

🚨 **ALL Service classes MUST extend `App\Services\BaseService`.** This is enforced at every level — new Services, existing Services, API Services. There are ZERO exceptions. If you create a Service without `extends BaseService`, it is a violation.

`BaseService` provides:

| Method | Purpose |
|--------|---------|
| `remember(string $key, callable $callback, ?int $ttl)` | Cache the result of `$callback`. Returns cached value if it exists, otherwise executes the callback and caches the result. |
| `invalidate(string\|array $keys)` | Forget one or more cache keys. |
| `cacheKey(string $prefix, int\|string $id, string $suffix)` | Build a namespaced key like `instructor:42:grouped_students`. |

Default TTL is **10 minutes** (600 seconds). Override `$cacheTtl` on the Service class to change per-service.

#### Pattern: Cache on Read, Invalidate on Write

```php
// ✅ READ — wrap Action call with remember()
public function getGroupedStudents(Instructor $instructor): array
{
    $key = $this->cacheKey('instructor', $instructor->id, 'grouped_students');

    return $this->remember($key, fn () => ($this->getGroupedStudents)($instructor));
}

// ✅ WRITE — invalidate after mutation
public function addPupil(Instructor $instructor, array $data): Student
{
    $student = ($this->createPupil)($instructor, $data);

    $this->invalidateStudentCache($instructor);

    return $student;
}

// ✅ Helper to centralize invalidation for a domain
public function invalidateStudentCache(Instructor $instructor): void
{
    $this->invalidate(
        $this->cacheKey('instructor', $instructor->id, 'grouped_students')
    );
}
```

#### Rules

1. **Cache reads only** — never cache writes, deletes, or side-effect operations
2. **Invalidate on any write** that affects the cached data — creates, updates, deletes
3. **Use `cacheKey()` for consistency** — always namespace keys as `{domain}:{id}:{suffix}`
4. **Keep Actions cache-free** — Actions contain pure business logic, no `Cache::` calls
5. **Keep Controllers cache-free** — Controllers delegate to Services, never call `Cache::` directly
6. **Group invalidation** — if a write affects multiple cached queries, invalidate all related keys in a single helper method (e.g., `invalidateStudentCache()`)
7. **Don't cache volatile data** — data that changes every request (e.g., real-time counts) should not be cached

#### 🚨 Caching Violations

- ❌ **Creating a Service that does not extend `BaseService`** — this is the #1 violation
- ❌ Caching inside an Action
- ❌ Caching inside a Controller
- ❌ Forgetting to invalidate after a write
- ❌ Using raw `Cache::` facade instead of `BaseService` methods
- ❌ Hardcoding cache keys as plain strings (use `cacheKey()`)

#### 📋 Checklist for Adding Caching

1. [ ] Service extends `BaseService`
2. [ ] Read method uses `$this->remember()` with a key from `$this->cacheKey()`
3. [ ] All write methods that affect cached data call `$this->invalidate()`
4. [ ] Invalidation helper exists to centralize related cache keys
5. [ ] Actions remain pure — no caching logic

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