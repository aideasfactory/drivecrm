# Project Instructions for Claude

## 🚨 CRITICAL: Read These Files Before Every Task

**BEFORE starting ANY work, you MUST read these files:**

1. **`.claude/instructions.md`** - Main workflow rules and coding standards
2. **`.claude/tasks/current-task.md`** - Current task progress

**Context-Specific Files (read when applicable):**

3. **`.claude/backend-coding-standards.md`** - When working with PHP/Laravel backend code
4. **`.claude/database-schema.md`** - When working with database models, migrations, or queries
5. **`.claude/frontend-coding-standards.md`** - When working with Vue/Inertia frontend code
6. **`.claude/wireframe-rules.md`** - When implementing designs or wireframes
7. **`.claude/api.md`** - When building, modifying, or debugging ANY API endpoint
8. **`.claude/payment-pipeline.md`** - When a change may touch orders, checkout, Stripe, webhooks, holds, invoices, payouts, refunds or pricing

---

## 📢 After Reading, Always Announce:

```
I've read:
- .claude/instructions.md ✓
- .claude/tasks/current-task.md ✓

Context-specific files (if applicable):
- .claude/backend-coding-standards.md ✓ (if backend work)
- .claude/database-schema.md ✓ (if database work)
- .claude/frontend-coding-standards.md ✓ (if frontend work)
- .claude/wireframe-rules.md ✓ (if design work)
- .claude/api.md ✓ (if API work)

Current status: [describe current phase and progress]
```

**These instructions apply to EVERY message, EVERY session, EVERY task.**

---

## 🎯 Workflow Rules (Non-Negotiable)

- **ALWAYS** update `.claude/tasks/current-task.md` after completing each step
- **ALWAYS** use ShadCN components (never wireframe styling)
- **ALWAYS** auto-continue to the next phase after completing one — do NOT stop between phases
- **NEVER** ask "shall I continue?" or "ready for the next phase?" — just keep going
- **NEVER** create a "Testing & Review" phase (no running migrations, tests, or manual testing scenarios)

---

## 🔄 Phase Completion Protocol (CRITICAL)

**When you finish ALL steps in the current phase:**

1. Update `current-task.md` — mark all steps complete, fill in Reflection, update status
2. **Immediately continue to the next phase** — do NOT stop, do NOT ask permission

**When ALL phases are complete (entire task done):**

1. Update `current-task.md` — mark final phase complete
2. Write a `.phase_done` file to the project root with this JSON:
```json
{
  "phase_completed": "all",
  "total_phases": 3,
  "status": "success",
  "summary": "Brief description of what was accomplished across all phases"
}
```
3. **STOP. The entire task is done. Do not prompt the user.**

**If a phase fails:**
Write `.phase_done` with `"status": "failed"` and include an `"error"` field. Then STOP.

**When resuming (user starts a new session and says "continue"):**
1. Read `current-task.md`
2. Find the next phase with status "Not Started"
3. Execute that phase and continue through remaining phases
4. Do NOT re-do completed phases. Do NOT re-plan. Just pick up where you left off.

---

## 💳 Payment Pipeline Rule (CRITICAL)

**We cannot afford to break payments.** On EVERY task:

1. **Before coding**, check the planned changes against **`.claude/payment-pipeline.md`** (critical files + payment data).
2. **If the task touches the payment pipeline**, announce `⚠️ PAYMENT PIPELINE: ...` before changing it, think twice, keep the change minimal, and follow the protocol in that file.
3. **Every task summary** (and `current-task.md`) must end with a `💳 Payment impact:` line — `NONE` or `TOUCHED` with files, what changed, what didn't, risks, and checks to run before release.

---

## 🚫 Forbidden Commands

**NEVER run these commands:**
- ❌ `php artisan test` (user runs tests manually)
- ❌ `./vendor/bin/pint` (user handles code style)
- ❌ `npm run lint` (user handles linting)
- ❌ `prettier`, `eslint` (user handles formatting)

**Always acknowledge:** "I understand I must not run tests or linting commands."

---

## 📊 Database Documentation Rule

**CRITICAL: After creating or modifying ANY database migration:**

1. **IMMEDIATELY update `.claude/database-schema.md`**
2. Add/update the relevant table documentation
3. Update relationships if they changed
4. Update the ERD diagram if structure changed
5. **Announce:** "I've updated database-schema.md to reflect the migration changes."

**This applies to:**
- ✅ New migrations (`create_`, `add_`, `modify_`)
- ✅ Modified migrations (changed columns, indexes, relationships)
- ✅ Dropped tables or columns

**Always update database-schema.md BEFORE marking the task complete.**

---

## 🌐 API Documentation Rule

**CRITICAL: After creating or modifying ANY API endpoint:**

1. **IMMEDIATELY update `.claude/api.md`**
2. Document the endpoint: method, path, auth, request body, response example
3. Update the changelog at the bottom of api.md
4. **Announce:** "I've updated api.md to reflect the new/changed endpoint."

**This applies to:**
- ✅ New API endpoints
- ✅ Modified request/response structures
- ✅ Changed auth requirements
- ✅ Removed endpoints

**Always update api.md BEFORE marking an API task complete.**

---

## 🔍 Quick Reference

### When to Read Each File:

| File | Read When |
|------|-----------|
| `instructions.md` | **Every time** |
| `tasks/current-task.md` | **Every time** |
| `backend-coding-standards.md` | PHP/Laravel code, Controllers, Models, Services, Actions |
| `database-schema.md` | Migrations, Models, Relationships, Database queries |
| `frontend-coding-standards.md` | Vue components, Inertia pages, Frontend logic |
| `wireframe-rules.md` | Implementing designs, Working with HTML wireframes |
| `api.md` | **Any API endpoint work** — creating, modifying, or debugging API routes |
| `payment-pipeline.md` | **Any change that could touch payments** — check every task against its file list |

---

## ✅ Verification

Before starting work, confirm:
- [ ] Read `instructions.md`
- [ ] Read `tasks/current-task.md`
- [ ] Read applicable context-specific files
- [ ] Understood workflow rules
- [ ] Acknowledged forbidden commands

**Then announce what you've read and proceed!**

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
