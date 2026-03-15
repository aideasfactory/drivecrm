# Project Development Instructions

## 🚨 CRITICAL: Read These Files Every Time

**BEFORE STARTING ANY WORK:**
1. **ALWAYS** read `.claude/instructions.md` (this file)
2. **ALWAYS** read `.claude/tasks/current-task.md` to check progress
3. **ALWAYS** read `.claude/backend-coding-standards.md` when coding
4. **ALWAYS** read `.claude/database-schema.md` when working with databases
5. **ALWAYS** read `.claude/frontend-coding-standards.md` when working with designs
6. **ALWAYS** read `.claude/wireframe-rules.md` when working with designs
7. **ALWAYS** read `.claude/api.md` when building or modifying ANY API feature

**ALWAYS** confirm you have read and understood the above files before continuing

---

## 📋 Task Management Workflow

### Starting a New Task
When beginning a new feature/component:

1. **Create new task file:**
   - Copy template to `.claude/tasks/current-task.md`
   - Fill in the Overview section
   - Begin Phase 1: Planning

2. **Announce what you're reading:**
   ```
   I've read:
   - .claude/instructions.md
   - .claude/tasks/current-task.md
   - Current status: [Phase X - Y% complete]
   ```

### During Development

1. **Update current-task.md frequently:**
   - Mark tasks with ✓ as you complete them
   - Update "Currently working on" section
   - Note blockers immediately
   - Update Last Updated timestamp

2. **At phase boundaries (AUTO-CONTINUE):**
   - Complete all tasks in current phase
   - Fill in Reflection section
   - Mark phase status as Complete
   - **Immediately continue to the next phase — do NOT stop or ask permission**

3. **Status indicators:**
   - ⏸️ Not Started
   - 🔄 In Progress (actively working)
   - ✅ Complete (all tasks done)
   - ⏭️ Skipped (if phase not needed)

### Completing a Task

1. Move `current-task.md` to `completed/[date]-[feature-name].md`
2. Example: `completed/2024-02-04-user-authentication.md`
3. Clear `current-task.md` for next task

---

## 🎨 Wireframe Implementation Rules

**When wireframe is provided, read `.claude/wireframe-rules.md` immediately.**

**Quick Reference:**
- ✅ USE: Layout, spacing, positioning, structure
- ❌ DON'T USE: Colors, button styles, custom CSS
- 🎯 ALWAYS: Use ShadCN components with default styling

---

## 💻 Development Standards

### Code Quality
- Follow existing project patterns
- Use ShadCN components with defaults
- Keep components small and focused
- Write self-documenting code
- Comment only complex logic

### File Organization
- Components: `PascalCase.tsx`
- Utilities: `camelCase.ts`
- Constants: `UPPER_SNAKE_CASE`
- Follow existing structure

### Before Committing Code
- [ ] All phase tasks checked off
- [ ] No console errors
- [ ] Tested core functionality
- [ ] Updated current-task.md
- [ ] Reflection completed

---

## 🔄 Phase Checkpoint Protocol

**At the end of EVERY phase (except the last):**

1. ✅ Mark all tasks in phase as complete
2. 📝 Fill in Reflection section (what went well, improvements)
3. ⏸️ Update phase status to Complete
4. 🕒 Update "Last Updated" timestamp
5. ▶️ **Immediately continue to the next phase — do NOT stop or ask permission**

**When ALL phases are complete (entire task done):**

1. Complete steps 1-4 above for the final phase
2. 📄 Write `.phase_done` sentinel file to project root:
```json
{
  "phase_completed": "all",
  "total_phases": 3,
  "status": "success",
  "summary": "Brief description of what was accomplished across all phases"
}
```
3. 🛑 **STOP. The entire task is done.**

**If a phase fails**, write the sentinel with `"status": "failed"` and an `"error"` field. Then STOP.

**DO NOT:**
- ❌ Stop between phases to ask permission
- ❌ Ask "shall I continue?" or "ready for next phase?"
- ❌ Skip reflection sections
- ❌ Leave tasks unchecked
- ❌ Forget to update timestamps
- ❌ Create a "Testing & Review" phase (no migrations, no manual test scenarios)

---

## 📢 Communication Standards

### Starting Each Session (New Task)
```
I've read the following files:
- .claude/instructions.md ✓
- .claude/tasks/current-task.md ✓
- [context-specific files] ✓

Current Status:
- Task: [Task name]
- Phase: [Phase number and name]
- Progress: [X/Y tasks complete]
- Working on: [What I'll do this session]
```

