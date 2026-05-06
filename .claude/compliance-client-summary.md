# Drive CRM — Compliance Snapshot

*Plain-English summary of where Drive CRM stands on the policy, regulatory
and security work needed to take the platform live to paying instructors.*

**Snapshot date:** 2026-04-30
**Audience:** business stakeholders — not a legal opinion, not implementation detail.

---

## Why two regulatory regimes apply

Two separate sets of UK rules apply to Drive CRM at the same time, because
of how the product works:

1. **Drive CRM acts as a digital platform** that connects pupils with
   instructors and handles the payment between them via Stripe Connect.
   That brings it inside the UK's *Reporting Rules for Digital Platforms*
   (the UK equivalent of DAC7). Booksy is in the same category, which is
   why their compliance pages flagged this.
2. **Drive CRM submits Income Tax (and optionally VAT) to HMRC on the
   instructor's behalf** via HMRC's Making Tax Digital APIs. That makes
   the platform an HMRC-recognised software vendor, with its own set of
   obligations under HMRC's terms.

Both regimes carry registrations, public documents, security
requirements and ongoing reporting duties. The regimes overlap on things
like privacy policy and breach response, but neither substitutes for the
other.

---

## What's already in place

A meaningful slice of the technical foundation is built and verified
against HMRC's sandbox today. Specifically:

- HMRC OAuth connection with PKCE, encrypted token storage at rest, and
  silent refresh under database locks so instructors stay connected
  for 18 months without prompting.
- HMRC fraud-prevention headers attached to every API call (a hard
  HMRC requirement).
- Six-year audit trail of every HMRC submission, including correlation
  IDs and HMRC's responses, so any submission can be reconstructed on
  demand.
- Quarterly Income Tax submissions verified end-to-end against the HMRC
  sandbox.
- VAT 9-box code in place for the small minority of VAT-registered
  instructors.
- Operational monitoring — automated alerts when HMRC token refreshes
  start failing, and runbooks for the four common support scenarios.
- Stripe Connect already handles a meaningful portion of payment-related
  compliance (KYC on instructors, sanctions screening, card-data
  handling) — Drive CRM never touches raw card numbers.

This isn't the *whole* compliance picture, but it's the part most likely
to derail a launch if it's missing. It is in place.

---

## Where we are on the launch journey

A pragmatic three-bucket view.

**Who does what — labels in the lists below.** To make it obvious where
each item sits, every bullet carries one of these tags:

- **[Solicitor]** — needs legal drafting or sign-off; goes on the next solicitor consult.
- **[AI-assisted]** — you can lead this with AI help using ICO, HMRC or government templates. A short solicitor sweep at the end of the batch is sensible but not a precondition.
- **[Platform]** — built or configured by the Drive CRM development team.
- **[External]** — a third party owns the work itself: a registration form, a broker, an assessor. Often quick once the underlying material is ready.

Some items carry more than one tag — for example, the digital-platform-reporting registration is **[External]** for the form itself, but the due-diligence procedure that has to be in place behind it is **[AI-assisted]**.

### Must be in place before launch

These items are either required by UK law, or required by HMRC / Stripe
before they will let the platform transact. None of them is unusual or
specific to Drive CRM — they are the standard launch pack for any UK
SaaS handling tax data and pupil payments.

