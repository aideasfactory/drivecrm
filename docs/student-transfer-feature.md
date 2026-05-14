# Student Transfer Between Instructors

A summary of the new feature that lets an admin move a student from one instructor to another, including what happens behind the scenes for both upfront-paid and weekly-paid bookings.

---

## What the feature does

Admins now have a dedicated **Transfer Student** page accessible from the left-hand navigation (visible only to owner-level users). The page is deliberately simple: pick a student, pick the new instructor, click Transfer. Done.

The system handles everything else automatically — moving future lessons into the new instructor's diary, sending notification emails to all three parties, and writing a permanent audit-trail entry on each person's timeline.

---

## The admin journey

1. Admin clicks **Transfer Student** in the sidebar.
2. **Student dropdown** — searchable list of students who currently have an instructor. Once selected, the page shows *"Current instructor: [name]"* underneath.
3. **Destination Instructor dropdown** — only shows instructors who have completed their Stripe onboarding (so payouts can reach them). The student's current instructor is automatically excluded from this list.
4. Admin clicks **Transfer**.
5. A green confirmation card appears on the page: *"Transfer complete: [student] transferred to [new instructor]. N lessons moved. M clashes flagged in the new instructor's email."* A toast notification confirms the same.

The whole flow is a couple of clicks and takes seconds.

---

## How each payment type is handled

This was the most important question to get right. The short answer: **the system handles both payment types automatically using exactly the same code path**, because of how the underlying booking and payment data is structured. Below is what actually happens to the money and the bookings in each case.

### Scenario A — Student paid upfront (e.g., £600 for 10 lessons)

**Where the money is sitting at the time of transfer:**
The £600 was paid by the student into the platform's main Stripe account at the point of purchase. Critically, that money was **never pre-allocated to any instructor** — it sits in the platform's pot, and each individual lesson the instructor delivers triggers an independent transfer of £60 from the platform to that instructor's Stripe Connect account, *at the moment the lesson is signed off*.

**Walk-through with a real example:**
Imagine the student is halfway through their 10 lessons. Five lessons with Instructor A have already been delivered and signed off — five separate £60 transfers have already gone from the platform pot to A's Stripe account. A has been paid. The remaining £300 (5 lessons × £60) is still in the platform pot.

When the admin transfers the student to Instructor B:
- The five completed lessons stay attached to A in our records. A keeps the £300 they've already been paid. Nothing is reversed, nothing is refunded.
- The remaining five lessons (still in the future) are moved into B's diary at their original dates and times.
- As B delivers each of those lessons and signs them off, the platform fires an independent £60 transfer from the pot to **B's** Stripe Connect account — because the lesson now belongs to B.
- At the end, the platform pot started at £600 and ends at £0. A receives £300, B receives £300. No Stripe gymnastics, no manual reconciliation, no money in limbo.

### Scenario B — Student paying weekly (no upfront payment)

**How weekly billing works:**
Each lesson is its own invoice. After a weekly lesson is delivered and signed off, the system generates a Stripe invoice charging the student directly. The funds go straight to the lesson's instructor's Stripe Connect account — never through the platform pot.

**Walk-through with a real example:**
Student is in week 6 of 10. Five lessons have been delivered, invoiced, and paid — A's Stripe account has received five weekly payments. Five more lessons are scheduled in A's diary.

When the admin transfers the student to Instructor B:
- The five completed-and-paid lessons stay attached to A. Their invoices remain in A's payment history. No money moves.
- The five upcoming lessons are moved into B's diary at their original dates and times.
- When B delivers each of those lessons and signs them off, a Stripe invoice is generated and the payment routes to **B's** Stripe Connect account.

### Why the system handles both cleanly

The key design decision is that **each lesson row carries its own "who's teaching this" field**. When the admin transfers a student, the system simply re-points each future lesson row to the new instructor. The existing payment code (whether it's the upfront drawdown logic or the weekly invoice logic) reads that field at the moment of sign-off to decide whose Stripe account to pay. No special transfer-mode plumbing is needed.

This also means **the old instructor literally cannot accidentally draw down a lesson that's no longer on their diary** — the underlying check happens automatically.

---

## Edge case: outstanding overdue payments

If the student has overdue weekly invoices (lessons delivered with A but never paid for), those debts stay with A. The system already enforces a rule that weekly lessons cannot be signed off until the prior invoice is paid, so there's no risk of A's earnings being misdirected after the transfer. A pursues collection of any overdue balances directly with the student — the transfer doesn't wipe that debt, doesn't transfer it, and doesn't interfere with it.

---

## Edge case: clashes with the new instructor's existing diary

Future lessons are moved into the new instructor's diary **at their existing dates and times**. If the new instructor already has bookings on any of those slots, those time slots will show two entries side-by-side — the calendar UI handles overlapping bookings visually.

The new instructor is told about clashes explicitly. Their notification email includes a list of the exact dates and times where clashes occurred, so they can review and rebook any of those lessons via their diary at a time that works for them and the student. Clashes don't block the transfer — they're surfaced, and the receiving instructor handles them.

---

## Notifications

Three emails are sent automatically on every transfer:

| Recipient | Subject | What it says |
|---|---|---|
| **Student** | *Your driving lessons have moved to a new instructor* | Reassurance about the change, mention that the new instructor will be in touch shortly. |
| **Old instructor** | *Student [name] transferred* | Confirmation that N future lessons have been removed from their diary, and that their past lessons (and earnings) remain attached to them. |
| **New instructor** | *New student assigned: [name]* | The N lessons now in their diary, and **any clashes with their existing bookings listed by date and time** so they know exactly what to fix. |

All three are sent immediately on transfer.

---

## Audit trail

Every transfer writes three entries into the system's permanent activity log:

- One on the **student's** timeline: *"Transferred from [old instructor] to [new instructor]"*
- One on the **old instructor's** timeline: *"Student [name] transferred to [new instructor]"*
- One on the **new instructor's** timeline: *"Student [name] transferred from [old instructor]"*

Each entry includes structured metadata: which admin triggered the transfer, when, the affected lesson IDs, and the clash list. So six months later, anyone can look at any of these three timelines and reconstruct exactly what happened.

The financial history is preserved separately and automatically — every Stripe transfer that happened before the transfer remains in the old instructor's payment history, and every transfer that happens after lands in the new instructor's history. The system never rewrites or back-dates these records.

---

## What's intentionally not in scope (for now)

A few things were deliberately left out of this first version, to keep the feature focused and shippable. They can be added later if the need arises:

- **Scheduled / future-dated transfers** — transfers happen immediately when the admin clicks Transfer.
- **Bulk transfers** — one student at a time. (If a whole roster needs to be moved due to an instructor leaving, it can be done one transfer at a time, or we can add bulk support later.)
- **Transfer reversal / undo** — to reverse a transfer, the admin does another transfer in the opposite direction. The activity log preserves the full history.
- **Initial student assignment** — this feature is for moving a student from one instructor to another. Students with no current instructor are not shown in the dropdown; their initial assignment is handled through the existing PIN-based flow.

---

## Summary

- Admins can move a student in seconds via a dedicated, role-gated page.
- The system handles upfront-paid and weekly-paid bookings identically and correctly — no money is ever moved at transfer time, and no instructor can accidentally receive payment for lessons they didn't teach.
- The old instructor keeps everything they've earned. The new instructor earns everything from the transfer point forward.
- All three parties are notified by email, with the new instructor seeing any diary clashes called out explicitly.
- Every transfer is recorded permanently on all three timelines for full auditability.
- No database migrations were required — the feature is built entirely on the existing data model.
