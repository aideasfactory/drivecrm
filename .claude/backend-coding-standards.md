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
- **Auth**: Use Laravel Fortify features; do not roll custom auth logic.

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
- **Architecture**: Follow **Controller -> Service -> Action** pattern
- **Actions**: Atomic steps (no HTTP).
- **Services**: Orchestration, Transactions & **Caching**.
- **Controllers**: Invokable, Web/Api split, inject Services.
- **Caching**: All Service reads must use `BaseService::remember()`. Writes must `invalidate()`.
- **Models**: Use `app/Models`. Always add `casts()` method.
- **Controllers**: Keep skinny. Use `FormRequest` for validation.
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