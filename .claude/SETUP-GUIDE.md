# 🚀 Quick Setup Guide

## 📦 What You're Getting

**5 core files** that make up the Claude workflow system:

1. **README.md** - Complete documentation (this file)
2. **instructions.md** - Main workflow and coding standards
3. **wireframe-rules.md** - Wireframe implementation rules
4. **tasks/task-template.md** - Template for tracking features
5. **tasks/current-task.md** - Active task placeholder

---

## 📁 Step 1: Copy to Your Project

### Your Project Structure Should Look Like:

```
your-project/
│
├── .claude/                              ← DROP THE DOWNLOADED FOLDER HERE
│   ├── README.md
│   ├── instructions.md
│   ├── wireframe-rules.md
│   └── tasks/
│       ├── task-template.md
│       ├── current-task.md
│       └── completed/
│
├── business-context/                    ← CREATE THIS (optional, for Claude.ai)
│   ├── company-overview.md
│   ├── brand-guidelines.md
│   └── temp-srs-template.md
│
├── requirements/                        ← CREATE THIS
│   └── (SRS documents will go here)
│
├── wireframes/                          ← CREATE THIS
│   └── (HTML wireframes will go here)
│
├── src/                                 ← YOUR CODE (already exists)
│   └── ...
│
└── package.json
```

---

## 🎯 Step 2: Create Additional Folders

```bash
# Navigate to your project
cd your-project/

# Create folders for requirements and wireframes
mkdir -p requirements wireframes

# Optional: Create business context folder (for Claude.ai Project)
mkdir -p business-context
```

---

## ⚙️ Step 3: Customize (Optional but Recommended)

### Edit `.claude/instructions.md` to Add Your Standards:

```markdown
## Project-Specific Standards

### Our Tech Stack
- Next.js 14 with App Router
- TypeScript (strict mode)
- Tailwind CSS + ShadCN UI
- Prisma + PostgreSQL
- React Query for data fetching

### Our Naming Conventions
- Components: PascalCase (UserProfile.tsx)
- Hooks: use + PascalCase (useAuth.ts)
- Utils: camelCase (formatDate.ts)
- Constants: UPPER_SNAKE_CASE (API_BASE_URL)

### Our Code Style
- Use named exports (not default)
- One component per file
- Props interface above component
- Use async/await (not .then())

### Our Error Handling
- Always try-catch for async operations
- Toast notifications for user-facing errors
- Console.error for debugging
- Throw errors up to error boundaries
```

---

## 🚦 Step 4: Test It Out

### Quick Test (5 minutes):

```
1. Open Claude Code in your project
2. Say: "Read .claude/instructions.md and tell me what coding standards I have"
3. Claude should respond with your standards
```

### Full Test (30 minutes):

Create a simple SRS and wireframe:

**requirements/test-feature.md:**
```markdown
# Test Feature SRS

## FR-001: Simple Button
User can click a button that shows an alert.

### Acceptance Criteria
- AC-001: Button displays "Click Me"
- AC-002: Clicking button shows alert
- AC-003: Button uses ShadCN styling
```

**wireframes/test-button.html:**
```html
<div style="text-align: center; padding: 40px;">
  <button style="background: blue; color: white; padding: 20px;">
    Click Me
  </button>
</div>
```

**Then in Claude Code:**
```
You: "Create a task for the test feature.
      Requirements: requirements/test-feature.md
      Wireframe: wireframes/test-button.html
      
      Start Phase 1 planning."
```

**Claude should:**
1. Read instructions.md ✓
2. Read wireframe-rules.md ✓
3. Create current-task.md
4. Plan the feature
5. Note: Use ShadCN Button (NOT blue background from wireframe)
6. Stop and wait for approval

---

## 📋 Step 5: Your First Real Feature

### A. If You Have an SRS Already:

```
You: "I have an SRS and wireframes for [feature name].
      
      Requirements: requirements/SRS-[feature].md
      Wireframes: wireframes/[feature].html
      
      Read the SRS and break it into features."

Claude: [Analyzes and proposes feature breakdown]

You: "Start with Feature 1"

Claude: [Creates task, begins Phase 1]
```

### B. If You Need to Generate an SRS:

