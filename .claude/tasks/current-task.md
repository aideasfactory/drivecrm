# Task: Fix lesson package pricing label on onboarding form

**Created:** 2026-06-17
**Last Updated:** 2026-06-17
**Status:** Implementation

---

## 📋 Overview

### Goal
Fix the pricing unit label on the onboarding "Pick a package" step (Step 3). The
per-lesson price is currently labelled "per hour", which mixes up lessons and
hours. Update to "per lesson" so packages such as "10 lessons for £350" display
as "£35.00 per lesson" rather than "£35.00 per hour".

### Success Criteria
- [x] On the onboarding package selection step, the small price under each
      package shows "per lesson" instead of "per hour"
- [x] Both the discounted and non-discounted price templates are updated
- [x] Wording is consistent with the rest of the app (Step 5, BookLessonSection,
      PackageForm — all already use "per lesson" / "/lesson")

### Context
- Affected file: `resources/js/pages/Onboarding/Step3.vue`
- The price value comes from `pkg.formatted_lesson_price`, which is derived in
  `app/Models/Package.php` as `lesson_price_pence / 100`, where
  `lesson_price_pence = total_price_pence / lessons_count`. So the value is
  per-lesson by construction; only the label is wrong.
- Other onboarding/package surfaces already use "per lesson" wording, so this is
  a localised inconsistency in Step 3.

---

## 🎯 PHASE 1: PLANNING ✅

**Status:** ✅ Complete

### Tasks
- [x] Locate the onboarding package selection display
- [x] Confirm the underlying value is per-lesson (not per-hour)
- [x] Check sibling surfaces for the agreed wording

### Decisions Made
- Use "per lesson" (matches Step 5 caption style "£X/lesson" and PackageForm's
  "£X per lesson"). Step 3's existing pattern is the longer phrase
  "{{ price }} per hour", so the smallest-diff fix is to swap "hour" for
  "lesson", which also reads naturally next to the "{{ pkg.lessons_count }}
  lessons" subtitle directly above the price block.

### Components Identified
- `resources/js/pages/Onboarding/Step3.vue` — two template branches (`discount`
  vs no-discount) both contain the label.

### Complexity Assessment
- [x] Low (< 2 hours)

### Reflection
**What went well:** Quick grep across `resources/js` showed the wording is
already "per lesson" everywhere else — confirms this is an isolated copy bug,
not a wider rename.

**What could be improved:** Nothing — straightforward copy fix.

**Risks identified:** None. No backend, schema, or API changes; pure label
update on a Vue template.

---

## 🔨 PHASE 2: IMPLEMENTATION ✅

**Status:** ✅ Complete

### Tasks
- [x] Update "per hour" → "per lesson" in the discounted template branch
- [x] Update "per hour" → "per lesson" in the non-discounted template branch

### Files modified
- `resources/js/pages/Onboarding/Step3.vue`

### Reflection
**What went well:** Two-line change, both occurrences sit side-by-side in the
same component. No other "per hour" usages anywhere else in
`resources/js`.

**Technical debt created:** None.

---

## 💭 PHASE 3: FINAL REFLECTION & DOCUMENTATION ✅

**Status:** ✅ Complete

### Documentation Updates
- `results.md` written at project root with client-facing summary and
  confidence score.

### Known Issues
- None.

### Overall Reflection

#### What Worked Well
1. Codebase grep narrowed the problem to a single file and two template lines.
2. Cross-checked the Package model so we're confident the per-lesson value is
   correct — only the label was wrong.
3. Verified other onboarding/package surfaces already use "per lesson" wording,
   so the fix puts Step 3 in line with the rest of the app.

#### Lessons Learned
1. When a label looks wrong, check the source attribute (here
   `formatted_lesson_price`) before assuming the value itself is wrong — saves a
   wider investigation.

### Future Recommendations
- None required for this ticket.

---

## ✅ TASK COMPLETE

**Completed:** 2026-06-17
**Status:** All phases complete.
