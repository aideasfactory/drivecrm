# Task: Isolate automated tests to SQLite in-memory

**Created:** 2026-09-15
**Last Updated:** 2026-09-15
**Status:** Complete

---

## Overview

Prevent `php artisan test` / PHPUnit / Pest / `composer test` from connecting to the
normal development, staging, or production database. Tests must always use an
isolated SQLite in-memory database.

### Success Criteria
- [x] `phpunit.xml` forces `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- [x] Overrides that could restore the normal DB (e.g. unforced env, `DB_URL`, config cache) are fixed
- [x] `.env` and non-test DB config are untouched
- [x] Test suite and destructive DB commands are not run

---

## PHASE 1: PLANNING

**Status:** ✅ Complete

### Tasks
- [x] Inspect `phpunit.xml`, Pest config, Composer test scripts, TestCase
- [x] Identify why tests could still hit the real DB despite sqlite entries
- [x] Decide minimal fix (force env + neutralize overrides)

### Decisions Made
- `phpunit.xml` already listed sqlite `:memory:` but without `force="true"`, so
  values already set when Artisan loads `.env` (before PHPUnit) win.
- Feature tests use `RefreshDatabase`, which would migrate/wipe whatever DB is connected.
- `DB_URL` (if set in `.env`) can override connection details and must be cleared.
- Cached `bootstrap/cache/config.php` can bake in the real DB and ignore env; purge before test boot.

### Reflection
The dangerous gap was not missing sqlite entries — it was that they were non-forced,
so `php artisan test` / `composer test` kept the real `.env` database.

---

## PHASE 2: IMPLEMENTATION

**Status:** ✅ Complete

### Tasks
- [x] Update `phpunit.xml` with forced testing DB env vars; clear `DB_URL`
- [x] Harden `tests/TestCase.php` so env + config cache cannot point at the normal DB
- [x] Verify resolved config without running the test suite

### Reflection
Minimal two-file change. Composer `test` script unchanged (still clears config then
runs artisan test). No `.env.testing` added — not present and not required once
phpunit.xml forces the vars.

---

## PHASE 3: REFLECTION

**Status:** ✅ Complete

### Tasks
- [x] Document files changed, final config, prior risk, and safety confirmation

### Reflection
Prior risk: unforced `DB_*` in phpunit.xml + RefreshDatabase on Feature tests +
possible `DB_URL` / config cache. Final config: forced sqlite `:memory:` with
empty `DB_URL`, plus TestCase pre-boot enforcement. Did not run the test suite
or any destructive database commands. Did not modify `.env` or `config/database.php`.
