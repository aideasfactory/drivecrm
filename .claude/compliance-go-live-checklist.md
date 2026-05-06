# Compliance & Go-Live Checklist

> **Status:** Reference list. Nothing here is in flight. Used to track what
> Drive CRM needs in place — beyond code — before the MTD product can go
> live to paying instructors. Revisit before opening Phase 5 (HMRC
> production access).

---

## Context: why we have two compliance regimes layered together

Drive CRM ends up in scope of **two separate** UK regulatory regimes
because of three product facts:

1. **Stripe Connect facilitates pupil→instructor payment** — pupil books a
   lesson, pays through Stripe, we pay the instructor via Stripe Connect.
   This makes Drive CRM a **Reporting Platform Operator** under the UK
   Digital Platform Reporting rules (DAC7-equivalent, SI 2023/817).
2. **We submit ITSA / VAT to HMRC on the instructor's behalf via the MTD
   APIs.** This makes us a **software vendor** bound by the HMRC Developer
   Hub Terms of Use.
3. **We hold UTR / NINO / VRN** on the `instructors` table for the HMRC
   integration — sensitive personal data with associated UK GDPR
   obligations.

Each regime carries its own document set, its own registrations, and its
own audit obligations. They overlap (privacy policy, breach response, data
retention) but don't substitute for each other.

---

## Launch-readiness tiers

Every concrete item below carries a tier badge. The tiers split items by
*what blocks launch* vs *what's defensible to defer*.

> **Disclaimer:** tier assignments are a practical reading, not a legal
> opinion. Confirm with the solicitor on the consult.

**Tier 1 — Hard block.** Illegal or contractually impossible to launch
without. Either UK law forbids it (UK GDPR, PECR, ICO registration) or a
gateway (HMRC, Stripe, card schemes) won't let us transact.

**Tier 2 — Strong do-before-launch.** ICO can request these and we must
produce on demand. Per-submission attestation and PI insurance sit here
too — not legally mandated for SaaS but irresponsible to launch without.

**Tier 3 — Defensibly defer 3–12 months.** Procurement signals,
documented internal policies, and certifications that are good practice
but not legal pre-conditions. Practice must exist (encryption, MFA,
backups, code review); the *written policies* documenting that practice
can wait.

### Tier 1 — Hard blocks (cannot launch without)

- ICO data controller registration
- Privacy Policy (instructor + pupil)
- Instructor Terms of Service (with Consumer Rights Act + Consumer Contracts Regs + E-commerce Regs disclosures)
- Pupil Terms of Service
- Cookie consent UI + Cookie Policy (PECR)
- Sub-processor DPAs in place (Stripe, hosting, email, error tracking)
- International transfer mechanism (IDTA / SCCs for Stripe US)
- HMRC MTD production access granted
- Fraud prevention headers (Phase 2 — done)
- Stripe Connect platform agreement signed
- PCI DSS SAQ A completed
- DAC7 Reporting Platform Operator registration **and** documented due-diligence procedure running
- DPIA — Data Protection Impact Assessment *(UK GDPR Article 35 requires it "prior to processing" for high-risk processing; Drive CRM clearly qualifies — financial data at scale, combining Stripe + HMRC + internal datasets, processing of UTR/NINO identifiers)*
- ROPA — Record of Processing Activities *(UK GDPR Article 30; ongoing duty that begins the moment processing starts — functionally a launch precondition)*

### Tier 2 — Strong do-before-launch (small window post-launch acceptable)

- Data retention schedule
- Subject Access Request procedure
- Right-to-Erasure procedure (with retention carve-outs)
- Data breach response runbook (72h ICO + 72h HMRC)
- Per-submission attestation UX (HMRC submissions)
- T&Cs versioning + acceptance audit
- Instructor data export + erasure flows
- Professional Indemnity insurance (with software-defect cover)
- Cyber liability insurance
- Documented complaints handling procedure
- Refund policy

