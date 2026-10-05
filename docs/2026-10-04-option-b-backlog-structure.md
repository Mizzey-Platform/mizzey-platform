# Option B delivery backlog: the structure

Created 4 October 2026 at the close of the product-cost pilot as a proposal, and still the full proposed
decomposition of Option B. Its **status has moved on**, and only that has changed here:

| | State |
|---|---|
| This document | The **full proposed decomposition** of Option B, over the 595 delivery rows. Unchanged in substance |
| The delivery board | **Active**: https://github.com/orgs/Mizzey-Platform/projects/1, "Mizzey Option B Delivery" |
| PBIs created | **The first 15 only**, #241 to #255, recorded in `docs/2026-10-04-github-project-proposal.md` |
| PBIs still uncreated | The remaining **45 to 55**, which await review before any are seeded |
| Specs created | **None.** A Spec Kit feature is opened per PBI when its work starts |

So the epics and slices below are not all live work: most of them are still a plan. Where this document and the
board disagree about a seeded PBI, **the board is right**, and the board's own record is the project document
named above.

## What this is built from

| Source | Role |
|---|---|
| Feature Register **MS-ANX-2026-006 v1.5** (contract pack MZ-02 REV4) | The only definition of scope |
| `docs/scope/register-ids.json` | The authoritative scope and stage per id, extracted from that register and verified against the signed PDF (754 ids) |
| `docs/scope/open-items.json` | CX-01 and the PRE-09 gate |
| `specs/001-product-cost-capture/` | The one completed feature, and the workflow this backlog assumes |
| `discovery/data/probes.json`, `verdicts.json` | 21 probes and 73 verdict rows of measured platform behaviour |

**Option C is history.** `scripts/stories.json` (205 stories, 27 epics, stages S1 to S6) and Project #4 were
written against the unsigned Option C pack. They are kept as reference for wording and for the leverage
classification, and they are not the Option B backlog. Option B has two stages, S1 and S2, a 9 to 11 week
timeline, and includes an ERP stock integration that Option C did not. Commercial terms are not recorded here:
this document covers scope, stages, dependencies and acceptance only.

## Scope included, and scope excluded

Delivery rows only: **P1, P1-L, P1-E, DLV**.

| Scope | Rows | Meaning here |
|---|---|---|
| P1 | 435 | Live at launch, full specification |
| P1-L | 117 | Live at launch, reduced specification. The reduction is in the row's own wording |
| P1-E | 19 | Contracted as an outcome; the method is pending PRE-09. **No final acceptance criteria before PRE-09 is approved** (constitution M-3) |
| DLV | 24 | Deliverables: **10** pre-development (PRE-01, PRE-02, PRE-03a, PRE-03b, PRE-04 to PRE-09), 12 handover (HND-01 to HND-12), plus EVT-20 and MKT-26 |
| **Total** | **595** | Of which 61 are marked `[key]` in the register |

Not delivery items, and not in this backlog unless separately approved: **DEF** (44), **P2** (74), **P3** (32),
**OUT** (9). Where one of them is adjacent to delivery work it is named in the slice as an explicit boundary, so
that nobody builds it by accident.

Stage distribution of the delivery rows: **S1** 562, **S2** 14 (all P1-L), and 19 rows carrying `-` or
`Per PRE-09` because their stage follows the ERP specification.

## Where the delivery-row total comes from, and how it is kept honest

**The authoritative total is 595**, recomputed from `docs/scope/register-ids.json`:
P1 435, P1-L 117, P1-E 19, DLV 24.

An earlier draft of this document gave the epic table a DLV column of 23 and a grand total of 594, against a
summary that said 595. **The cause was a single hand-counted series.** The pre-development deliverables are
labelled PRE-01 to PRE-09, which reads as nine rows, but the register splits PRE-03 into **PRE-03a** and
**PRE-03b**, so the series holds ten ids. The epic table and the DLV composition sentence both inherited the
nine, and nothing compared either of them with the extraction. Verified against the data rather than reasoned
about: the 24 DLV ids are 10 PRE, 12 HND, EVT-20 and MKT-26.

Nothing else was wrong. The Part Three sections account for 573 delivery rows (435 + 117 + 19 + 2) and Part Seven
for the remaining 22 (10 PRE + 12 HND), which is 595.

**`tools/tests/test_backlog_totals.py` now checks it**, through the tooling unit-test step CI already runs. It
recomputes the per-scope delivery totals from `register-ids.json` and fails if the scope summary, any epic row,
the epic column sums or the Total row disagree with it or with each other. Eight cases, including the exact drift
that occurred, a row whose columns do not sum, a wrong scope summary and a reissued register. It reproduced the
594-against-595 drift before the correction, which is how it was confirmed to work:

```
docs/2026-10-04-option-b-backlog-structure.md: epic rows sum to 23 for DLV, extraction says 24
docs/2026-10-04-option-b-backlog-structure.md: epic rows sum to 594 delivery rows, extraction says 595
docs/2026-10-04-option-b-backlog-structure.md: epic table Total row says 594, extraction says 595
backlog-totals: 3 problems
```

When the register is reissued, `tools/extract_register_ids.py` regenerates `register-ids.json`, this test fails
until the tables here are brought into line, and the failure names each disagreement. The check lives in the test
rather than in `tools/` because a `requirement` PR may touch `tools/tests/` and not `tools/*.py`, which the path
policy enforces and which was verified against the checker.

## Epic structure

The epics follow the register's own sections, not a technical layering. A register section is a coherent
contractual unit, which means an epic maps to something the client can recognise and accept.

| Epic | Register section | P1 | P1-L | P1-E | DLV | Total | Key rows |
|---|---|---|---|---|---|---|---|
| E-FND | A. Foundation (vision, platform type, core decisions, journey, information architecture) | 58 | 12 | 0 | 0 | 70 | 4 |
| E-ROLE | B. Users, roles and permissions | 4 | 1 | 0 | 0 | 5 | 0 |
| E-SF | C. Storefront (header, home, listing, search, PDP, cart, auth, checkout, account, wishlist, reviews, trust pages) | 139 | 13 | 0 | 0 | 152 | 7 |
| E-RULES | D. Business rules (promotion engine, launch rules, merchandising) | 34 | 0 | 0 | 0 | 34 | 6 |
| E-ORD | E. Orders, payments, shipping and returns | 29 | 10 | 0 | 0 | 39 | 7 |
| E-NOTF | F. Notifications | 7 | 3 | 0 | 0 | 10 | 0 |
| E-ADM | G. Admin panel (dashboard, products, categories, inventory, orders, customers, promotions, returns, users, Operations Console boundary, self-service storefront control) | 77 | 33 | 8 | 0 | 118 | 7 |
| E-RPT | H. Reports and analytics | 1 | 4 | 0 | 0 | 5 | 1 |
| E-INT | I. Integrations (Paymob, Bosta, email, GA4, Meta, Merchant Center, adapter rule) | 5 | 2 | 0 | 0 | 7 | 1 |
| E-ERP | I2. ERP stock integration | 5 | 0 | 4 | 0 | 9 | 3 |
| E-NFR | J. Non-functional requirements | 13 | 4 | 0 | 0 | 17 | 2 |
| E-DATA | K. Data model | 12 | 4 | 1 | 0 | 17 | 1 |
| E-EVT | L. Analytics events | 14 | 3 | 0 | 1 | 18 | 0 |
| E-DOD | M. Definition of done | 8 | 1 | 0 | 0 | 9 | 0 |
| E-ACC | N. Acceptance scenarios | 18 | 2 | 5 | 0 | 25 | 5 |
| E-MIG | U. Data migration and catalogue import | 0 | 16 | 1 | 0 | 17 | 0 |
| E-MKT | V. Marketing team enablement (measurement, SEO, third-party access) | 11 | 9 | 0 | 1 | 21 | 7 |
| E-PRE | Part Seven, pre-development deliverables (PRE-01 to PRE-09, ten rows: PRE-03 is split into PRE-03a and PRE-03b) | 0 | 0 | 0 | 10 | 10 | - |
| E-HND | Part Seven, handover deliverables (HND-01 to HND-12) | 0 | 0 | 0 | 12 | 12 | - |
| **Total** | | **435** | **117** | **19** | **24** | **595** | 51 |

E-ACC and E-DOD are not build epics. They carry the acceptance scenarios and the definition of done, and they
become the evidence requirement attached to every other slice. E-PRE and E-HND are deliverables with dates, not
features.

**This table counts by register section.** The ownership map at the end of this document counts by slice, and a
slice sometimes owns a row from a neighbouring section where the outcome is the same. Both account for the same
595 rows, and `tools/tests/test_backlog_totals.py` checks both.

## PBI shape

A PBI is a **vertical slice**: one customer-visible or operator-visible outcome, through every layer it needs,
with its own acceptance evidence. Not "the data layer for X" followed by "the UI for X".

The fields are the ones the live board defines, and `docs/2026-10-04-github-project-proposal.md` records their
definitions as the Project holds them. **That document is the authority for the field model; this one repeats it
because this is where the remaining 45 to 55 PBIs will be drafted.**