### Starting Each Session (Resuming — User Says "Continue")
```
I've read the following files:
- .claude/instructions.md ✓
- .claude/tasks/current-task.md ✓
- [context-specific files] ✓

Resuming:
- Task: [Task name]
- Completed phases: [list completed]
- Next phase: Phase [X] — [name]
- Starting now.
```

Then immediately begin executing the next incomplete phase. Do NOT re-plan or re-do completed work.

### All Phases Complete (Session End)
After writing the `.phase_done` sentinel file at the very end, output ONLY this:
```
All phases complete. Sentinel written. Task done.
```
Then STOP. Do not output anything else. Do not ask questions. Do not suggest next steps.

### When Blocked
```
⚠️ BLOCKER ENCOUNTERED

Issue: [Description]
Attempted: [What I tried]
Suggestions: [Possible solutions]
Impact: [What's blocked]

Documented in current-task.md.
```
Write `.phase_done` with `"status": "failed"` and the error details. Then STOP.

---

## 🔍 Self-Review Checklist

After **each phase**, verify:

- [ ] All tasks marked complete (✓)
- [ ] Reflection section filled out
- [ ] Timestamp updated
- [ ] Phase status updated
- [ ] Notes captured
After **implementation phase specifically:**

- [ ] Code follows project patterns
- [ ] ShadCN components used correctly
- [ ] No custom styling unless for layout
- [ ] Error handling in place
- [ ] Loading states implemented
- [ ] TypeScript types defined
- [ ] No console.log statements left

---

## 🎯 Phase-Specific Guidelines

### Phase 1: Planning
- Break down requirements into specific tasks
- Identify all components needed
- Note any API/backend dependencies
- Flag potential complexity/risks
- Get approval before coding

### Phase 2: Implementation
- Work through tasks sequentially
- Update "Currently working on" frequently
- Commit working code regularly
- Document decisions as you make them
- Stop if blocked - don't guess

### Phase 3: Reflection
- Be honest about what worked/didn't
- Identify technical debt created
- Note future improvements
- Document lessons learned
- Think about reusability

---

## 🗄️ Database Change Protocol

### When Creating or Modifying Migrations

**MANDATORY STEPS (in this order):**

1. **Create/modify the migration file**
   ```bash
   php artisan make:migration create_table_name
   ```

2. **Write the migration code**
   - Add columns, indexes, foreign keys
   - Test locally (user will run migrations)

3. **IMMEDIATELY update `.claude/database-schema.md`**
   - Add new table documentation
   - Update relationships section
   - Update ERD diagram if structure changed
   - Update "Key Business Flows" if applicable

4. **Announce the update**
   ```
   ✅ Migration created: create_bookings_table
   ✅ Updated: .claude/database-schema.md (added Bookings table)
   ```

5. **Mark task complete** (only after documentation is updated)

---

**NEVER:**
- ❌ Create a migration without updating database-schema.md
- ❌ Mark a task complete if schema docs are outdated
- ❌ Say "documentation will be updated later"

**The rule is:** Migration code + Schema documentation = ONE atomic task.

---

## 🌐 API Documentation Protocol

### When Creating or Modifying API Endpoints

**MANDATORY STEPS (in this order):**

1. **Create the API endpoint** (Controller, Resource, FormRequest, route)
2. **IMMEDIATELY update `.claude/api.md`**
   - Add the endpoint with method, path, auth requirements
   - Document ALL request body fields with types and validation
   - Include a complete example response with realistic data
   - Update the changelog at the bottom
3. **Announce the update**
   ```
   API endpoint created: POST /api/v1/auth/login
   Updated: .claude/api.md
   ```

**NEVER:**
- ❌ Create an API endpoint without updating api.md
- ❌ Mark an API feature complete if api.md is outdated
- ❌ Say "API docs will be updated later"

**The rule is:** API code + api.md documentation = ONE atomic task.

---

## 📁 File Management

### Task Files
- **Active:** `.claude/tasks/current-task.md`
- **Archive:** `.claude/tasks/completed/YYYY-MM-DD-task-name.md`

### When to Archive
- Task fully complete and approved
- Moving to completely new feature
- Major milestone reached

### Naming Convention
```
completed/2024-02-04-user-authentication.md
completed/2024-02-05-dashboard-layout.md
completed/2024-02-10-data-export-feature.md
```

---

## ⚡ Quick Commands for Human

**To start new task:**
```
"Create new task file for [feature name] and start Phase 1"
```

**To continue to next phase (new session):**
```
"Continue"
```

**To check status:**
```
"What's the current status? Read current-task.md"
```

**To archive completed task:**
```
"Archive current task to completed folder"
```