### Tier 3 — Defensibly defer 3–12 months post-launch

- Penetration test (annual)
- Cyber Essentials (Plus optional)
- Vulnerability management policy (written)
- Access control policy (written)
- Change management policy (written)
- Backup + DR plan (written — backups themselves are Tier 2)
- Business Continuity Plan (written)
- Acceptable Use Policy (internal)
- Staff background check policy
- Pupil safeguarding policy (move to Tier 1 if under-18s on the platform)
- Instructor onboarding/offboarding policy
- ADI status verification automation
- Accessibility statement (WCAG 2.1 AA)
- Marketing claim review process
- ISO 27001 / SOC 2 (only if procurement asks)

---

## 1. DAC7 — UK Digital Platform Reporting

**Trigger:** Stripe Connect payouts to instructors. Source: *The Platform
Operators (Due Diligence and Reporting Requirements) Regulations 2023*
(SI 2023/817).

| Item | What | Notes |
|---|---|---|
| Register as Reporting Platform Operator | Tell HMRC we are a platform operator and confirm scope of activity | Done via the *Digital Platform Reporting* service on gov.uk. Must be in place before the first reporting period for which we have data |
| Due diligence procedures | Documented process for collecting + verifying each instructor's: full name, primary residence address, TIN (UTR for UK sole traders, also NINO), VAT number where applicable, business reg number for incorporated instructors, financial account identifier (Stripe Connect account ID), jurisdiction of residence | Must be repeatable and auditable. We already collect most of this in the tax profile — gap is the formal verification step and the documented procedure |
| Annual XML report to HMRC | Annual report by **31 January** following each calendar year, listing every reportable instructor and their consideration paid quarter-by-quarter | First report due 31 Jan after the first full calendar year we operate Stripe Connect. Format is HMRC's prescribed XML schema |
| Annual seller notice | Provide each instructor with a copy of the information reported about them, by **31 January** each year | Can be in-app + email. Must mirror exactly what was filed |
| Record retention | Keep all due-diligence records and supporting data for **5 years** from end of the reporting period | Distinct from the 6-year MTD audit retention — different clocks |
| Penalty exposure | Up to £5,000 initial penalty + up to £600 per day for failure to register, file, or carry out due diligence; further penalties for inaccurate reporting | Cited for awareness — confirm current rates with solicitor |
| Sign-up disclosure | Tell each instructor at sign-up what data we collect, why, and that it will be reported to HMRC annually | Goes into the instructor T&Cs and the Privacy Policy |
| Out-of-scope check | Document the DAC7 scope test in writing so we have a defensible position if HMRC ever queries why a given instructor was/wasn't reported | E.g. instructors who only use the CRM for record-keeping and never receive a Stripe Connect payout via the platform are not "sellers" for DAC7 purposes |

---

## 2. HMRC MTD — software vendor obligations

**Trigger:** integrating with HMRC MTD APIs (ITSA + VAT). Source: HMRC
Developer Hub Terms of Use, signed off as part of production access.

| Item | What | Notes |
|---|---|---|
| Production application | Submit production access form on HMRC dev hub: privacy policy URL, T&Cs URL, support email, fraud-headers evidence, screenshots of UX | Phase 5 of current task |
| Fraud prevention headers | Submit headers per HMRC's spec on every API call; validate against the Test Fraud Prevention Headers API before going live | Phase 2 of current task |
| Per-submission attestation UX | Final-screen confirmation before any ITSA/VAT submission: "I confirm the figures are accurate to the best of my knowledge. DRIVE does not provide tax advice." Captured + timestamped against the audit row | Material legal control — protects against the "but the software told me" defence |
| 6-year submission audit retention | Every submission: who, when, what figures, what response from HMRC | Already in plan — retention period set by HMRC |
| Terminology discipline | Use only "HMRC recognised" in marketing — never "approved", "accredited", "endorsed", "certified by HMRC" | Bound by HMRC dev hub terms |
| Security incident notification | Within 72h to HMRC via support ticket, separately within 72h to ICO under UK GDPR | Needs a documented runbook + named breach contact |
| Software-not-advice positioning | T&Cs explicitly disclaim agent status and tax advice; UI avoids interpretive language ("you should claim X", "your allowance is Y") | The current "v1 manual entry is intentional" decision is doing this work — keep it |
| Amendment / error-correction procedure | Documented path for an instructor who finds an error in a filed return | HMRC has a formal amendment API for ITSA — needs UX + procedure |