- **[External]** ICO data controller registration (annual fee, takes minutes online)
- **[Solicitor]** Privacy Policy — public, covering how pupil and instructor data is handled
- **[Solicitor]** Instructor Terms of Service and Pupil Terms of Service
- **[Platform]** Cookie consent banner — built into the site
- **[AI-assisted]** Cookie Policy — short, templated under UK regulations
- **[External]** Data-processing contracts with each provider Drive CRM uses (Stripe, hosting, email) — usually click-through templates the providers issue
- **[External]** Mechanism for international data transfers (Stripe is US-based) — Stripe provides standard documents; the business signs them
- **[External]** + **[AI-assisted]** Registration as a Reporting Platform Operator with HMRC under the digital platform rules — online form **[External]**, supported by a documented due-diligence procedure **[AI-assisted]**
- **[AI-assisted]** A documented procedure for verifying instructor details (name, address, tax number) — Drive CRM already collects most of this; the procedure formalises and audits it
- **[External]** HMRC production access — the formal application that takes Drive CRM from sandbox to live submissions (gated on the Privacy Policy and T&Cs being signed off)
- **[AI-assisted]** PCI compliance self-assessment (the short form, since Stripe handles the heavy lifting)
- **[AI-assisted]** Data Protection Impact Assessment — a documented analysis of how personal data is handled and what risks are mitigated. UK GDPR specifically requires this *before* high-risk processing begins, and Drive CRM's combination of financial data, tax identifiers, and data drawn from Stripe and HMRC clearly qualifies as high-risk
- **[AI-assisted]** Record of Processing Activities — the internal log of every processing purpose, lawful basis, retention period and recipient. UK GDPR requires it to be maintained from the moment processing begins, so it sits alongside the launch documents

### Strongly recommended before launch

These are not strict legal pre-conditions, but it would be unwise to go
live without them. A regulator can ask to see them and the platform must
produce them within a reasonable time, which means they need to exist
*before* the request lands.

- **[AI-assisted]** Data retention schedule
- **[AI-assisted]** Procedure for responding to "give me my data" / "delete my data" requests within the legal one-month window
- **[AI-assisted]** Breach response playbook — the law requires notification within 72 hours, which is impossible without a runbook prepared
- **[Platform]** A confirmation step on every Income Tax submission ("I confirm these figures are accurate"), captured and timestamped against the audit trail
- **[External]** Professional Indemnity insurance with software-defect cover
- **[External]** Cyber liability insurance

### Can be addressed in the first 6–12 months after launch

Industry good practice and procurement signals — useful, but not
gatekeepers to going live.

- **[External]** Annual external penetration test
- **[AI-assisted]** + **[External]** Cyber Essentials certification — questionnaire-driven, AI helps with the answers; the cert itself is awarded by an external assessor
- **[AI-assisted]** Written internal policies for vulnerability management, change control, backup and disaster recovery, and staff access (the practice must exist; the documents formalising it can come later)
- **[AI-assisted]** Accessibility statement
- **[External]** ISO 27001 (only if enterprise procurement starts asking)

---

## What you can lead on yourself with AI

Several items in the lists above can be drafted by you with AI assistance
using established UK templates from the ICO, HMRC and gov.uk. None of
these require a solicitor's sign-off as a precondition. Asking the
solicitor to do a single 30-minute sweep across the whole batch at the
end (alongside their review of the public documents) is sensible
belt-and-braces but not gating.

In rough priority order — the items most worth starting on while the
solicitor is being engaged:

1. **Data Protection Impact Assessment** — ICO publishes a template.
   Most of the work is describing how Drive CRM processes data, listing
   the risks, and noting the mitigations (encryption, access control,
   audit trail, fraud-prevention headers, retention windows). AI is
   well-suited to structured documentation of this kind.
2. **Record of Processing Activities** — a structured table of every
   processing purpose, lawful basis, retention period and recipient.
   Pure documentation; AI handles it efficiently.
3. **DAC7 due-diligence procedure** — the documented process for
   verifying each instructor's name, address and tax number, and
   storing the verification evidence. Drive CRM already collects most
   of this data; the procedure document formalises what happens, not
   something new.
4. **Breach response playbook, SAR and erasure procedures** — process
   documents that follow well-established UK GDPR patterns. AI can
   produce a strong first version that fits the way Drive CRM actually
   operates.
5. **PCI DSS SAQ A self-assessment** — annual questionnaire. The bulk
   of the technical answers come from Stripe handling card data; AI can
   help walk through each question.