| Field | Values | How it is set |
|---|---|---|
| Register ids | The exact delivery ids, in full | The only authority for scope. **Never a range.** See the next section |
| Scope class | `P1`, `P1-L`, `P1-E`, `DLV`, `mixed` | **Computed mechanically from the cited ids**: one distinct delivery scope among them, that scope; more than one, `mixed`. Never chosen from what the PBI is for |
| Stage | The literal stage set of the cited rows: `S1`, `S2`, `mixed`, `per PRE-09` | **Read from the register.** One distinct value, that value; more than one, `mixed`. A row the register leaves unstaged keeps its own value and is not promoted to S1. Engineering scheduling belongs in **Delivery order**, never here |
| ERP blocked | `no`, `partial`, `yes` | **A dependency field, not a scope field.** `no`: no unresolved ERP or PRE-09 dependency prevents it. `partial`: part can proceed, some behaviour or final acceptance waits. `yes`: it materially depends on the ERP meeting or PRE-09 |
| Design dependency | yes, no | OD-01 blocks all design, therefore every storefront slice |
| Data-integrity dependency | The workstream item | From `docs/2026-10-04-multilingual-data-integrity-workstream.md`, measured before the slice is accepted |
| Client decision | The CX or OD id | Empty for most PBIs |
| Acceptance evidence | The AC rows plus the local scenario | What will show the outcome was reached |
| Delivery order | A number | **Engineering scheduling.** It never moves a contractual stage |
| Depends on | Real GitHub issue numbers | Never a delivery-order number: on GitHub `#1` is a historical Option C issue |

**`ERP blocked` is not inferred from P1-E.** Scope class already records scope. A PBI citing no P1-E row can still
be fully ERP blocked, and #243 is the live proof: it is `yes` while citing a **single DLV row, PRE-09**, because the
specification is what the ERP meeting produces.

Three of those rules are written the way they are because the first fifteen PBIs got them wrong, and external
review caught it: Scope class was chosen from the PBI's purpose on five of fifteen, Stage was derived from Scope
class, and `ERP blocked` carried a `yes (P1-E)` option that encoded a scope class. The correction record is in the
board document. The earlier wording in this document, `Stage (S1 / S2 / per PRE-09)` and
`ERP blocked: Yes if any id is P1-E`, is what produced two of the three, and it is replaced above.

**One row, one accepting owner.** A register row is cited by the PBI whose acceptance closes it. A second PBI that
needs the row names it as a **dependency or a boundary** and does not cite it, so the board can always say which
acceptance closed which row.

## Range shorthand is section context, never a scope citation

`IA-01 to IA-36` reads like 36 rows of scope. In the register it is a **section**: five of those ids are P2 or
DEF, two more are contracted but staged S2, and the section does not even contain all 36 numbers. A range is
therefore safe as a description of where work sits and unsafe as a statement of what a PBI owns.

This is not hypothetical. Every line below was found and corrected on the first fifteen PBIs:

| What a range swept in | Where it was caught |
|---|---|
| P2 rows | IA-36, CHK-13, PDP-20, PDP-23, ROLE-06a, ADM-76, HOME-12 |
| DEF rows | ORD-09, ORD-10, ORD-11, five ROLE rows, IA-22, ADM-147 |
| P3 rows | ROLE-11, HOME-13, ADM-78 |
| An S2 row inside a Stage 1 PBI | IA-23 and IA-33, removed from #242, which moved its exact-id total from 131 to 129 |
| An id the range names that the register does not contain | `AUTH-01 to AUTH-17`: the series ends at AUTH-16. `SSC-01 to SSC-24`: the series is SSC-01 to SSC-14 then SSC-20 to SSC-29 |
| A count taken from a range rather than from the data | `ADM-70 to ADM-77`, called "all 8 are P1-E". G4 holds seven delivery rows; the eighth P1-E row is ADM-149, in G10 |

**The rule for every future PBI drafted from this document.** A slice may describe itself by section. It must also
state the **exact delivery ids it owns**, and name what the section holds that it does not own. Where a section's
delivery rows split across stages, the slice says which ids it owns and **which slice the rest belong to**. No row
is left without a home: that is what the ownership map at the end of this document is for, and what CI checks.

### Exact delivery ids per register section

Generated from `docs/scope/register-ids.json` and the register's own section structure, so a future PBI is drafted
from data instead of from a range. **Scope class and Stage here are computed over the whole section**; a slice that
owns part of a section recomputes both over the ids it actually cites.