---

## 3. UK GDPR / Data Protection

**Trigger:** processing personal data of UK residents — both instructors
and pupils. ICO is the regulator.

| Item | What | Notes |
|---|---|---|
| ICO registration | Register as a data controller, pay annual fee | Mandatory; small fee (~£40–£60/year tier most likely) |
| Privacy Policy (public) | What we collect, lawful basis, retention, sub-processors, international transfers, data subject rights, complaints route | Must be linked from sign-up, footer, OAuth consent screen |
| DPIA — Data Protection Impact Assessment | Required for high-risk processing. Tax data + financial data + identity data clearly qualifies | ICO publishes a template; first cut can be done in-house, signed off externally |
| ROPA — Record of Processing Activities | Internal log of every processing purpose, lawful basis, retention, data category, recipients | Living document |
| Sub-processor list + DPAs | Contracts in place with every processor that touches personal data: hosting (Forge / DO), Stripe, email provider, HMRC (controller-to-controller), error tracking, analytics | Each sub-processor named in Privacy Policy |
| Subject Access Request procedure | Documented process to respond to SARs within 1 month | Need a workflow + a person responsible |
| Right-to-erasure procedure | Process for "delete my data" requests, with carve-outs for legally retained records (DAC7 5y, MTD 6y) | UX for instructor-initiated account deletion + audit trail of what's actually deleted vs retained |
| Data retention schedule | Per-data-type retention table: tax data 6y, DAC7 records 5y, marketing data shorter, etc. | Codify in code + policy |
| International transfers | Stripe is US-based; need IDTA / SCCs in place | Stripe provides this; needs signing |
| Lawful basis register | For each processing purpose, the lawful basis (contract / legitimate interest / consent / legal obligation) | Part of the ROPA |
| Data breach response runbook | Triage, severity classification, notification decision tree, draft templates for ICO + HMRC + affected users | Needs on-call rota |
| Cookie policy + consent UI | Cookie banner with granular consent; cookie policy page | Standard ICO requirement |
| DPO determination | Decide whether we appoint a Data Protection Officer or only a "data protection contact" | Likely the latter at current scale; document the reasoning |
| Privacy by design notes | Document the encryption-at-rest of HMRC tokens, role-based access, audit logging — these are GDPR Article 25 evidence | Already in code; needs documenting |

---

## 4. Payments / Stripe Connect

**Trigger:** Stripe Connect platform model.

| Item | What | Notes |
|---|---|---|
| PCI DSS SAQ A | Self-Assessment Questionnaire — short form valid only if all card data flows through Stripe-hosted iframes/Checkout and we never touch raw PAN | Confirm we are SAQ A eligible, complete the SAQ annually, retain |
| Stripe Connect platform agreement | Bound by Stripe's Connected Account Agreement — instructor onboarding flow must surface it | Stripe enforces |
| AML / KYC on connected accounts | Stripe handles the actual KYC on instructors via Stripe Connect onboarding | Document our role: we're not the AML obligor, Stripe is |
| Sanctions / PEP screening | Stripe handles | Document |
| Refund + chargeback procedure | Pupil refund flow, chargeback notification path, who-bears-loss policy in T&Cs | UX + policy |
| Payout failure procedure | What happens when a Stripe Connect payout to an instructor fails or is held | Operational runbook |
| Money handling disclosure | Pupil-facing disclosure that Drive CRM is a platform, the instructor is the merchant of record for the lesson, Drive CRM is the merchant of record for any platform fee | Goes into pupil T&Cs |

