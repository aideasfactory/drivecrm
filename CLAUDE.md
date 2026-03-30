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

---

## ✅ Verification

Before starting work, confirm:
- [ ] Read `instructions.md`
- [ ] Read `tasks/current-task.md`
- [ ] Read applicable context-specific files
- [ ] Understood workflow rules
- [ ] Acknowledged forbidden commands

**Then announce what you've read and proceed!**