- **A1. Vision and Scope** (6 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `VIS-01, VIS-02, VIS-03, VIS-04, VIS-05, VIS-06`
- **A2. Business Objectives** (7 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `OBJ-01, OBJ-02, OBJ-03, OBJ-04, OBJ-05, OBJ-06, OBJ-07`
  - **not S1**: OBJ-06 (S2, P1-L), OBJ-07 (S2, P1-L)
- **A3. Platform Type** (4 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `PLT-01, PLT-02, PLT-03, PLT-06`
  - in the section but **not delivery**: PLT-04 (OUT), PLT-05 (OUT), PLT-07 (OUT), PLT-08 (P3), PLT-09 (P2)
- **A4. Fixed Core Decisions** (11 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `FIX-01, FIX-02, FIX-03, FIX-04, FIX-04a, FIX-05, FIX-06, FIX-07, FIX-08, FIX-09, FIX-10`
- **A5. Customer Journey** (11 delivery rows, scope `mixed`, stage `-`)
  - delivery ids: `JRN-01, JRN-02, JRN-03, JRN-04, JRN-05, JRN-06, JRN-07, JRN-08, JRN-09, JRN-10, JRN-11`
  - in the section but **not delivery**: JRN-12 (P3)
  - **not S1**: JRN-01 (-, P1), JRN-02 (-, P1), JRN-03 (-, P1), JRN-04 (-, P1), JRN-05 (-, P1-L), JRN-06 (-, P1), JRN-07 (-, P1), JRN-08 (-, P1-L), JRN-09 (-, P1), JRN-10 (-, P1), JRN-11 (-, P1-L)
- **A6. Information Architecture** (31 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `IA-01, IA-02, IA-03, IA-04, IA-05, IA-06, IA-07, IA-08, IA-09, IA-10, IA-11, IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20, IA-21, IA-23, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32, IA-33, IA-35`
  - in the section but **not delivery**: IA-12 (P2), IA-13 (P2), IA-22 (DEF), IA-34 (P2), IA-36 (P2)
  - **not S1**: IA-23 (S2, P1-L), IA-33 (S2, P1-L)
- **SECTION B: USERS, ROLES and PERMISSIONS** (5 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ROLE-01, ROLE-02, ROLE-07, ROLE-08, ROLE-09`
  - in the section but **not delivery**: ROLE-03 (DEF), ROLE-04 (DEF), ROLE-05 (DEF), ROLE-06 (DEF), ROLE-06a (P2), ROLE-10 (DEF)
- **C1. Header and Navigation** (10 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, NAV-07, NAV-08, NAV-09, NAV-10`
- **C2. Home Page** (11 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `HOME-01, HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-07, HOME-08, HOME-09, HOME-10, HOME-11`
  - in the section but **not delivery**: HOME-12 (P2), HOME-13 (P3)
- **C3. Product Listing, Category and Collection** (22 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `PLP-01, PLP-02, PLP-03, PLP-04, PLP-05, PLP-06, PLP-07, PLP-08, PLP-09, PLP-10, PLP-11, PLP-12, PLP-13, PLP-14, PLP-15, PLP-16, PLP-17, PLP-18, PLP-19, PLP-20, PLP-21, PLP-22`
  - **not S1**: PLP-11 (S2, P1-L)
- **C4. Search** (9 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-05, SRCH-06, SRCH-06a, SRCH-07, SRCH-08`
  - in the section but **not delivery**: SRCH-09 (P2)
- **C5. Product Details Page** (22 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `PDP-01, PDP-02, PDP-03, PDP-04, PDP-05, PDP-06, PDP-07, PDP-08, PDP-09, PDP-10, PDP-11, PDP-12, PDP-13, PDP-14, PDP-15, PDP-16, PDP-17, PDP-18, PDP-19, PDP-21, PDP-22, PDP-24`
  - in the section but **not delivery**: PDP-20 (P2), PDP-23 (P2)
- **C6. Cart** (18 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `CART-01, CART-02, CART-03, CART-04, CART-05, CART-06, CART-07, CART-08, CART-09, CART-10, CART-11, CART-12, CART-13, CART-14, CART-15, CART-16, CART-17, CART-18`
- **C7. Authentication** (16 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09, AUTH-10, AUTH-11, AUTH-12, AUTH-13, AUTH-14, AUTH-15, AUTH-16`
  - in the section but **not delivery**: AUTH-10a (P2)
- **C8. Checkout** (12 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `CHK-01, CHK-02, CHK-03, CHK-04, CHK-05, CHK-06, CHK-07, CHK-08, CHK-09, CHK-10, CHK-11, CHK-12`
  - in the section but **not delivery**: CHK-13 (P2)
- **C9. My Account** (12 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `ACCT-01, ACCT-02, ACCT-03, ACCT-04, ACCT-05, ACCT-06, ACCT-07, ACCT-08, ACCT-09, ACCT-11, ACCT-12, ACCT-13`
  - in the section but **not delivery**: ACCT-10 (DEF)
  - **not S1**: ACCT-09 (S2, P1-L), ACCT-11 (S2, P1-L), ACCT-13 (S2, P1-L)
- **C10. Wishlist, Recently Viewed and Compare** (5 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `WISH-01, WISH-02, WISH-03, WISH-04, WISH-07`
  - in the section but **not delivery**: WISH-05 (P2), WISH-06 (P2), WISH-08 (P2)
- **C11. Reviews and QandA** (5 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `REV-01, REV-02, REV-03, REV-04, REV-05`
  - in the section but **not delivery**: REV-06 (P2), REV-07 (P2), REV-08 (P2), REV-09 (P2)
- **C12. Trust and Support Pages** (10 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `CMS-01, CMS-02, CMS-03, CMS-04, CMS-05, CMS-06, CMS-07, CMS-08, CMS-09, CMS-10`
- **D1. Promotion Engine** (17 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `PROMO-01, PROMO-02, PROMO-03, PROMO-04, PROMO-05, PROMO-06, PROMO-07, PROMO-08, PROMO-09, PROMO-11, PROMO-12, PROMO-13, PROMO-14, PROMO-15, PROMO-18, PROMO-19, PROMO-23`
  - in the section but **not delivery**: PROMO-10 (DEF), PROMO-16 (DEF), PROMO-17 (DEF), PROMO-20 (P2), PROMO-21 (P2), PROMO-22 (P2)
- **D2. Launch Business Rules** (10 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `BR-001, BR-002, BR-003, BR-004, BR-005, BR-006, BR-007, BR-008, BR-009, BR-010`
- **D3. Merchandising and Premium Positioning** (7 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `MER-01, MER-02, MER-03, MER-04, MER-05, MER-06, MER-07`
- **E1. Order Status Model** (11 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ORD-01, ORD-02, ORD-03, ORD-04, ORD-05, ORD-06, ORD-07, ORD-08, ORD-12, ORD-13, ORD-14`
  - in the section but **not delivery**: ORD-09 (DEF), ORD-10 (DEF), ORD-11 (DEF)
- **E2. Payments** (15 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `PAY-01, PAY-02, PAY-03, PAY-04, PAY-05, PAY-06, PAY-07, PAY-08, PAY-09, PAY-13, PAY-14, PAY-15, PAY-16, PAY-17, PAY-19`
  - in the section but **not delivery**: PAY-10 (P2), PAY-20 (P2), PAY-21 (P2), PAY-22 (P3)
- **E3. Shipping** (12 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `SHIP-01, SHIP-03, SHIP-04, SHIP-05, SHIP-08, SHIP-09, SHIP-12, SHIP-13, SHIP-14, SHIP-15, SHIP-16, SHIP-17`
  - in the section but **not delivery**: SHIP-02 (P2), SHIP-06 (P2), SHIP-07 (P2), SHIP-10 (DEF), SHIP-11 (DEF), SHIP-18 (P2)
- **E4. Returns and Refunds** (1 delivery rows, scope `P1-L`, stage `S1`)
  - delivery ids: `RET-08`
  - in the section but **not delivery**: RET-01 (DEF), RET-02 (DEF), RET-03 (DEF), RET-04 (DEF), RET-05 (P2), RET-06 (DEF), RET-07 (P2), RET-09 (P2), RET-10 (DEF)
- **SECTION F: NOTIFICATIONS** (10 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `NOTF-01, NOTF-02, NOTF-03, NOTF-04, NOTF-06, NOTF-08, NOTF-11, NOTF-12, NOTF-13, NOTF-14`
  - in the section but **not delivery**: NOTF-05 (P2), NOTF-07 (DEF), NOTF-09 (P2), NOTF-10 (P2)
  - **not S1**: NOTF-14 (S2, P1-L)
- **G1. Dashboard** (14 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `ADM-01, ADM-02, ADM-03, ADM-04, ADM-05, ADM-06, ADM-07, ADM-08, ADM-09, ADM-10, ADM-11, ADM-12, ADM-13, ADM-15`
  - in the section but **not delivery**: ADM-14 (P2)
  - **not S1**: ADM-05 (S2, P1-L), ADM-07 (S2, P1-L)
- **G2. Product Management** (29 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ADM-20, ADM-21, ADM-22, ADM-23, ADM-24, ADM-25, ADM-26, ADM-27, ADM-29, ADM-30, ADM-31, ADM-32, ADM-33, ADM-34, ADM-35, ADM-36, ADM-37, ADM-38, ADM-39, ADM-40, ADM-41, ADM-42, ADM-43, ADM-44, ADM-45, ADM-47, ADM-48, ADM-49, ADM-50`
  - in the section but **not delivery**: ADM-28 (P2), ADM-46 (P2)
- **G3. Categories, Brands, Collections and Content** (7 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ADM-55, ADM-56, ADM-57, ADM-59, ADM-60, ADM-61, ADM-63`
  - in the section but **not delivery**: ADM-58 (P2), ADM-62 (P2)
- **G4. Inventory** (7 delivery rows, scope `P1-E`, stage `mixed`)
  - delivery ids: `ADM-70, ADM-71, ADM-72, ADM-73, ADM-74, ADM-75, ADM-77`
  - in the section but **not delivery**: ADM-76 (P2), ADM-78 (P3), ADM-79 (P2), ADM-80 (OUT)
  - **not S1**: ADM-71 (-, P1-E), ADM-73 (-, P1-E), ADM-74 (-, P1-E), ADM-75 (-, P1-E), ADM-77 (-, P1-E)
- **G5. Orders** (9 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ADM-85, ADM-86, ADM-87, ADM-88, ADM-89, ADM-90, ADM-91, ADM-92, ADM-93`
  - in the section but **not delivery**: ADM-94 (P2)
- **G6. Customers** (5 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `ADM-100, ADM-101, ADM-103, ADM-104, ADM-105`
  - in the section but **not delivery**: ADM-102 (P2)
  - **not S1**: ADM-101 (S2, P1-L), ADM-105 (S2, P1-L)
- **G7. Promotions and Coupons** (4 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ADM-110, ADM-111, ADM-113, ADM-115`
  - in the section but **not delivery**: ADM-112 (P2), ADM-114 (P2)
- **G8. Returns and Refunds Admin** (1 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `ADM-124`
  - in the section but **not delivery**: ADM-120 (DEF), ADM-121 (DEF), ADM-122 (DEF), ADM-123 (DEF), ADM-125 (DEF), ADM-126 (P2)
- **G9. Users, Roles and Audit** (5 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `ADM-130, ADM-131, ADM-132, ADM-133, ADM-134`
- **G10. [key] STORE OPERATIONS CONSOLE** (13 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `ADM-146, ADM-148, ADM-149, ADM-150, ADM-151, ADM-152, ADM-153, ADM-154, ADM-157, ADM-158, ADM-159, ADM-160, ADM-161`
  - in the section but **not delivery**: ADM-140 (DEF), ADM-141 (DEF), ADM-142 (DEF), ADM-143 (DEF), ADM-144 (DEF), ADM-145 (DEF), ADM-147 (DEF), ADM-155 (DEF), ADM-156 (DEF), ADM-159a (P2)
  - **not S1**: ADM-149 (-, P1-E)
- **G11. [key] SELF-SERVICE STOREFRONT and PRODUCT CONTROL** (24 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `SSC-01, SSC-02, SSC-03, SSC-04, SSC-05, SSC-06, SSC-07, SSC-08, SSC-09, SSC-10, SSC-11, SSC-12, SSC-13, SSC-14, SSC-20, SSC-21, SSC-22, SSC-23, SSC-24, SSC-25, SSC-26, SSC-27, SSC-28, SSC-29`
- **SECTION H: REPORTS and ANALYTICS** (5 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `RPT-01, RPT-08, RPT-09, RPT-10, RPT-11`
  - in the section but **not delivery**: RPT-02 (P2), RPT-03 (DEF), RPT-04 (P2), RPT-05 (P2), RPT-06 (P2), RPT-07 (DEF)
- **SECTION I: INTEGRATIONS** (7 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `INT-01, INT-02, INT-05, INT-09, INT-10, INT-12, INT-16`
  - in the section but **not delivery**: INT-03 (P2), INT-04 (P3), INT-06 (P2), INT-07 (P2), INT-08 (P2), INT-11 (P2), INT-13 (P3), INT-14 (P3), INT-15 (P2), INT-17 (OUT)
- **I2.1 Confirmed business-level commitment** (5 delivery rows, scope `P1`, stage `S1`)
  - delivery ids: `ERP-01, ERP-02, ERP-04, ERP-05, ERP-10`
- **I2.2 Pending ERP technical specification** (4 delivery rows, scope `P1-E`, stage `S1`)
  - delivery ids: `ERP-03, ERP-06, ERP-07, ERP-08`
  - in the section but **not delivery**: ERP-09 (OUT)
- **SECTION J: NON-FUNCTIONAL REQUIREMENTS** (17 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `NFR-01, NFR-02, NFR-03, NFR-04, NFR-04a, NFR-04b, NFR-05, NFR-06, NFR-07, NFR-08, NFR-09, NFR-10, NFR-11, NFR-12, NFR-12a, NFR-13, NFR-14`
  - **not S1**: NFR-12a (S2, P1-L)
- **SECTION K: DATA MODEL** (17 delivery rows, scope `mixed`, stage `mixed`)
  - delivery ids: `ENT-01, ENT-02, ENT-03, ENT-04, ENT-05, ENT-06, ENT-07, ENT-08, ENT-09, ENT-10, ENT-11, ENT-13, ENT-14, ENT-15, ENT-16, ENT-17, ENT-19`
  - in the section but **not delivery**: ENT-12 (DEF), ENT-18 (P2)
  - **not S1**: ENT-04 (-, P1-E)
- **SECTION L: ANALYTICS EVENTS** (18 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `EVT-01, EVT-02, EVT-03, EVT-04, EVT-05, EVT-06, EVT-07, EVT-08, EVT-09, EVT-10, EVT-11, EVT-12, EVT-13, EVT-14, EVT-15, EVT-16, EVT-18, EVT-20`
  - in the section but **not delivery**: EVT-17 (P2), EVT-19 (DEF)
- **SECTION M: DEFINITION OF DONE** (9 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `DOD-01, DOD-02, DOD-03, DOD-04, DOD-05, DOD-06, DOD-07, DOD-08, DOD-09`
- **SECTION N: ACCEPTANCE SCENARIOS** (25 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `AC-01, AC-02, AC-03, AC-04, AC-05, AC-06, AC-07, AC-08, AC-09, AC-10, AC-11, AC-12, AC-13, AC-14, AC-15, AC-16, AC-17, AC-18, AC-19, AC-20, AC-21, AC-22, AC-23, AC-24, AC-25`
- **U.3 Rows** (17 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `MIG-01, MIG-02, MIG-03, MIG-04, MIG-06, MIG-09, MIG-10, MIG-11, MIG-12, MIG-13, MIG-14, MIG-15, MIG-16, MIG-17, MIG-19, MIG-20, MIG-25`
  - in the section but **not delivery**: MIG-05 (DEF), MIG-07 (DEF), MIG-08 (DEF), MIG-18 (DEF), MIG-21 (P2), MIG-22 (P2), MIG-23 (OUT), MIG-24 (OUT)
- **V.1 Measurement and advertising** (7 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `MKT-01, MKT-02, MKT-03, MKT-05, MKT-06, MKT-09, MKT-10`
  - in the section but **not delivery**: MKT-04 (P2), MKT-07 (P2), MKT-08 (P2), MKT-11 (P3)
- **V.2 Search engine optimisation** (8 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `MKT-12, MKT-13, MKT-14, MKT-15, MKT-16, MKT-17, MKT-18, MKT-20`
  - in the section but **not delivery**: MKT-19 (OUT)
- **V.3 Third-party access and site protection** (6 delivery rows, scope `mixed`, stage `S1`)
  - delivery ids: `MKT-21, MKT-22, MKT-23, MKT-24, MKT-25, MKT-26`
- **W.1 Points earning**: no delivery rows. Every row in it is P2, P3, DEF or OUT
- **W.2 Points redemption**: no delivery rows. Every row in it is P2, P3, DEF or OUT
- **W.3 Points account and ledger**: no delivery rows. Every row in it is P2, P3, DEF or OUT
- **W.4 VIP tiers**: no delivery rows. Every row in it is P2, P3, DEF or OUT
- **W.5 Referral programme**: no delivery rows. Every row in it is P2, P3, DEF or OUT
- **W.6 Supporting records**: no delivery rows. Every row in it is P2, P3, DEF or OUT
- **Pre-development** (10 delivery rows, scope `DLV`, stage `mixed`)
  - delivery ids: `PRE-01, PRE-02, PRE-03a, PRE-03b, PRE-04, PRE-05, PRE-06, PRE-07, PRE-08, PRE-09`
  - **not S1**: PRE-09 (-, DLV)
- **Handover** (12 delivery rows, scope `DLV`, stage `S1`)
  - delivery ids: `HND-01, HND-02, HND-03, HND-04, HND-05, HND-06, HND-07, HND-08, HND-09, HND-10, HND-11, HND-12`

## The slices, by epic

Generated from `docs/scope/backlog-ownership.json` by `tools/gen_backlog_docs.py`. Each slice states the **exact delivery ids it owns** in the ownership map at the end of this document, which is generated in the same pass and is what CI reads. `Scope class` and `Stage` are **computed over the ids the slice owns**.

**74 of the 83 slices are PBIs on the live board.** The other 9 hold second-release rows: they are recorded here so that every delivery row has an owner, and they are not created until the second release is planned. The outcome, dependencies, inputs and notes of a slice live in its issue and in the ownership file, not in this table.

A slice may own a row from another register section where the outcome is the same, which is why the per-epic counts here do not match the epic table above, which counts by register section. Both account for the same delivery rows.

### E-PRE (15 delivery rows over 5 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-PRE-6 ERP Integration Specification approved (PRE-09) | #243 | 1 | `DLV` | `per PRE-09` | 3 |
| E-PRE-4 Catalogue import specification, validated against the real source file | #249 | 5 | `P1-L` | `S1` | 7 |
| E-PRE-5 Design system and interface design delivered as working HTML and CSS | #250 | 1 | `DLV` | `S1` | 10 |
| E-PRE-7 Pre-development documents delivered and approved | #266 | 7 | `DLV` | `S1` | 16 |
| E-PRE-8 Store operations walkthrough held on the live admin, with the Section G10 list confirmed | #267 | 1 | `DLV` | `S1` | 17 |

### E-FND (74 delivery rows over 8 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-FND-1 Bilingual platform baseline: English default, Arabic fully delivered right to left | #241 | 5 | `P1` | `S1` | 1 |
| E-FND-2 Information architecture and URL structure, in both languages | #242 | 30 | `mixed` | `S1` | 2 |
| E-FND-1b The remaining fixed core decisions hold across the build | #271 | 9 | `mixed` | `S1` | 21 |
| E-FND-4 Vision, objectives and platform-type guardrails are met by the delivered store | #272 | 15 | `mixed` | `S1` | 22 |
| E-FND-3 The customer journey holds end to end, from discovery to after-sales | #322 | 10 | `mixed` | `S1` | 72 |
| E-FND-2b Information architecture, second release | not created, second release | 2 | `P1-L` | `S2` | - |
| E-FND-3b Reorder from order history, second release | not created, second release | 1 | `P1-L` | `S2` | - |
| E-FND-4b Business objectives, second release | not created, second release | 2 | `P1-L` | `S2` | - |

### E-ROLE (5 delivery rows over 1 slice)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-ROLE-1 Launch staff roles and permissions | #251 | 5 | `mixed` | `S1` | 11 |

### E-SF (154 delivery rows over 14 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-SF-1 Header, navigation and footer, mirrored right to left | #253 | 10 | `P1` | `S1` | 13 |
| E-SF-5 Product details page, in both languages | #254 | 22 | `mixed` | `S1` | 14 |
| E-SF-8 Checkout and order placement, in both languages | #255 | 14 | `P1` | `S1` | 15 |
| E-SF-2 Home page, in both languages, with sections the client edits and reorders | #302 | 11 | `mixed` | `S1` | 52 |
| E-SF-3 Product listing, category and collection pages with filters and sorting, in both languages | #303 | 21 | `P1` | `S1` | 53 |
| E-SF-4 Search that works in English and Arabic, with an admin-managed synonym list | #304 | 9 | `mixed` | `S1` | 54 |
| E-SF-7 Registration, sign-in and Google sign-in resolve to one account, with guest checkout kept open | #305 | 16 | `mixed` | `S1` | 55 |
| E-SF-6 Cart that survives registration, sign-in and return visits, in both languages | #306 | 18 | `mixed` | `S1` | 56 |
| E-SF-10 Wishlist for guests and customers, and recently viewed products | #307 | 5 | `P1` | `S1` | 57 |
| E-SF-9 My Account: profile, addresses, order history, tracking and invoice download | #308 | 9 | `mixed` | `S1` | 58 |
| E-SF-11 Moderated product reviews with ratings, and a review invitation after delivery | #309 | 5 | `P1` | `S1` | 59 |
| E-SF-12 Trust and support pages, editable without a developer, in both languages | #310 | 10 | `P1` | `S1` | 60 |
| E-SF-3b Product listing, second release: filter by rating | not created, second release | 1 | `P1-L` | `S2` | - |
| E-SF-9b My Account, second release: reorder, notification preferences, account deletion request | not created, second release | 3 | `P1-L` | `S2` | - |

### E-RULES (33 delivery rows over 3 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-RULES-1 Promotion engine: discounts, conditions, limits and schedules configurable from admin | #281 | 17 | `P1` | `S1` | 31 |
| E-RULES-3 Merchandising controls: badges, featured products, manual ordering, curated collections and related products | #282 | 7 | `P1` | `S1` | 32 |
| E-RULES-2 Launch business rules enforced: welcome discount, free shipping on two items, explicit stacking, refund ceiling | #290 | 9 | `P1` | `S1` | 40 |

### E-ORD (43 delivery rows over 4 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-ORD-1 Order status model with a queryable status history | #248 | 13 | `mixed` | `S1` | 9 |
| E-ORD-2 Payments through Paymob: verified callbacks, no duplicate charge, refunds from the order screen | #283 | 16 | `mixed` | `S1` | 33 |
| E-ORD-3 Shipping through Bosta with an explicit status mapping, governorate rates and cash-on-delivery reconciliation | #291 | 13 | `mixed` | `S1` | 41 |
| E-ORD-4 Refunds recorded from the standard order screen, with the standard refund email | #292 | 1 | `P1-L` | `S1` | 42 |

### E-NOTF (10 delivery rows over 2 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-NOTF-1 Transactional emails for account, order, payment, shipping, delivery and refund | #285 | 9 | `mixed` | `S1` | 35 |
| E-NOTF-1b Notifications, second release: admin-editable templates | not created, second release | 1 | `P1-L` | `S2` | - |

### E-ADM (114 delivery rows over 14 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-ADM-9 Staff accounts with two-factor sign-in and a searchable log that ordinary users cannot delete | #275 | 5 | `mixed` | `S1` | 25 |
| E-ADM-2 Product management in admin: content, media, pricing, variants, SEO fields, flags, bulk import, export and update | #276 | 28 | `mixed` | `S1` | 26 |
| E-ADM-3 Categories, brands, collections, home page sections and static pages managed from admin | #277 | 7 | `mixed` | `S1` | 27 |
| E-ADM-11 Everyday admin tasks in the standard administration: quick product entry, bulk edits, search, export, mobile checks | #278 | 12 | `mixed` | `S1` | 28 |
| E-ADM-6 Customer records in admin: profile, internal notes, and suspension with a reason | #279 | 3 | `mixed` | `S1` | 29 |
| E-ADM-5 Order management in admin: find, annotate, ship, cancel, refund and print | #280 | 7 | `mixed` | `S1` | 30 |
| E-ADM-7 Promotions and coupons managed from admin: create, schedule, pause, keep the usage history | #289 | 4 | `mixed` | `S1` | 39 |
| E-ADM-8 A refund is recorded with its payment reference from the standard order screen | #293 | 1 | `P1` | `S1` | 43 |
| E-ADM-1 Admin dashboard: sales figures, top products, and low and out-of-stock counts that count each item once | #294 | 12 | `mixed` | `S1` | 44 |
| E-ADM-10 Self-service storefront and product control: everything a customer sees is editable in both languages, with preview | #311 | 23 | `mixed` | `S1` | 61 |
| E-ADM-4 Stock on hand and stock available shown in admin from the ERP | #317 | 2 | `P1-E` | `S1` | 67 |
| E-ADM-4b Inventory tasks whose method and timing PRE-09 sets | #318 | 6 | `P1-E` | `per PRE-09` | 68 |
| E-ADM-1b Dashboard, second release: new and returning customers, conversion rate | not created, second release | 2 | `P1-L` | `S2` | - |
| E-ADM-6b Customers, second release: lifetime metrics and export under privacy controls | not created, second release | 2 | `P1-L` | `S2` | - |

### E-MIG (13 delivery rows over 5 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-MIG-3 The catalogue migration does not corrupt Arabic product or variation content | #247 | 4 | `mixed` | `S1` | 6 |
| E-MIG-2 Attributes, categories, brands and SEO fields mapped into the store in the initial migration | #295 | 4 | `P1-L` | `S1` | 45 |
| E-MIG-4 Product images imported to the Mizzey server with alt text, once rights are confirmed | #296 | 2 | `P1-L` | `S1` | 46 |
| E-MIG-6 The initial migration runs in the background, and the catalogue stays exportable | #297 | 2 | `P1-L` | `S1` | 47 |
| E-MIG-7 Initial stock quantities loaded where PRE-09 settles | #320 | 1 | `P1-E` | `S1` | 70 |

### E-ERP (10 delivery rows over 7 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-ERP-1 ERP adapter seam: one commercial item resolves to one ERP stock item | #245 | 4 | `P1` | `S1` | 8 |
| E-ERP-7 Products and variants are matched to the ERP, one stock identity per item in both languages | #299 | 1 | `P1-E` | `S1` | 49 |
| E-ERP-2 A sale on the website reduces the corresponding stock in the ERP, exactly once | #300 | 1 | `P1` | `S1` | 50 |
| E-ERP-3 No sale is confirmed against stock the ERP cannot validate, including when it is unreachable | #301 | 1 | `P1` | `S1` | 51 |
| E-ERP-4 Adding to the cart and viewing the cart take account of ERP stock | #314 | 1 | `P1-E` | `S1` | 64 |
| E-ERP-5 Stock is restored in the ERP on a failed payment or a cancellation, and returned parcels are received | #315 | 1 | `P1-E` | `S1` | 65 |
| E-ERP-6 Stock appears in, and is changed through, the store admin as PRE-09 settles | #316 | 1 | `P1-E` | `S1` | 66 |

### E-RPT (6 delivery rows over 4 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-RPT-2 The inventory report counts one physical item once, not once per language | #246 | 1 | `P1-L` | `S1` | 5 |
| E-RPT-3 Product cost: carry CX-01 and OD-12 to the Stage 1 gate | #252 | 2 | `P1` | `S1` | 12 |
| E-RPT-1 Sales report: revenue, net sales, orders, units, average order value, discounts and shipping | #286 | 1 | `P1-L` | `S1` | 36 |
| E-RPT-4 Funnel and traffic reports available in Google Analytics | #313 | 2 | `P1-L` | `S1` | 63 |

### E-INT (4 delivery rows over 1 slice)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-INT-1 Transactional email service, Google Analytics with Tag Manager, Meta Pixel and the Merchant Center feed connected | #284 | 4 | `mixed` | `S1` | 34 |

### E-NFR (13 delivery rows over 4 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-NFR-3 Infrastructure provisioned for development, staging and production | #244 | 2 | `P1` | `S1` | 4 |
| E-NFR-1 Cross-cutting standards met: mobile first, performance, locale formatting, security, data integrity, accessibility, privacy, scalability | #269 | 9 | `mixed` | `S1` | 19 |
| E-NFR-2 Observability: application logs, error monitoring, integration logs and admin audit logs | #270 | 1 | `P1-L` | `S1` | 20 |
| E-NFR-1b Privacy, second release: automated data export and deletion requests | not created, second release | 1 | `P1-L` | `S2` | - |

### E-DATA (16 delivery rows over 2 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-DATA-1 The contracted data model exists: every launch entity is stored, related and documented | #273 | 15 | `mixed` | `S1` | 23 |
| E-DATA-2 Inventory transaction records, as PRE-09 settles | #319 | 1 | `P1-E` | `per PRE-09` | 69 |

### E-EVT (18 delivery rows over 2 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-EVT-2 Event Tracking Plan: every event, trigger, parameter and source of truth per business metric | #274 | 1 | `DLV` | `S1` | 24 |
| E-EVT-1 The standard e-commerce events fire across the funnel, in both languages | #312 | 17 | `mixed` | `S1` | 62 |

### E-MKT (21 delivery rows over 3 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-MKT-2 Search engine controls: editable meta and canonical, redirects, missing-page log, sitemap and robots, hreflang | #287 | 8 | `mixed` | `S1` | 37 |
| E-MKT-1 Measurement and advertising tools ready for a marketing team: analytics, pixels, product feeds, attribution, consent | #288 | 7 | `mixed` | `S1` | 38 |
| E-MKT-3 Third parties work through a restricted role, Tag Manager and staging, under a written protocol | #298 | 6 | `mixed` | `S1` | 48 |

### E-DOD (9 delivery rows over 1 slice)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-DOD Definition of done applied to every Stage 1 PBI, with the evidence recorded | #268 | 9 | `mixed` | `S1` | 18 |

### E-ACC (25 delivery rows over 2 slices)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-ACC-2 ERP stock acceptance scenarios, finalised in PRE-09, pass against the ERP test environment | #321 | 5 | `P1-E` | `S1` | 71 |
| E-ACC-1 The client's acceptance scenarios pass as the UAT script | #323 | 20 | `mixed` | `S1` | 73 |

### E-HND (12 delivery rows over 1 slice)

| Slice | PBI | Rows | Scope class | Stage | Delivery order |
|---|---|---|---|---|---|
| E-HND Handover: code, design, documentation, accounts, environments, training and warranty | #324 | 12 | `DLV` | `S1` | 74 |

## What can proceed independently of the ERP, and what cannot

| Can proceed now | Must wait for PRE-09 |
|---|---|
| E-FND, E-ROLE (CX-01 resolved by D-10), E-SF-1 to E-SF-5, E-SF-7, E-SF-9 to E-SF-12 | E-ADM-4 (all 8 inventory rows are P1-E) |
| E-RULES (all three slices) | E-ERP-4 to E-ERP-7 |
| E-ORD-1 to E-ORD-4 | E-MIG-7 (MIG-14 initial stock load) |
| E-ADM-1 to E-ADM-3, E-ADM-5 to E-ADM-10 | The 5 P1-E acceptance scenarios in E-ACC |
| E-MIG-1 to E-MIG-6 | ENT-04 |
| E-RPT-1 to E-RPT-4 | |
| E-INT, E-NOTF, E-NFR, E-DATA (except ENT-04), E-EVT, E-MKT | |
| **E-ERP-1**, the adapter seam, because ERP-01, ERP-02, ERP-10 and INT-16 are P1 | |

**E-SF-6 (cart) and E-SF-8 (checkout) are partly blocked**: the surfaces are buildable, the stock-aware behaviour
inside them is ERP-03 and ERP-05. They are sliced so that the non-stock behaviour can be delivered and accepted,
with the stock interaction behind the adapter seam.

## The current delivery sequence

**The live Project's `Delivery order` field is the operational authority.** This section records the reasoning
behind it. The probes that the earlier version of this section recommended as items 1, 2 and 3 are **complete**,
and they are not planned work any more:

| Was recommended | Outcome |
|---|---|
| B1, does an Arabic order reduce stock on the English original | **Done.** Normal multilingual stock synchronisation works while the translation group is intact. WCML hooks the stock write directly, so stock never depended on `save_post`. The hypothesis that it was a second cost defect is **refuted and closed**. The dangerous case is translation-group corruption |
| A12, which fields a meta-only save leaves stale | **Done.** Nine of fourteen measured fields do not reach the Arabic record on a code-level save, because thirteen of WCML's fourteen synchronisation components run from `save_post`. No fix built |
| A11, translation-group integrity | **Done.** Sources first, translations second prevents the measured batch defect. The correction is a migration sequencing invariant, not runtime code, and it is owned by **#247**. No runtime synchronisation layer is built |
| The price channel matrix | **Done.** Every contracted launch price-maintenance path keeps the Arabic price correct, including the scheduled-sales cron. Unsupported programmatic writes remain a developer-quality safeguard, **not a feature**, so no price PBI exists |
| The ERP question set | **Done.** Eighteen must-answer decisions in `docs/2026-10-04-erp-technical-meeting-questions.md` |

The sequence now:

| Order | Work | Why here | Blocked by |
|---|---|---|---|
| 1 | **#241**, bilingual platform baseline (FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14) | The only PBI in `Ready`. No client decision, no design dependency, no ERP dependency, and every later storefront slice sits on it | Nothing |
| 2 | **The client decisions, in parallel**: OD-01, OD-27, CR-06, CX-01, OD-12 | They are the long poles and none of them is engineering work. OD-01 alone blocks 152 storefront rows | The client |
| 3 | **The ERP meeting and PRE-09 preparation** (#243) | It gates 19 P1-E rows plus MIG-14. Targeted week 4, and being unprepared costs a week | The ERP team's availability |
| 4 | **#242**, information architecture, after #241 | Everything on the storefront needs the URL and language structure settled | #241 |
| 5 | **#246**, the inventory report correction, once its predecessor is ready | A contracted P1-L row with a measured defect (P-020): one physical item listed twice | The bilingual baseline it depends on |
| 6 | **#249**, catalogue import specification, after CR-06 | The specification cannot be finalised without the real source file | **CR-06** |
| 7 | **#247**, migration sequencing and integrity, after #249 and CR-06 | It carries the A11 invariant and the MIG-13 precondition, and both describe how the import runs | #249, **CR-06** |
| 8 | **#245**, the ERP adapter seam, as far as PRE-09 allows | ERP-01, ERP-02 and INT-16 are P1, not P1-E, so the seam can be designed now and PRE-09 fills in a known shape | Partly PRE-09 |
| 9 | **The design-dependent storefront PBIs**, #250 then #253, #254, #255 and the rest | All of them are behind the approved interface design | **OD-01** |

**That table is the sequence as first reasoned, on 4 October.** #241 and #242 are since merged and `Verified`, and
D-10 reclassified every blocker in it. The section below is the current reading.

## Blockers after D-10 (5 October 2026)

Constitution M-10: a missing client or vendor value does not block development when configuration, a working
default, a placeholder, a fixture, a mock adapter, a contract or sandbox credentials can stand in for it. The
owner's decisions are in `DECISIONS.md` D-10 and `docs/scope/open-items.json`; none is a client confirmation.

| Was blocking | Class now | What proceeds, and what still waits |
|---|---|---|
| OD-27 hosting outside Egypt | Blocks production or launch only | Development and staging run on the developer's machine, staging isolated, on its own database, synthetic data only, reviewed through a Cloudflare Tunnel. Production waits for the client's written instruction. **Not client-approved** |
| OD-14 operating budget | Blocks production or launch only | A production hosting and budget gate |
| OD-15 volumes | Configurable working default | 5,000 products as the baseline, growth without redesign, 300 orders a day as the planning assumption |
| OD-01 brand identity | Blocks final acceptance | Structural design on neutral tokens proceeds in #250. Approval of the interface design waits |
| CR-06, CR-07 the Amazon export | Blocks final acceptance | #249 is built against a fixture in the documented export format. It is not final, and not validated against the real file, until CR-06 arrives |
| CX-01 the Accountant role | Resolved by the owner | The role exists at launch, least privilege, refunds a separate capability. ROLE-06 still reads DEF in the register, so the Accountant criteria wait for the register correction |
| PRE-09 and the ERP meeting | ERP input | The adapter seam, #245, is built against mocks and contracts on the fixed invariants. No P1-E criterion is final before PRE-09 (M-3) |
| OD-03, OD-04, OD-05, OD-19, OD-21 | Configurable working default | Owner defaults, each admin-configurable or a launch-time activation |
| OD-12 product cost basis | Blocks final acceptance | AC-7 of #252 |
| OD-08, OD-09, OD-13, OD-26 | Content or client input | Placeholders until supplied |
| The Paymob merchant account, the carrier account and rate card, the Bosta legend, OD-20, the tracking accounts | Vendor or account input | Sandbox credentials or a fake adapter until supplied |
| OD-18, CR-04, CR-05, CR-09 | Blocks production or launch only | Launch inputs |

**What blocks development now is only sequencing between PBIs**: #247 behind #249; #253 behind #250; #254 behind
#250 and #253; #255 behind #250, #254 and #245. Every other seeded PBI is `Ready` or `Verified`.

## Client decisions to group into the next Stage 1 review

| Id | Decision | Blocks | Why it is in this group |
|---|---|---|---|
| **OD-01** | Brand name, logo, visual identity | All design, therefore all 152 storefront rows | The largest single blocker in the backlog |
| **OD-27** | Hosting outside Egypt, in writing | All infrastructure (E-PRE-1) | Nothing can be provisioned without it |
| **OD-15** | Expected product count, order volume, traffic | Infrastructure sizing | A stated assumption exists (5,000 products, 300 orders a day). It needs confirming or correcting |
| **OD-14** | Monthly operating budget for hosting and services | Infrastructure sizing and provider choice | Recurring cost is the client's |
| **CR-06** | A real, unmodified Amazon sample export | E-PRE-4, E-MIG-1 | Recorded as confirmed available; still needed as a file |
| **CR-08 / OD-26** | Image and content rights, in writing | E-MIG-4 | R-01, the highest legal risk in the register |
| **CX-01** | Which staff roles hold financial permission | AC-6 of the cost pilot, E-ROLE-1, E-ADM-9 | The pilot is complete except for this. t09 facts are attached: administrator and shop_manager see cost today |
| **OD-12** | Product cost basis, and who enters it. Also whether a deliberate zero must be distinguishable from an uncosted product | AC-7 of the cost pilot, the catalogue load | The pilot's second open item. The zero-versus-missing question is added by the pilot's evidence |
| OD-03, OD-04 | Welcome discount; what "two items" means for free shipping | E-RULES-2 | Before the promotions build |
| OD-05, OD-09, OD-19, OD-29 | COD offered; VAT and invoicing; payment methods at launch; COD verification | E-SF-8, E-ORD-2 | Before the checkout build |
| OD-08, OD-13 | Return window and exceptions; authenticity and warranty policy | E-ORD-4, E-SF-12 | Before the returns and content builds |
| OD-20 | Carrier COD collection and remittance cycle | E-ORD-3, reporting | Before the shipping build |
| OD-21 | Which tracking integrations to activate at launch | E-EVT, E-MKT | All three are built; this decides activation |
| OD-18 | Domain, sending address, support numbers, legal entity details | Launch | Before launch, not before build |

Already settled, recorded so they are not reopened: **OD-41** (the store does not confirm a sale when the ERP
cannot be reached), **OD-42** (no interim separately maintained stock balance), **OD-11** (email-only
notifications at launch).

## Ownership map: all 595 delivery rows

**Generated, and checked in CI.** `tools/tests/test_backlog_totals.py` reads this list and fails if any id is not
a register delivery row, if any delivery row has no owner, if a range appears where an id should be, if a seeded
slice disagrees with the live board, or if **any** row gains a second owner. That is the mechanism that stops this
document recreating the errors corrected on #241 to #255.

**A seeded slice records exactly what the live board holds**, so the document and the Project cannot drift. Every
delivery row has **exactly one accepting owner**: see the resolution note at the end of this map.

Format: slice, live PBI where one exists, count, then the exact ids.

**E-PRE**

- **E-PRE-4 Catalogue import specification, validated against the real source file** (#249) (5 ids): `MIG-01, MIG-03, MIG-04, MIG-06, MIG-19`
- **E-PRE-5 Design system and interface design delivered as working HTML and CSS** (#250) (1 ids): `PRE-03b`
- **E-PRE-6 ERP Integration Specification approved (PRE-09)** (#243) (1 ids): `PRE-09`
- **E-PRE-7 Pre-development documents delivered and approved** (#266) (7 ids): `PRE-01, PRE-02, PRE-03a, PRE-04, PRE-05, PRE-06, PRE-08`
- **E-PRE-8 Store operations walkthrough held on the live admin, with the Section G10 list confirmed** (#267) (1 ids): `PRE-07`

**E-FND**

- **E-FND-1 Bilingual platform baseline: English default, Arabic fully delivered right to left** (#241) (5 ids): `FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14`
- **E-FND-1b The remaining fixed core decisions hold across the build** (#271) (9 ids): `FIX-01, FIX-02, FIX-03, FIX-05, FIX-06, FIX-07, FIX-08, FIX-09, FIX-10`
- **E-FND-2 Information architecture and URL structure, in both languages** (#242) (30 ids): `IA-01, IA-02, IA-03, IA-04, IA-05, IA-06, IA-07, IA-08, IA-09, IA-10, IA-11, IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20, IA-21, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32, IA-35, NFR-03`
- **E-FND-2b Information architecture, second release** (2 ids): `IA-23, IA-33`
- **E-FND-3 The customer journey holds end to end, from discovery to after-sales** (#322) (10 ids): `JRN-01, JRN-02, JRN-03, JRN-04, JRN-05, JRN-06, JRN-07, JRN-08, JRN-09, JRN-10`
- **E-FND-3b Reorder from order history, second release** (1 ids): `JRN-11`
- **E-FND-4 Vision, objectives and platform-type guardrails are met by the delivered store** (#272) (15 ids): `VIS-01, VIS-02, VIS-03, VIS-04, VIS-05, VIS-06, OBJ-01, OBJ-02, OBJ-03, OBJ-04, OBJ-05, PLT-01, PLT-02, PLT-03, PLT-06`
- **E-FND-4b Business objectives, second release** (2 ids): `OBJ-06, OBJ-07`

**E-ROLE**

- **E-ROLE-1 Launch staff roles and permissions** (#251) (5 ids): `ROLE-01, ROLE-02, ROLE-07, ROLE-08, ROLE-09`

**E-SF**

- **E-SF-1 Header, navigation and footer, mirrored right to left** (#253) (10 ids): `NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, NAV-07, NAV-08, NAV-09, NAV-10`
- **E-SF-10 Wishlist for guests and customers, and recently viewed products** (#307) (5 ids): `WISH-01, WISH-02, WISH-03, WISH-04, WISH-07`
- **E-SF-11 Moderated product reviews with ratings, and a review invitation after delivery** (#309) (5 ids): `REV-01, REV-02, REV-03, REV-04, REV-05`
- **E-SF-12 Trust and support pages, editable without a developer, in both languages** (#310) (10 ids): `CMS-01, CMS-02, CMS-03, CMS-04, CMS-05, CMS-06, CMS-07, CMS-08, CMS-09, CMS-10`
- **E-SF-2 Home page, in both languages, with sections the client edits and reorders** (#302) (11 ids): `HOME-01, HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-07, HOME-08, HOME-09, HOME-10, HOME-11`
- **E-SF-3 Product listing, category and collection pages with filters and sorting, in both languages** (#303) (21 ids): `PLP-01, PLP-02, PLP-03, PLP-04, PLP-05, PLP-06, PLP-07, PLP-08, PLP-09, PLP-10, PLP-12, PLP-13, PLP-14, PLP-15, PLP-16, PLP-17, PLP-18, PLP-19, PLP-20, PLP-21, PLP-22`
- **E-SF-3b Product listing, second release: filter by rating** (1 ids): `PLP-11`
- **E-SF-4 Search that works in English and Arabic, with an admin-managed synonym list** (#304) (9 ids): `SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-05, SRCH-06, SRCH-06a, SRCH-07, SRCH-08`
- **E-SF-5 Product details page, in both languages** (#254) (22 ids): `PDP-01, PDP-02, PDP-03, PDP-04, PDP-05, PDP-06, PDP-07, PDP-08, PDP-09, PDP-10, PDP-11, PDP-12, PDP-13, PDP-14, PDP-15, PDP-16, PDP-17, PDP-18, PDP-19, PDP-21, PDP-22, PDP-24`
- **E-SF-6 Cart that survives registration, sign-in and return visits, in both languages** (#306) (18 ids): `CART-01, CART-02, CART-03, CART-04, CART-05, CART-06, CART-07, CART-08, CART-09, CART-10, CART-11, CART-12, CART-13, CART-14, CART-15, CART-16, CART-17, CART-18`
- **E-SF-7 Registration, sign-in and Google sign-in resolve to one account, with guest checkout kept open** (#305) (16 ids): `AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09, AUTH-10, AUTH-11, AUTH-12, AUTH-13, AUTH-14, AUTH-15, AUTH-16`
- **E-SF-8 Checkout and order placement, in both languages** (#255) (14 ids): `CHK-01, CHK-02, CHK-03, CHK-04, CHK-05, CHK-06, CHK-07, CHK-08, CHK-09, CHK-10, CHK-11, CHK-12, BR-005, ENT-08`
- **E-SF-9 My Account: profile, addresses, order history, tracking and invoice download** (#308) (9 ids): `ACCT-01, ACCT-02, ACCT-03, ACCT-04, ACCT-05, ACCT-06, ACCT-07, ACCT-08, ACCT-12`
- **E-SF-9b My Account, second release: reorder, notification preferences, account deletion request** (3 ids): `ACCT-09, ACCT-11, ACCT-13`

**E-RULES**

- **E-RULES-1 Promotion engine: discounts, conditions, limits and schedules configurable from admin** (#281) (17 ids): `PROMO-01, PROMO-02, PROMO-03, PROMO-04, PROMO-05, PROMO-06, PROMO-07, PROMO-08, PROMO-09, PROMO-11, PROMO-12, PROMO-13, PROMO-14, PROMO-15, PROMO-18, PROMO-19, PROMO-23`
- **E-RULES-2 Launch business rules enforced: welcome discount, free shipping on two items, explicit stacking, refund ceiling** (#290) (9 ids): `BR-001, BR-002, BR-003, BR-004, BR-006, BR-007, BR-008, BR-009, BR-010`
- **E-RULES-3 Merchandising controls: badges, featured products, manual ordering, curated collections and related products** (#282) (7 ids): `MER-01, MER-02, MER-03, MER-04, MER-05, MER-06, MER-07`

**E-ORD**

- **E-ORD-1 Order status model with a queryable status history** (#248) (13 ids): `ORD-01, ORD-02, ORD-03, ORD-04, ORD-05, ORD-06, ORD-07, ORD-08, ORD-12, ORD-13, ORD-14, ADM-86, ADM-87`
- **E-ORD-2 Payments through Paymob: verified callbacks, no duplicate charge, refunds from the order screen** (#283) (16 ids): `PAY-01, PAY-02, PAY-03, PAY-04, PAY-05, PAY-06, PAY-07, PAY-08, PAY-09, PAY-13, PAY-14, PAY-15, PAY-16, PAY-17, PAY-19, INT-01`
- **E-ORD-3 Shipping through Bosta with an explicit status mapping, governorate rates and cash-on-delivery reconciliation** (#291) (13 ids): `SHIP-01, SHIP-03, SHIP-04, SHIP-05, SHIP-08, SHIP-09, SHIP-12, SHIP-13, SHIP-14, SHIP-15, SHIP-16, SHIP-17, INT-02`
- **E-ORD-4 Refunds recorded from the standard order screen, with the standard refund email** (#292) (1 ids): `RET-08`

**E-NOTF**

- **E-NOTF-1 Transactional emails for account, order, payment, shipping, delivery and refund** (#285) (9 ids): `NOTF-01, NOTF-02, NOTF-03, NOTF-04, NOTF-06, NOTF-08, NOTF-11, NOTF-12, NOTF-13`
- **E-NOTF-1b Notifications, second release: admin-editable templates** (1 ids): `NOTF-14`

**E-ADM**

- **E-ADM-1 Admin dashboard: sales figures, top products, and low and out-of-stock counts that count each item once** (#294) (12 ids): `ADM-01, ADM-02, ADM-03, ADM-04, ADM-06, ADM-08, ADM-09, ADM-10, ADM-11, ADM-12, ADM-13, ADM-15`
- **E-ADM-10 Self-service storefront and product control: everything a customer sees is editable in both languages, with preview** (#311) (23 ids): `SSC-01, SSC-02, SSC-03, SSC-04, SSC-05, SSC-06, SSC-07, SSC-08, SSC-09, SSC-10, SSC-11, SSC-12, SSC-13, SSC-14, SSC-20, SSC-22, SSC-23, SSC-24, SSC-25, SSC-26, SSC-27, SSC-28, SSC-29`
- **E-ADM-11 Everyday admin tasks in the standard administration: quick product entry, bulk edits, search, export, mobile checks** (#278) (12 ids): `ADM-146, ADM-148, ADM-150, ADM-151, ADM-152, ADM-153, ADM-154, ADM-157, ADM-158, ADM-159, ADM-160, ADM-161`
- **E-ADM-1b Dashboard, second release: new and returning customers, conversion rate** (2 ids): `ADM-05, ADM-07`
- **E-ADM-2 Product management in admin: content, media, pricing, variants, SEO fields, flags, bulk import, export and update** (#276) (28 ids): `ADM-20, ADM-21, ADM-22, ADM-23, ADM-24, ADM-25, ADM-26, ADM-29, ADM-30, ADM-31, ADM-32, ADM-33, ADM-34, ADM-35, ADM-36, ADM-37, ADM-38, ADM-39, ADM-40, ADM-41, ADM-42, ADM-43, ADM-44, ADM-45, ADM-47, ADM-48, ADM-49, ADM-50`
- **E-ADM-3 Categories, brands, collections, home page sections and static pages managed from admin** (#277) (7 ids): `ADM-55, ADM-56, ADM-57, ADM-59, ADM-60, ADM-61, ADM-63`
- **E-ADM-4 Stock on hand and stock available shown in admin from the ERP** (#317) (2 ids): `ADM-70, ADM-72`
- **E-ADM-4b Inventory tasks whose method and timing PRE-09 sets** (#318) (6 ids): `ADM-71, ADM-73, ADM-74, ADM-75, ADM-77, ADM-149`
- **E-ADM-5 Order management in admin: find, annotate, ship, cancel, refund and print** (#280) (7 ids): `ADM-85, ADM-88, ADM-89, ADM-90, ADM-91, ADM-92, ADM-93`
- **E-ADM-6 Customer records in admin: profile, internal notes, and suspension with a reason** (#279) (3 ids): `ADM-100, ADM-103, ADM-104`
- **E-ADM-6b Customers, second release: lifetime metrics and export under privacy controls** (2 ids): `ADM-101, ADM-105`
- **E-ADM-7 Promotions and coupons managed from admin: create, schedule, pause, keep the usage history** (#289) (4 ids): `ADM-110, ADM-111, ADM-113, ADM-115`
- **E-ADM-8 A refund is recorded with its payment reference from the standard order screen** (#293) (1 ids): `ADM-124`
- **E-ADM-9 Staff accounts with two-factor sign-in and a searchable log that ordinary users cannot delete** (#275) (5 ids): `ADM-130, ADM-131, ADM-132, ADM-133, ADM-134`

**E-MIG**

- **E-MIG-2 Attributes, categories, brands and SEO fields mapped into the store in the initial migration** (#295) (4 ids): `MIG-10, MIG-11, MIG-12, MIG-15`
- **E-MIG-3 The catalogue migration does not corrupt Arabic product or variation content** (#247) (4 ids): `MIG-02, MIG-09, MIG-13, SSC-21`
- **E-MIG-4 Product images imported to the Mizzey server with alt text, once rights are confirmed** (#296) (2 ids): `MIG-16, MIG-17`
- **E-MIG-6 The initial migration runs in the background, and the catalogue stays exportable** (#297) (2 ids): `MIG-20, MIG-25`
- **E-MIG-7 Initial stock quantities loaded where PRE-09 settles** (#320) (1 ids): `MIG-14`

**E-ERP**

- **E-ERP-1 ERP adapter seam: one commercial item resolves to one ERP stock item** (#245) (4 ids): `ERP-01, ERP-02, ERP-10, INT-16`
- **E-ERP-2 A sale on the website reduces the corresponding stock in the ERP, exactly once** (#300) (1 ids): `ERP-04`
- **E-ERP-3 No sale is confirmed against stock the ERP cannot validate, including when it is unreachable** (#301) (1 ids): `ERP-05`
- **E-ERP-4 Adding to the cart and viewing the cart take account of ERP stock** (#314) (1 ids): `ERP-03`
- **E-ERP-5 Stock is restored in the ERP on a failed payment or a cancellation, and returned parcels are received** (#315) (1 ids): `ERP-06`
- **E-ERP-6 Stock appears in, and is changed through, the store admin as PRE-09 settles** (#316) (1 ids): `ERP-07`
- **E-ERP-7 Products and variants are matched to the ERP, one stock identity per item in both languages** (#299) (1 ids): `ERP-08`

**E-RPT**

- **E-RPT-1 Sales report: revenue, net sales, orders, units, average order value, discounts and shipping** (#286) (1 ids): `RPT-01`
- **E-RPT-2 The inventory report counts one physical item once, not once per language** (#246) (1 ids): `RPT-10`
- **E-RPT-3 Product cost: carry CX-01 and OD-12 to the Stage 1 gate** (#252) (2 ids): `ADM-27, RPT-11`
- **E-RPT-4 Funnel and traffic reports available in Google Analytics** (#313) (2 ids): `RPT-08, RPT-09`

**E-INT**

- **E-INT-1 Transactional email service, Google Analytics with Tag Manager, Meta Pixel and the Merchant Center feed connected** (#284) (4 ids): `INT-05, INT-09, INT-10, INT-12`

**E-NFR**

- **E-NFR-1 Cross-cutting standards met: mobile first, performance, locale formatting, security, data integrity, accessibility, privacy, scalability** (#269) (9 ids): `NFR-01, NFR-02, NFR-04b, NFR-05, NFR-06, NFR-07, NFR-11, NFR-12, NFR-13`
- **E-NFR-1b Privacy, second release: automated data export and deletion requests** (1 ids): `NFR-12a`
- **E-NFR-2 Observability: application logs, error monitoring, integration logs and admin audit logs** (#270) (1 ids): `NFR-10`
- **E-NFR-3 Infrastructure provisioned for development, staging and production** (#244) (2 ids): `NFR-08, NFR-09`

**E-DATA**

- **E-DATA-1 The contracted data model exists: every launch entity is stored, related and documented** (#273) (15 ids): `ENT-01, ENT-02, ENT-03, ENT-05, ENT-06, ENT-07, ENT-09, ENT-10, ENT-11, ENT-13, ENT-14, ENT-15, ENT-16, ENT-17, ENT-19`
- **E-DATA-2 Inventory transaction records, as PRE-09 settles** (#319) (1 ids): `ENT-04`

**E-EVT**

- **E-EVT-1 The standard e-commerce events fire across the funnel, in both languages** (#312) (17 ids): `EVT-01, EVT-02, EVT-03, EVT-04, EVT-05, EVT-06, EVT-07, EVT-08, EVT-09, EVT-10, EVT-11, EVT-12, EVT-13, EVT-14, EVT-15, EVT-16, EVT-18`
- **E-EVT-2 Event Tracking Plan: every event, trigger, parameter and source of truth per business metric** (#274) (1 ids): `EVT-20`

**E-MKT**

- **E-MKT-1 Measurement and advertising tools ready for a marketing team: analytics, pixels, product feeds, attribution, consent** (#288) (7 ids): `MKT-01, MKT-02, MKT-03, MKT-05, MKT-06, MKT-09, MKT-10`
- **E-MKT-2 Search engine controls: editable meta and canonical, redirects, missing-page log, sitemap and robots, hreflang** (#287) (8 ids): `MKT-12, MKT-13, MKT-14, MKT-15, MKT-16, MKT-17, MKT-18, MKT-20`
- **E-MKT-3 Third parties work through a restricted role, Tag Manager and staging, under a written protocol** (#298) (6 ids): `MKT-21, MKT-22, MKT-23, MKT-24, MKT-25, MKT-26`

**E-DOD**

- **E-DOD Definition of done applied to every Stage 1 PBI, with the evidence recorded** (#268) (9 ids): `DOD-01, DOD-02, DOD-03, DOD-04, DOD-05, DOD-06, DOD-07, DOD-08, DOD-09`

**E-ACC**

- **E-ACC-1 The client's acceptance scenarios pass as the UAT script** (#323) (20 ids): `AC-01, AC-02, AC-03, AC-04, AC-05, AC-06, AC-07, AC-08, AC-09, AC-10, AC-11, AC-12, AC-13, AC-14, AC-15, AC-16, AC-17, AC-18, AC-19, AC-20`
- **E-ACC-2 ERP stock acceptance scenarios, finalised in PRE-09, pass against the ERP test environment** (#321) (5 ids): `AC-21, AC-22, AC-23, AC-24, AC-25`

**E-HND**

- **E-HND Handover: code, design, documentation, accounts, environments, training and warranty** (#324) (12 ids): `HND-01, HND-02, HND-03, HND-04, HND-05, HND-06, HND-07, HND-08, HND-09, HND-10, HND-11, HND-12`

### One row, one accepting owner: how the three shared citations were resolved

Three delivery rows were once cited by two PBIs each, which left the board unable to say which acceptance closed
them. **Corrected on the live board on 4 October 2026**, each second citation becoming a Dependencies entry:

| Row | Accepting owner | Was also cited by | Now |
|---|---|---|---|
| FIX-04 | **#241**, which delivers the bilingual baseline | #246 | #246 names #241 in Dependencies and cites only RPT-10 |
| NFR-04 | **#241** | #246 | The same |
| ERP-10 | **#245**, the adapter seam | #243 | #243 names #245 in Dependencies and cites only PRE-09 |

Two consequences, both visible on the board and recorded for that reason. **#246** recomputes from `mixed` to
**`P1-L`**, RPT-10 being its only row. **#243** recomputes from `mixed` to **`DLV`**, and its Stage from `mixed` to
the board's `per PRE-09`, PRE-09 being unstaged in the register. The exact-id total moved from 131 to 129 when the
two S2 rows left #242, and from 129 to **126** here. The map above still accounts for all 595 delivery rows,
because nothing left the backlog: a citation became a dependency.

**#243 remains the proof that `ERP blocked` is not a restatement of scope**, and a cleaner one than before: it is
`ERP blocked: yes` while citing a single **DLV** row, because the specification is what the ERP meeting produces.

`tools/tests/test_backlog_totals.py` now fails on **any** row with two owners. There is no allowed exception, and
the empty exception set is itself asserted, so the allowance cannot creep back.

## What this document deliberately does not do

- It does not create any further issue or any spec. The Project exists and **15** PBIs are seeded; the remaining
  45 to 55 are not created.
- It does not close the 232 historical Option C issues or Project #4.
- It does not give any P1-E row acceptance criteria, because PRE-09 is not approved.
- It does not use a register range as a scope citation anywhere, and the ownership map plus CI now prevent one
  being reintroduced.
- It does not build margin or profitability reporting (RPT-02 is P2), the Operations Console (DEF), the dedicated
  returns workflow (DEF), reusable migration tooling (DEF), or an external search engine (P2).
- It does not resolve CX-01 or any OD row. Contradictions are escalated, not resolved.