---

## 5. Consumer protection

**Trigger:** pupils are consumers buying a service via the platform.

| Item | What | Notes |
|---|---|---|
| Consumer Rights Act 2015 compliance | Service quality, refund, repeat-performance terms in pupil T&Cs | Standard UK SaaS-with-marketplace terms |
| Consumer Contracts Regulations 2013 | Pre-contract disclosures, 14-day cancellation right (with carve-outs for performed services) | Booking flow must surface this |
| E-commerce Regulations 2002 | Identity disclosures (company name, address, registration number) on the site | Footer + T&Cs |
| Complaints procedure | Public complaints policy, response SLA, escalation route | Needs a published page |
| ADR / ODR | EU/UK Online Dispute Resolution route — declare we don't subscribe to a specific ADR scheme, or do | Decide and document |

---

## 6. Insurance

| Item | What | Notes |
|---|---|---|
| Professional Indemnity (PI) | Cover for software defects causing client loss (e.g. wrong figure submitted to HMRC due to a bug, instructor incurs penalty) | Confirm with broker that policy covers software defects, not just professional advice |
| Cyber liability | Cover for breach response, ransomware, business interruption, third-party claims | Often bundled with PI |
| Public liability | Standard | Probably already in place |
| Crime / fidelity (optional) | Cover for staff fraud — relevant since staff have access to instructor financial data | Worth quoting |

---

## 7. Information security

| Item | What | Notes |
|---|---|---|
| Annual penetration test | External pen-test of the production app | HMRC dev hub expects this for SaaS handling tax data |
| Vulnerability management policy | Patch SLAs by severity, dependency scanning, disclosure path | Codify, evidence with tooling output |
| Access control policy | Who can access production data, how access is granted/revoked, MFA mandatory for staff with prod access | Document + audit |
| Change management policy | How code reaches production, code review, deployment approvals, rollback | Mostly informal today; needs writing up |
| Backup + restore + DR | Backup frequency, retention, restore-test cadence, RPO/RTO targets | Document and test |
| Business continuity plan | What happens if hosting / Stripe / HMRC has a multi-day outage on a quarter-end | High value at quarter-end |
| Acceptable Use Policy | Internal — staff use of company systems | Standard |
| Staff background checks | For staff with production / customer-data access — at least basic right-to-work + DBS for safeguarding-adjacent roles | Document |
| Logging + monitoring | Centralised logs, alerting, retention period | Already partially in place |
| Secrets management | Where keys/tokens live, rotation cadence, who has access | Document the existing pattern |

---

## 8. Trust & safety

**Trigger:** instructors are professionals teaching members of the public,
some of whom may be minors (17-year-olds learning to drive is the floor;
theory-test pupils can be younger).

| Item | What | Notes |
|---|---|---|
| Instructor identity verification | Verify ADI registration number against the DVSA register at onboarding | Public DVSA check exists |
| ADI status monitoring | Re-check periodically; suspend platform access if ADI registration lapses | Operational policy |
| Pupil safeguarding policy | If under-18s are on the platform, a safeguarding policy is required — even if we're "just" a CRM | Document the policy + escalation route |
| T&Cs acceptance audit | Versioned T&Cs, immutable record of which version each user accepted and when | Codify in DB; surface on re-acceptance |
| Suspension / offboarding policy | Grounds + process for removing an instructor; data handling on offboarding | Document |

---

## 9. Certifications worth considering

Not all mandatory; in priority order for a UK SaaS at this stage.

| Cert | Worth pursuing? | Why |
|---|---|---|
| Cyber Essentials | Yes — early | UK government scheme, ~£300/year, strong signal, often a procurement requirement, easy to achieve |
| Cyber Essentials Plus | Maybe — year 2 | Audited variant; useful if pursuing public-sector or enterprise customers |
| ISO 27001 | Defer | Heavy lift (~£20k+ first time); only worth it if enterprise procurement starts asking |
| SOC 2 Type II | Skip | US-centric; only relevant if selling to US customers |