**In Claude.ai Project (if you set up business-context/):**
```
You: "Generate an SRS for [feature description].
      Use our business context and template."

Claude.ai: [Generates professional SRS]

You: "Save this as requirements/SRS-[feature].md"
```

**Then in Claude Code:**
```
You: "Build from requirements/SRS-[feature].md"
```

---

## 🎨 Working With Wireframes

### Create HTML Wireframes

**Option 1: Simple HTML**
```html
<!-- wireframes/search-page.html -->
<!DOCTYPE html>
<html>
<body>
  <div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
      <input type="text" placeholder="Location">
      <input type="date" placeholder="Check-in">
      <input type="number" placeholder="Guests">
    </div>
    <button>Search Hotels</button>
  </div>
</body>
</html>
```

**Option 2: Use Claude.ai to Generate**
```
You (in Claude.ai): "Create an HTML wireframe for a hotel search page.
                      Include: location input, date picker, guest selector, search button.
                      Use a centered layout with 3-column grid."

Claude.ai: [Generates HTML]

You: "Save as wireframes/search-page.html"
```

**What Claude Code Will Extract:**
- ✅ Centered layout (max-width: 1200px)
- ✅ 3-column grid structure
- ✅ Gap spacing
- ❌ Will NOT use any colors or specific styling
- ✅ Will use ShadCN Input and Button components instead

---

## 💬 Common Commands

### Starting Work
```
"Read the SRS and break it into features"
"Create task for [feature name]"
"Start Phase 1"
```

### During Development
```
"How's it going?"
"What's the current status?"
"Show me completed tasks"
```

### Approving Phases
```
"Phase 1 approved, start Phase 2"
"Continue to next phase"
"Phase looks good, continue"
```

### Making Changes
```
"Add [requirement] to current task"
"Use [different library] instead"
"Stop, I need to review this first"
```

### Completing
```
"Archive this task"
"Mark complete and start Feature 2"
```

---

## ✅ Verification Checklist

After setup, verify:

- [ ] `.claude/` folder is in your project root
- [ ] `requirements/` folder exists
- [ ] `wireframes/` folder exists
- [ ] You've customized `instructions.md` with your standards (optional)
- [ ] Claude can read `.claude/instructions.md`
- [ ] Claude understands your coding standards

---

## 🎯 What Happens Next

### Every Time Claude Works:

1. **Reads instructions.md** (your coding standards)
2. **Reads wireframe-rules.md** (layout vs styling rules)
3. **Reads current-task.md** (current progress)
4. **Follows the 4-phase workflow:**
   - Phase 1: Planning → Stop for approval
   - Phase 2: Implementation → Stop for approval
   - Phase 3: Testing → Stop for approval
   - Phase 4: Reflection → Archive task

### Guarantees:

✅ Consistent coding style (follows your instructions)
✅ Proper wireframe implementation (layout only, never styling)
✅ Phase discipline (always stops for approval)
✅ Complete tracking (updates task file constantly)
✅ Historical record (archives completed tasks)

---

## 🐛 Troubleshooting

### Claude isn't reading instructions
```
You: "Before you start, read .claude/instructions.md"
```

### Claude used wireframe colors
```
You: "Stop. Read .claude/wireframe-rules.md. 
      You should use ShadCN components, not wireframe styling.
      Redo this using layout only."
```

### Task file not updating
```
You: "Update .claude/tasks/current-task.md with your progress"
```

### Need to restart a phase
```
You: "Go back to Phase 2. We need to make changes."
```

---

## 📚 Next Steps

1. ✅ Complete setup (copy files, create folders)
2. ✅ Customize instructions.md with your standards
3. ✅ Test with a simple feature
4. ✅ Build your first real feature
5. ✅ Review archived tasks to learn patterns

---

## 🎓 Resources

- **README.md** - Complete system documentation
- **instructions.md** - Workflow rules and standards (edit this!)
- **wireframe-rules.md** - Design implementation guide
- **task-template.md** - See what a complete task looks like

---

## ✨ You're Ready!

Your Claude workflow system is set up. Claude will now:

- Follow your coding standards consistently
- Extract layout from wireframes (never styling)
- Stop at phase boundaries for your approval
- Track everything in detailed task files
- Build high-quality, professional code

**Happy building! 🚀**

---

**Questions?** Review the README.md for detailed documentation.

**Need help?** Check the troubleshooting section above.
