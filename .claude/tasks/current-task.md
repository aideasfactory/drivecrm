# Current Task

No active task currently.

## Getting Started

To start a new development task:

### Option 1: Manual Setup
1. Copy the template:
   ```bash
   cp .claude/tasks/task-template.md .claude/tasks/current-task.md
   ```

2. Fill in the Overview section with your feature details

3. Tell Claude to begin:
   ```
   "Read current-task.md and start Phase 1: Planning"
   ```

### Option 2: Let Claude Do It
Simply tell Claude:
```
"I have an SRS and wireframes for [feature name].

Requirements: requirements/SRS-[feature].md
Wireframes: wireframes/[feature].html

Read the SRS, break it into features, and start with Feature 1."
```

Claude will:
1. Analyze the SRS
2. Identify features and priorities
3. Create this file from the template
4. Fill in the Overview
5. Begin Phase 1: Planning

---

## Current Status
⏸️ No active task

## Quick Commands

**Start new task:**
```
"Create task for [feature name] using [SRS path] and [wireframe path]"
```

**Continue existing task:**
```
"Continue with current task"
"What's the current status?"
```

**Approve phase:**
```
"Phase [X] approved, start Phase [Y]"
```

---

**Note:** This file will be replaced with an actual task when you start development.