---

## 10. Marketing / accessibility

| Item | What | Notes |
|---|---|---|
| Accessibility statement | WCAG 2.1 AA — public statement of conformance with known gaps | Required for some procurement; good practice anyway |
| Cookie consent UI | Granular, default-off non-essential cookies | ICO position |
| Marketing claim review | Pre-publication check for "HMRC approved" / "guaranteed" / "tax advice" framing | Process, not a one-off |

---

## 11. Documents to author (consolidated)

Single list of every document implied above — if it's not written down, it
doesn't exist for an auditor. Most don't exist today.

**Public-facing:**
- [ ] Privacy Policy (instructor + pupil — likely two)
- [ ] Cookie Policy
- [ ] Instructor Terms of Service
- [ ] Pupil Terms of Service
- [ ] Acceptable Use Policy (public summary)
- [ ] Accessibility Statement
- [ ] Complaints Procedure
- [ ] Refund Policy
- [ ] Security & Trust page (overview of measures, breach contact)

**Internal:**
- [ ] DPIA — Data Protection Impact Assessment
- [ ] ROPA — Record of Processing Activities
- [ ] Data Retention Schedule
- [ ] Subject Access Request procedure
- [ ] Right-to-Erasure procedure
- [ ] Data Breach Response Runbook
- [ ] DAC7 Due Diligence Procedure
- [ ] DAC7 Sign-up Disclosure copy
- [ ] HMRC Submission Audit & Amendment procedure
- [ ] Vulnerability Management Policy
- [ ] Access Control Policy
- [ ] Change Management Policy
- [ ] Backup & DR Plan
- [ ] Business Continuity Plan
- [ ] Acceptable Use Policy (internal)
- [ ] Staff Background Check Policy
- [ ] Pupil Safeguarding Policy
- [ ] Instructor Onboarding & Offboarding Policy
- [ ] Sub-processor List + DPAs

**Registrations / submissions:**
- [ ] ICO data controller registration
- [ ] HMRC Reporting Platform Operator registration
- [ ] HMRC MTD production access application
- [ ] PCI DSS SAQ A
- [ ] Cyber Essentials application
- [ ] Stripe Connect platform agreement (sign + retain)

---

## 12. Suggested order of attack (when we get here)

1. **Solicitor consult** — 1–2 hours with a UK SaaS-and-tax-aware
   solicitor to draft the instructor + pupil T&Cs and the Privacy Policy,
   confirm the DAC7 scope read in writing, and flag anything missing from
   this list. Cost ~£1–3k. **Do this first** — most other items reference
   the T&Cs and Privacy Policy.
2. **ICO registration + DPIA + ROPA** — 1–2 weeks of in-house work.
3. **DAC7 registration + due-diligence procedure** — needs to be in place
   before the first reporting period we have data for.
4. **HMRC MTD production application** — gated on a published Privacy
   Policy + T&Cs + support email + fraud-headers evidence.
5. **Cyber Essentials** — quick win, useful procurement signal.
6. **Pen-test** — annual cadence, first one before production launch.
7. **Insurance review** — confirm PI covers software defects, add cyber.
8. **In-product UX work** — per-submission attestation, T&Cs versioning
   audit, instructor data-export & erasure flows, cookie consent.

Heaviest specialist input items: solicitor (T&Cs, Privacy Policy, DAC7
confirmation), pen-tester (annual), insurance broker (one conversation).
Everything else is in-house policy authoring.

---

## What is **not** on this list

- Anything user-facing in the app itself — that's tracked in the
  per-feature task files in `.claude/tasks/`.
- Tax-advice content — explicitly out of scope for Drive CRM as a
  product. We are software, not advisers.
- US compliance (SOC 2, CCPA, etc.) — only relevant if we sell into the
  US, which is not on the roadmap.
