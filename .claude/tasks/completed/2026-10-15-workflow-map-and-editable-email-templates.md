# Task: Learner workflow map + fully editable email templates

**Created:** 2026-10-15
**Last Updated:** 2026-10-15
**Status:** Complete

---

## 📋 Overview

### Goal
Mission Control ticket 01a14018-5479-73cf-9d42-c24feceb2144 (Andrew Caygill).

1. Document every learner workflow, from first enquiry to last lesson, in `docs/workflows.md` (mermaid + tables). For each stage, cover what is sent and what triggers it.
2. Make every editable email template use only simple placeholders, with no code-built content blocks. Migrate the stored content, and list the available placeholders per template in the admin editor.

### Success Criteria
- [x] `docs/workflows.md` covers enquiry → onboarding → booking/payment (in full and weekly) → holds → reminders → reschedules/cancellations/refunds → sign-off/completion, and flags ambiguous or dead paths.
- [x] No template relies on code-built sentences or blocks (`{{intro}}`, `{{payment_block}}`, `{{refund_line}}`, …).
- [x] Data migration converts the stored rows (default or staff-edited) so emails read the same.
- [x] The admin editor lists the placeholders and conditions for each template and inserts them at the cursor.
- [x] Unknown placeholders and conditions, and unbalanced tags, render harmlessly.

---

## 🎯 PHASE 1: PLANNING — ✅ Complete

### Decisions Made
- Templates had no fixed `{{{Intro}}}`-style blocks in Blade. The fixed content was in sentences that the notifications built in PHP and passed in as placeholder values. Those values are now plain data (names, dates, amounts, counts, lists), and all the wording lives in the template.
- Wording that changes with the booking (contact vs learner, staff vs instructor booking, refund/late-cancellation, singular/plural) uses `{{#if condition}}…{{else}}…{{/if}}` sections. Sections can be nested. Without them, some of the copy would have to stay in code.
- The migration replaces each old block token with equivalent template text, one template at a time. This keeps staff-edited copy intact as well as the defaults.

## 🎯 PHASE 2: IMPLEMENTATION — ✅ Complete

- [x] `EmailTemplateInterpolator`: conditional sections; unknown placeholders render blank; unknown conditions are false; unbalanced tags are dropped; `{{{x}}}` is treated as `{{x}}`; runs of blank lines are collapsed.
- [x] `EmailTemplateCatalog`: new defaults, a `conditions` list per template, and updated placeholder descriptions.
- [x] Notifications and Mailables pass simple values and boolean conditions.
- [x] Migration `2026_10_15_160000_convert_email_template_blocks_to_editable_text`.
- [x] `ListEmailTemplatesAction` exposes `conditions`. `EditEmailTemplateSheet.vue` lists the placeholders and conditions with descriptions and inserts them at the cursor.
- [x] `.claude/database-schema.md` updated.
- [x] `docs/workflows.md` written.

## 🎯 PHASE 3: REFLECTION — ✅ Complete

- Nothing could be executed: PHP, vendor and node_modules are not installed, and tests were out of scope. All changes were reviewed by reading the code only.
- Small wording difference: a weekly order with no fees used to show "Payment: Weekly (£X per lesson)". It now shows the weekly payment section with a "Lessons: £X" line.
- Names inside conditional intros keep their bold formatting, so the rendered copy is unchanged.

## 🎯 PHASE 4: REVIEW FOLLOW-UP — ✅ Complete

- [x] `{{cost_breakdown}}` split into individual placeholders: `{{lesson_cost}}`, `{{booking_fee}}`, `{{digital_fee}}`, `{{guarantee_fee}}` (blank when zero), plus `has_fees` and `guarantee_included_free` conditions. Values come from `Order::costBreakdownPlaceholders()` and `LessonPayment::costBreakdownPlaceholders()` / `breakdownPlaceholders()`. The old `*Lines()` methods are kept because unit tests use them.
- [x] `{{lesson_word}}` in both bulk-reschedule templates replaced with the `multiple_lessons` condition; learner copy now reads "lesson has" for a single lesson.
- [x] Migration `2026_10_15_162422_split_email_template_cost_breakdown_into_placeholders` converts stored rows.
- [x] Checked in tinker (no DB writes): new fee lines render identically to the old `cost_breakdown` output for every fee combination, and the migration turns the previous defaults into the new defaults and back.
- Not changed: `{{lesson_list}}` (a pre-formatted list) and the hard-coded head-office emails (`BookingEnquirySubmittedMail`, `RefundRequiredNotification`, `ReleasedOrderPaidNotification`, `ManualInstructorPayoutRequiredNotification`).
