# DRIVE → HMRC ITSA Category Mapping (Strawman for Client Review)

**Status:** Draft — pending client (and ideally an accountant's) sign-off before any code is written.
**Date:** 2026-04-29
**Owner:** sam@aideasfactory.io
**Scope:** Self-employment quarterly updates only (Phase 3). VAT 9-box mapping is in a separate doc.

---

## 1. Why this document exists

DRIVE collects expenses and payments using its own category list (12 expense slugs, 6 payment slugs — see [config/finances.php](../config/finances.php)). HMRC's MTD ITSA API only accepts **15 fixed expense buckets** (or a single `consolidatedExpenses` figure). To submit a quarterly update from DRIVE data automatically, every DRIVE category must be mapped to one of HMRC's 15 buckets — or explicitly excluded.

The purpose of this doc is to **agree the mapping with the client (and ideally an accountant) before any code, schema, or auto-population work begins**. Once the mapping is locked, the implementation is mechanical.

---

## 2. HMRC's expense taxonomy (the universe we have to map into)

Every quarterly self-employment submission to HMRC must use **either**:

### Option A — Consolidated
A single number: `consolidatedExpenses`. Allowed for businesses with turnover under the VAT threshold (~£90k). This is what the current Phase 3 manual entry uses.

### Option B — Itemised (15 buckets)
| HMRC bucket | Plain English |
|---|---|
| `costOfGoods` | Stock bought for resale (not relevant for instructors) |
| `paymentsToSubcontractors` | Other instructors paid to cover lessons, etc. |
| `wagesAndStaffCosts` | Employed staff salaries (rare for sole traders) |
| `carVanTravelExpenses` | **All vehicle running costs** OR mileage allowance — the big one for instructors |
| `premisesRunningCosts` | Office rent, heat/light if home office |
| `maintenanceCosts` | Repairs to premises (not vehicle — that's `carVanTravelExpenses`) |
| `adminCosts` | Phone, internet, stationery, software subscriptions |
| `businessEntertainmentCosts` | **Not deductible** for tax — must be reported but added back |
| `advertisingCosts` | Marketing, lead generation, referral commissions |
| `interestOnBankOtherLoans` | Loan interest |
| `financeCharges` | Bank charges, card fees, finance lease charges |
| `irrecoverableDebts` | Bad debts written off |
| `professionalFees` | Trade body subs, accountant fees, ADI registration |
| `depreciation` | **Not deductible** for tax — must be reported but added back. Capital allowances (AIA / WDA) are claimed separately on the Final Declaration |
| `otherExpenses` | Anything that doesn't fit above |

The two non-deductible buckets (`businessEntertainmentCosts`, `depreciation`) are reported for HMRC's reconciliation but don't reduce taxable profit. We're unlikely to populate them from DRIVE data.

---

## 3. Simplified Expenses vs Actual Costs (the driving-instructor-specific choice)

For vehicle costs, HMRC offers two methods. The instructor must pick one **per vehicle, for the lifetime of that vehicle**. They can choose differently for each vehicle they own, and they can pick again when they buy a new car — but they cannot switch mid-life.

### Method 1 — Simplified Expenses (flat-rate mileage)

**The deal:** HMRC pays you a fixed rate per business mile, and that single number covers *every* running cost the car has — fuel, insurance, MOT, servicing, tyres, repairs, road tax, breakdown cover, depreciation. You don't add them separately; you don't keep receipts for any of them. Just the miles.

**The rates (set by HMRC, unchanged since 2011):**
- **45p per business mile** for the first 10,000 miles in the tax year
- **25p per business mile** thereafter

**What's still claimed separately (on top of the per-mile rate):**
- Parking, tolls, congestion charge (these are travel costs, not running costs)
- Loan interest if the car was financed (up to £500/yr)

**What the instructor has to do day-to-day:**
- Keep a **mileage log** — date, start, end, business miles, brief reason. That's it.
- DRIVE already does this via `mileage_logs`.

**What HMRC needs as evidence (if they enquire):**
- The mileage log. That's the entire audit trail.
- Records can be paper, spreadsheet, or app — DRIVE's logs satisfy this.

**Big restriction:**
- You **cannot** also claim fuel, vehicle insurance, MOT, servicing, repairs, road tax, breakdown cover, or capital allowances/depreciation for the same vehicle. Pick Simplified and those receipts are for your own records only — they don't reduce your tax bill.

---

### Method 2 — Actual Costs

**The deal:** Add up every real running cost during the year, work out what proportion was business use (typically `business miles ÷ total miles`), and claim that proportion. Plus you can claim capital allowances on the purchase price of the car itself.

**What gets summed:**
- Fuel
- Vehicle insurance
- MOT
- Servicing & maintenance
- Repairs (including major unexpected ones — gearbox, clutch, etc.)
- Road tax (VED)
- Breakdown cover (AA, RAC)
- Tyres, wipers, washer fluid, AdBlue
- Lease payments (if leased rather than owned)
- **Plus** capital allowances on the purchase price (claimed on the annual Final Declaration, not quarterly):
  - **AIA (Annual Investment Allowance)** — most cars used by sole traders qualify for full or partial AIA in the year of purchase, depending on CO₂ emissions. Electric cars: 100% first-year allowance.
  - **WDA (Writing Down Allowance)** — for cars that don't qualify for AIA, you write down a percentage of the cost each year (18% main rate or 6% special rate depending on emissions).

**The apportionment:**
- Business-use % = `business miles ÷ total miles`. For a typical full-time instructor that's often 90-98%.
- Every cost above is multiplied by this percentage before adding to expenses.

**What the instructor has to do day-to-day:**
- Keep the mileage log (still needed — it's how the business-use % is calculated).
- **Plus** keep receipts and records for every running cost: every fuel receipt, the insurance schedule, MOT certificate, every garage invoice, road tax confirmation, breakdown renewal.
- Log each receipt into DRIVE's `instructor_finances` against the right category. Today they'd use `fuel`, `insurance`, `mot`. We may add `servicing`, `repairs`, `road_tax`, `breakdown_cover` slugs to make this complete.

**What HMRC needs as evidence (if they enquire):**
- The mileage log (for the business-use %)
- Every receipt for every cost claimed — fuel, insurance schedule, MOT certificate, garage invoices, etc. Six years of retention.
- Logbook proof for the capital allowance claim (V5C, purchase invoice).

**Bonus: capital allowances**
- This is the main reason Actual sometimes beats Simplified. If you buy a £20,000 car and 95% of its use is business, you can potentially claim **up to £19,000** as a deduction in year one (subject to AIA rules and CO₂ emissions). Simplified gives you £0 for the purchase price — the per-mile rate is supposed to include depreciation.

---

### Side-by-side comparison

| | Simplified | Actual |
|---|---|---|
| **Record-keeping** | Mileage log only | Mileage log + every receipt |
| **Receipts to retain** | None for vehicle costs | All vehicle costs, 6 years |
| **Time cost / yr** | ~30 min/qtr (just logging miles) | ~2-4 hrs/qtr (logging + filing receipts) |
| **HMRC enquiry exposure** | Low — flat rate, hard to argue with | Medium — every line item is a potential question |
| **Captures purchase cost** | ❌ No | ✅ Yes (AIA / WDA) |
| **Captures big repair year** | ❌ No (you eat the cost) | ✅ Yes |
| **Works with EVs well** | ⚠️ OK but loses ground (EV running costs are tiny) | ✅ Strong (full first-year allowance + cheap running) |
| **Can switch later** | ❌ Lifetime per vehicle | ❌ Lifetime per vehicle (but new car = new choice) |

---

### When does each method actually win? (worked scenarios)

All five scenarios assume 95% business use (typical for an ADI's tuition car).

#### Scenario A — Average full-time instructor, settled mid-life car
**28,000 business miles, 5-year-old hatchback, no major repairs, no purchase that year.**
Costs: £3,500 fuel + £1,400 insurance + £55 MOT + £600 servicing + £400 repairs + £180 road tax = £6,135 × 95% = £5,828.

| Method | Deduction |
|---|---|
| Simplified | **£9,000** ((10k × 45p) + (18k × 25p)) |
| Actual | £5,828 |

🏆 **Simplified wins by ~£3,200.** Most common scenario for established instructors.

#### Scenario B — Part-time instructor, low miles
**10,000 business miles, similar car.**
Costs: £1,400 fuel + £1,200 insurance + £55 MOT + £400 servicing + £180 road tax = £3,235 × 95% = £3,073.

| Method | Deduction |
|---|---|
| Simplified | **£4,500** (10k × 45p) |
| Actual | £3,073 |

🏆 **Simplified wins.** Even at low miles, the 45p rate generally beats actual running costs.

#### Scenario C — Brand new car bought this year (£22,000)
**28,000 business miles, otherwise as Scenario A.**
Costs: same £6,135 × 95% = £5,828, **plus** AIA-qualifying capital allowance: £22,000 × 95% = £20,900.

| Method | Deduction |
|---|---|
| Simplified | £9,000 |
| Actual | **£26,728** (£5,828 + £20,900 capital allowance) |

🏆 **Actual wins by ~£17,700** *in year one only*. From year two onwards the capital allowance shrinks dramatically and Simplified usually overtakes again. **This is the single biggest reason to consider Actual.**

#### Scenario D — Major unexpected repair year
**28,000 business miles, gearbox replacement £2,800.**
Costs: £6,135 + £2,800 = £8,935 × 95% = £8,488.

| Method | Deduction |
|---|---|
| Simplified | £9,000 |
| Actual | £8,488 |

🤝 **Roughly even.** Actual catches up but doesn't overtake. The instructor would need to have predicted the repair to choose Actual — the lifetime lock means they're stuck with whatever they picked years ago.

#### Scenario E — Electric vehicle
**28,000 business miles, EV bought new for £30,000, ultra-low running costs.**
Costs: £700 home charging + £1,300 insurance + £55 MOT + £150 servicing + £0 road tax = £2,205 × 95% = £2,095. Plus 100% first-year EV allowance: £30,000 × 95% = £28,500 in year one.

| Method | Year 1 deduction | Year 2+ deduction |
|---|---|---|
| Simplified | £9,000 | £9,000 |
| Actual | **£30,595** | £2,095 |

🏆 **Actual wins year 1 massively, Simplified wins every year after.** EV scenario shows the lock-in trap most starkly — pick Actual for the £30k year-one win, but you're locked into Actual for years 2-7+ where you're claiming ~£2k vs the £9k you could have had on Simplified. **Net over 7 years: Simplified is usually still better unless you change car every 3-4 years.**

---

### So which should DRIVE recommend?

**My recommendation: default-suggest Simplified, but make it an informed decision per vehicle.**

Why:
- Scenarios A, B, D, E (years 2+) all favour Simplified — that's the majority of years for the majority of instructors.
- Simplified is dramatically less work — one mileage log vs. six years of receipt-hoarding.
- Lower HMRC enquiry exposure.
- It's what most accountants recommend for this profession unless the instructor changes vehicles frequently.

The exception is **the year of buying a new car** — if the instructor is about to spend £15-30k on a new tuition vehicle, Actual could be £5-25k more deductible *in that year*. But they're then locked into Actual for the life of that vehicle, which usually loses ground from year two.

**The DRIVE UX I'd build:**
1. On first connecting to HMRC, ask "Have you bought a new car in the current tax year, or do you plan to in the next 12 months?"
2. If **No** → recommend Simplified with one-click confirm. Show the comparison panel for transparency but pre-select Simplified.
3. If **Yes** → show the comparison panel prominently with both year-1 and projected-year-2-onwards numbers. Tell them this is a decision to take with their accountant if they have one. Don't auto-suggest — make them tick.
4. Surface a permanent reminder on the tax profile: *"This vehicle is on the Simplified / Actual method. Locked for the life of this vehicle. When you replace it, you can choose again."*
5. Build a "What if I'd chosen the other method?" report at year-end so the instructor can see how their choice played out and inform their *next* car decision.

---

### Open questions for the client

1. **Do most of your instructors change vehicles often (e.g. every 2-3 years)?** If yes, Actual becomes more attractive because they hit the AIA bonus more frequently. If they keep cars 5+ years, Simplified is almost always the right call.
2. **Should the choice be locked in code once a quarter has been submitted, or stay editable with a strong warning?** My instinct is **soft lock with warning** — DRIVE is not the source of truth for HMRC; the instructor is, and they may need to correct an early mistake. But the warning needs teeth.
3. **Should DRIVE explicitly recommend Simplified to all instructors, or stay neutral and just show the comparison?** Recommending creates a (small) liability if an instructor would have done better on Actual. Staying neutral is safer but less useful.

---

## 4. The strawman mapping

### 4.1 Expense categories ([config/finances.php:18-31](../config/finances.php#L18-L31))

| DRIVE category | HMRC bucket | Claimable? | Notes / open questions |
|---|---|---|---|
| `none` | — | n/a | Excluded from HMRC payload |
| `our_account` | — | n/a | Internal accounting; never sent to HMRC |
| `advertising` | `advertisingCosts` | ✅ | Direct match |
| `association` | `professionalFees` | ✅ | ADINJC, MSA, IAM RoadSmart subs |
| `bank_charges` | `financeCharges` | ✅ | Account fees, card processing fees |
| `computer_dvsa_fees` | `professionalFees` | ✅ | ADI registration (£300 every 4 yrs), Standards Check, CRB. **Open question:** are pupil theory/practical test fees included here? Those should be re-charged to the pupil and excluded from the instructor's expenses. Need a sub-flag or rename. |
| `equipment` | `otherExpenses` (revenue) or `depreciation` (capital) | ✅ | Cones, dashcam, L-plates, dual controls. **Open question:** what's the cut-off — anything over £X capitalised? HMRC AIA covers most of this anyway; suggest treating as revenue if individual item < £200. |
| `food_drink` | — | ❌ | **Recommend dropping the slug entirely.** Subsistence is only allowable on overnight stays / temporary workplaces — not relevant to a driving instructor's typical day. Keeping it implies it's deductible. |
| `fuel` | `carVanTravelExpenses` | ✅ if Actual; ❌ if Simplified | Method-dependent (see §3). If Simplified is chosen for the vehicle, fuel rows must be excluded from the HMRC payload (still recorded in DRIVE for the instructor's own reference). |
| `insurance` (vehicle) | `carVanTravelExpenses` | ✅ if Actual; ❌ if Simplified | Same rule as fuel |
| `insurance` (business / PI / public liability) | `otherExpenses` or `premisesRunningCosts` | ✅ | Always claimable regardless of vehicle method. **This is why we need to split `insurance` into two categories** — see §5. |
| `internet` | `adminCosts` | ✅ (apportioned) | Business-use % only. Most instructors should claim a fraction (e.g. 50%), not the full bill. |
| `mot` | `carVanTravelExpenses` | ✅ if Actual; ❌ if Simplified | Same rule as fuel |

### 4.2 Payment categories ([config/finances.php:33-40](../config/finances.php#L33-L40))

These are money flowing **out** that aren't general expenses — they need separate treatment.

| DRIVE category | HMRC bucket | Claimable? | Notes |
|---|---|---|---|
| `none` | — | n/a | Excluded |
| `franchise_payout` | `professionalFees` or `paymentsToSubcontractors` | ✅ | Depends on contract structure — most ADI franchise fees are `professionalFees`. **Open question for client:** is the franchise fee a flat weekly fee, or is it commission per-pupil? If commission-style, `paymentsToSubcontractors` may fit better. |
| `hmrc_tax` | — | ❌ | **Income tax is never an allowable expense.** Excluded. |
| `insurance` | (same split as expense) | ✅ | Same routing as expense `insurance` after split |
| `referral` | `advertisingCosts` | ✅ | Commission paid for new pupil intro |
| `pupil_transfer_referral` | `advertisingCosts` | ✅ | Same — commission for taking on another instructor's pupil |

### 4.3 Mileage logs

`mileage_logs` only feed the HMRC payload **when the vehicle's method is Simplified**. Calculation:

```
business_miles_in_period_first_10k × £0.45
+ business_miles_in_period_after_10k × £0.25
→ added to carVanTravelExpenses for the period
```

The 10k threshold is **per tax year**, not per quarter. So Q1 might be all at 45p, Q3 might split, Q4 might be all at 25p. The system needs a running tally of business miles across the tax year per vehicle.

When the vehicle's method is Actual, mileage logs are **not** sent to HMRC — they're only used for the business-use % apportionment of fuel/insurance/MOT/repairs.

---

## 5. Schema / config changes implied by this mapping

In plain English (no code yet — this is for the client conversation):

1. **Add `simplified_vs_actual` to the tax profile** — per vehicle, lifetime decision, with a `decision_made_at` timestamp.
2. **Split `insurance` in `config/finances.php`** into `vehicle_insurance` and `business_insurance` (clearer than a sub-flag). Migrate any existing rows by best-guess (probably default to vehicle, prompt instructor to confirm).
3. **Drop `food_drink` from the picker.** Existing historical rows can stay tagged for the instructor's own records but get excluded from HMRC payloads.
4. **Add `vehicle_id` linking to `instructor_finances` rows** for fuel/insurance/MOT/servicing — so the system knows which vehicle's method to apply. (Currently a single instructor probably has a single vehicle, but two-car households exist.)
5. **Add a `category_tax_mapping` config block** — the table in §4.1 / §4.2 in machine-readable form. Lives in `config/hmrc.php` so it's version-controlled.
6. **Add per-period running totals** for the 45p/25p threshold tracking.
7. **For VAT (separate doc):** add `vat_treatment`, `vat_rate`, `vat_amount_pence` columns to `instructor_finances`.
8. **Soft-delete + retention lock on `instructor_finances`** — once a row has been part of an HMRC submission (any quarterly update or Final Declaration for the period it falls in), it cannot be hard-deleted from DRIVE. State machine:
   - **Draft** (not yet submitted): full edit + delete allowed
   - **Submitted, year not final-declared**: edit allowed (creates audit revision; HMRC re-PUT amendment); delete blocked
   - **Final-declared**: edit + delete both blocked; row + receipt frozen until 6-yr retention expires
   - **Beyond retention** (6 years from end of tax year): eligible for purge by background job
   The `destroy` endpoint and the Vue delete button must enforce this. Receipts on disk follow the same lifecycle — never deleted while their parent row is locked.
9. **Tax-year archive download** — self-serve "Download tax year archive" button on each completed tax year. Async job → email when ready → signed URL (24-hr TTL). ZIP contains: `finances.csv`, `mileage.csv`, `receipts/Q[1-4]/` (original files filed by quarter), `submissions/` (HMRC payload JSON + correlation ID + response per submission), `summary.pdf` cover sheet. Year-bounded only in v1 — multi-year and custom date range deferred. Used both for routine year-end accountant handover and for HMRC enquiry response packs.

---

## 6. Open questions to resolve with the client

Tagged for client conversation:

1. **Pupil test fees.** Are instructors fronting DVSA test fees and being reimbursed by the pupil? If so, those need to be excluded from HMRC expenses (they're a pass-through, not a cost).
2. **Capital threshold.** Below what value should equipment be treated as revenue (`otherExpenses`) vs capital (`depreciation` + AIA)? Suggest £200 per item.
3. **Franchise fee structure.** Flat fee or commission? Affects `professionalFees` vs `paymentsToSubcontractors`.
4. **Method-switch lock.** Hard-lock `simplified_vs_actual` once the first quarter has been submitted, or soft-lock with a warning?
5. **Drop `food_drink` entirely or keep for historical record?** My recommendation: drop from the picker, leave existing rows untouched, exclude from HMRC payload.
6. **Multi-vehicle.** Confirm whether DRIVE needs a `vehicles` table, or whether instructors are single-vehicle in practice. (Insurance-side consideration: tuition vehicles are typically the only car they own anyway.)
7. **Phone bills.** Currently no DRIVE category for phone. Most instructors should claim a fraction of their mobile bill. Add `phone` slug → `adminCosts`?
8. **Accountant fees.** No DRIVE category. Add `accountant_fees` → `professionalFees`?
9. **Pre-population philosophy.** When auto-derived numbers populate the Phase 3 form, do we lock the field, allow override-with-badge, or allow override-no-badge? Recommend **override-with-badge + reason field if they change it** (audit trail).

---

## 7. Worked end-to-end example (for the client)

Instructor: full-time ADI, sole trader, single tuition car (Simplified method chosen), 28,000 business miles/yr, ~£42k turnover. Q1 (Apr–Jul) sample.

**Inputs from DRIVE:**
- `mileage_logs` Q1 business miles: 7,200 (all under 10k threshold so all at 45p)
- `instructor_finances` Q1 expenses:
  - Advertising: £180
  - Association (ADINJC): £45
  - Bank charges: £35
  - Computer/DVSA fees (CRB renewal): £20
  - Equipment (new dashcam): £120
  - Fuel: £820 ❌ excluded (Simplified)
  - Vehicle insurance: £350 ❌ excluded (Simplified)
  - Business insurance (PI): £55 ✅ included
  - Internet (50% business): £45 (gross £90)
  - MOT: £55 ❌ excluded (Simplified)
- `instructor_finances` Q1 income (turnover): £10,500

**HMRC payload:**
```json
{
  "periodIncome": { "turnover": 10500.00, "other": 0 },
  "periodExpenses": {
    "advertisingCosts": 180.00,
    "professionalFees": 65.00,         // association + DVSA CRB
    "financeCharges": 35.00,
    "otherExpenses": 175.00,            // equipment + business insurance
    "adminCosts": 45.00,
    "carVanTravelExpenses": 3240.00     // 7200 × 0.45
  }
}
```

Notice: 6 buckets populated, 5 DRIVE categories excluded (because Simplified suppresses vehicle-running rows). The user sees the calculated payload in `Period.vue` with each bucket's "calculated from your records" badge and is free to override any number.

---

## 8. What happens after sign-off

Once this mapping is approved (client + ideally accountant):

1. The tables in §4 become the canonical source — moved into `config/hmrc.php`.
2. Schema changes from §5 land as one migration.
3. `Period.vue` gets a "Pre-populate from records" button that runs the derivation on the open period and fills the form.
4. The Final Declaration flow (Phase 3.5) reuses the same mapping — annual roll-up of the four quarters.
5. VAT mapping (separate doc, separate sign-off) layers on `vat_treatment` columns and 9-box derivation.

**No code lands until §6 is resolved.**