6. **Cyber Essentials questionnaire** (when you decide to pursue it) —
   roughly seventy technical questions; AI can draft answers from the
   platform's actual security posture.
7. **Internal Tier 3 policies** — vulnerability management, change
   control, backup / disaster recovery, staff access, accessibility
   statement. Templated work; AI-friendly.

**A practical note on AI-drafted documents.** The AI gets you most of the
way to a working draft, but each document still has to reflect Drive
CRM's *actual* processing, *actual* retention windows, *actual* breach
contacts and *actual* sub-processors. Treat AI output as scaffolding to
fill in with real specifics, not as a finished policy. Inaccurate
documents are worse than no documents — they set expectations a
regulator will then check you against.

---

## Risks worth being explicit about

**Tax advice.** The single biggest non-technical risk for any
tax-submission product is that an instructor enters wrong figures, the
software submits them, HMRC opens an enquiry, and the instructor argues
"the software told me to." Three controls together remove most of this
exposure:

1. The product never interprets the figures or recommends what to claim.
   Instructors enter their own numbers; Drive CRM submits exactly those
   numbers. The end-of-year Final Declaration (which involves
   interpretive choices) has been deliberately removed from the product
   — instructors download a tax-year archive and hand it to a qualified
   accountant. Drive CRM is software, not a tax adviser.
2. A confirmation screen appears before every submission, captured to
   an immutable audit row.
3. The Terms of Service explicitly disclaim agent and adviser status.

**Data breach exposure.** The data Drive CRM holds is sensitive (UTR,
NINO, financial figures). Mitigations: encryption at rest for tokens
and identifiers, role-based access for any staff with production
access, six-year audit trail, breach response process targeted to meet
the 72-hour ICO notification deadline.

**Future product change to be aware of:** if Drive CRM ever moved to
holding pupil payments outside Stripe Connect (e.g. an in-house wallet
or float), additional regulatory regimes would be triggered (e-money
licensing, possibly FCA-adjacent permissions). The current Stripe
Connect model deliberately avoids that.

---

## What's needed from the business

A short, concrete list of items where the platform team needs the
business to act, decide or fund.

1. **Engage a UK SaaS-and-tax-aware solicitor** — typically 1–2 hours of
   their time to draft the Instructor T&Cs, Pupil T&Cs and Privacy
   Policy, and to confirm in writing that the digital platform reporting
   rules apply (and that the controls in place are appropriate). Rough
   budget: £1–3k. **This is the unblocker** — almost every other
   document references the T&Cs and Privacy Policy, so it sits at the
   front of the queue.
2. **Confirm budget for the launch-pack items** — ICO fee (small), Cyber
   Essentials (~£300/year, valuable signal), insurance review with the
   broker (one conversation), first penetration test (typically
   £3–8k depending on scope, can be done shortly after launch rather
   than before).
3. **Decide on the support contact and response-time commitment** —
   HMRC require a published support email and an SLA before granting
   production access. Needs to be a real address with a real owner.
4. **Sign off the public documents** when the solicitor returns drafts —
   T&Cs, Privacy Policy, Cookie Policy, Refund Policy, Complaints
   Procedure.
5. **Provide breach-contact details** — a named person, phone and email,
   for ICO and HMRC notifications. Sits in the breach runbook.
6. **Confirm the data retention windows are acceptable** — six years
   for tax submission audit (HMRC mandate), five years for
   platform-reporting due-diligence records (DAC7 mandate), shorter
   for marketing data.

None of these requires technical input from the business — they are
budget, decisions, and named contacts.

---

## How this picture changes over time

This document is a snapshot. As work completes, items move from
"recommended" or "must" into "in place." The platform team maintains a
fuller working list internally and updates this summary on request.

**Best moment for the next version of this document:** once the
solicitor has returned the first drafts of T&Cs and Privacy Policy.
That's the point at which a meaningful number of "must-have" items flip
to done and the picture for the business looks materially different.
