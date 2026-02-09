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

Current status: [describe current phase and progress]
```

**These instructions apply to EVERY message, EVERY session, EVERY task.**

---

## 🎯 Workflow Rules (Non-Negotiable)

- **NEVER** proceed to next phase without explicit approval
- **ALWAYS** update `.claude/tasks/current-task.md` after completing tasks
- **ALWAYS** stop at phase boundaries
- **ALWAYS** use ShadCN components (never wireframe styling)

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

---

## ✅ Verification

Before starting work, confirm:
- [ ] Read `instructions.md`
- [ ] Read `tasks/current-task.md`
- [ ] Read applicable context-specific files
- [ ] Understood workflow rules
- [ ] Acknowledged forbidden commands

**Then announce what you've read and proceed!**