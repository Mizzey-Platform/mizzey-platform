# Option B delivery backlog: proposed structure

Created 4 October 2026, at the close of the product-cost pilot. **This is a proposal for review. Nothing is
created from it yet**: no GitHub Project, no issues, no specs.

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

## PBI shape

A PBI is a **vertical slice**: one customer-visible or operator-visible outcome, through every layer it needs, with
its own acceptance evidence. Not "the data layer for X" followed by "the UI for X".

Each PBI carries:

| Field | Why |
|---|---|
| Register ids | The only authority for scope. A PBI with no id is a change request, not a PBI |
| Scope class per id (P1 / P1-L / P1-E / DLV) | P1-L bounds what "done" means; P1-E cannot be finalised before PRE-09 |
| Stage (S1 / S2 / per PRE-09) | The contractual stage never moves for engineering convenience |
| Depends on | Other PBIs, by id |
| ERP blocked | Yes if any id is P1-E, or if the behaviour needs an ERP answer |
| Design dependency | Yes if it needs an approved interface design (OD-01 blocks all design) |
| Data-integrity test dependency | The item from `docs/2026-10-04-multilingual-data-integrity-workstream.md` that must be measured first |
| Acceptance evidence expected | The AC-nn rows from Section N, plus the local scenario or probe |
| Client decision required | The CX or OD id, if any |

## The slices, by epic

Dependencies are written as "after X". Everything after E-FND-1 assumes the environment exists.

### E-PRE, pre-development deliverables (DLV, S1)

| PBI | Register ids | Stage | Blocked by | Notes |
|---|---|---|---|---|
| E-PRE-1 Infrastructure provisioned | PRE-01, PRE-02 | S1 | **OD-27** (hosting outside Egypt), **OD-15** (volumes), **OD-14** (budget) | Written instruction needed before provisioning |
| E-PRE-2 Environments: development, staging, production | PRE-03a, PRE-03b, NFR-09 | S1 | E-PRE-1 | Staging is a hard prerequisite for the concurrency tests (B6) and for rendered-Arabic verification |
| E-PRE-3 Repository, CI and governance | PRE-04 | S1 | - | **Done** in substance: the governance foundation landed 22 September; CI runs `baseline` and `changes` |
| E-PRE-4 Import specification | PRE-05, PRE-06 | S1 | **CR-06** (real Amazon sample export), CR-07 | Cannot be finalised without the real file |
| E-PRE-5 Design system and interface design | PRE-07, PRE-08 | S1 | **OD-01** (brand identity) | Delivered as HTML and CSS, not Figma |
| E-PRE-6 **ERP Integration Specification** | **PRE-09** | S1 | ERP technical meeting, targeted week 4 | Gates every P1-E row. See `docs/2026-10-04-erp-technical-meeting-questions.md` |

### E-FND, foundation (70 rows)

| PBI | Register ids | Notes |
|---|---|---|
| E-FND-1 WordPress, WooCommerce and CoreX baseline, bilingual | A4 rows, FIX-01 to FIX-10, NFR-04, NFR-04a | Arabic right-to-left is `[key]`. The admin is English only (ADM-159) |
| E-FND-2 Information architecture and URL structure | IA-01 to IA-36 | 36 rows. Feeds SEO (NFR-03) and the storefront |
| E-FND-3 Customer journey skeleton | JRN-01 to JRN-12 | Becomes the acceptance path for Section N |
| E-FND-4 Vision, objectives and platform-type guardrails | VIS, OBJ, PLT rows | Mostly recorded constraints, not build work |

### E-ROLE, users and roles (5 rows)

| PBI | Register ids | Notes |
|---|---|---|
| E-ROLE-1 Launch roles and permissions | ROLE rows that are P1 or P1-L | **CX-01 is open.** The Accountant role contradiction (ROLE-06 says DEF, the narrative says it exists at launch) must be resolved by the client. Dependent criteria stay `pending CX-01` |

### E-SF, storefront (152 rows, the largest epic)

Sliced by customer-facing surface, each independently acceptable:

| PBI | Register ids | Design dep | Notes |
|---|---|---|---|
| E-SF-1 Header, navigation and footer | NAV-01 to NAV-10 | Yes | P-010 (right-to-left mirroring) is still blocked on a storefront existing |
| E-SF-2 Home page | HOME-01 to HOME-13 | Yes | |
| E-SF-3 Product listing, category and collection | PLP-01 to PLP-22 | Yes | |
| E-SF-4 Search | SRCH rows (P1 and P1-L) | Yes | Arabic synonym and correction list is admin-managed (SRCH-06, SRCH-07). An external search engine is **P2**, not here |
| E-SF-5 Product details page | PDP-01 to PDP-24 | Yes | Needs E-ADM product data and variations |
| E-SF-6 Cart | CART-01 to CART-18 | Yes | **ERP-03 is P1-E**: cart stock awareness waits for PRE-09 |
| E-SF-7 Authentication | AUTH-01 to AUTH-17 | Yes | P-001: WooCommerce replaces the guest cart rather than merging it. Cart merge is real work |
| E-SF-8 Checkout | CHK-01 to CHK-13 | Yes | **ERP-05 is P1 and `[key]`**: no sale confirmed against stock that cannot be validated. OD-05, OD-09, OD-19, OD-29 open |
| E-SF-9 My Account and after-sales | ACCT-01 to ACCT-13 | Yes | Two rows are S2 |
| E-SF-10 Wishlist, recently viewed, compare | WISH-01 to WISH-08 | Yes | P-015: WooCommerce provides no wishlist. Custom |
| E-SF-11 Reviews and Q&A | REV-01 to REV-09 | Yes | |
| E-SF-12 Trust and support pages | CMS-01 to CMS-10 | Yes | Gutenberg is the mechanism; the page-builder extras are not contracted. OD-08, OD-13 open |

### E-RULES, business rules (34 rows, 6 key)

| PBI | Register ids | Notes |
|---|---|---|
| E-RULES-1 Promotion engine | PROMO-01 to PROMO-23 | P-003: WooCommerce coupons stack freely with no priority policy. Stacking rules are real work |
| E-RULES-2 Launch business rules | BR-001 to BR-010 | OD-03 (welcome discount), OD-04 (free shipping meaning) open. BR-003 concurrency is data-integrity item B6 |
| E-RULES-3 Merchandising and premium positioning | MER-01 to MER-07 | |

### E-ORD, orders, payments, shipping, returns (39 rows, 7 key)

| PBI | Register ids | Data-integrity dep | Notes |
|---|---|---|---|
| E-ORD-1 Order status model | ORD-01 to ORD-14 | D6, D7 | P-018: no structured status history natively. ADR-0002 is the recorded minimum |
| E-ORD-2 Payments, Paymob | PAY rows, INT-01 | - | Callback verification must be specified (a recorded gap in the Technical Design). P-004: order placement is idempotent by default |
| E-ORD-3 Shipping, Bosta | SHIP rows, INT-02 | F2 | P-012: carrier status mapping is dormant in the plugin. OD-20 (COD cycle) open |
| E-ORD-4 Returns and refunds, customer side | RET rows | F1, F3, F4 | P-014: the native refund ceiling is sound. The **dedicated returns workflow is DEF** and not built |

### E-ADM, admin panel (118 rows, 8 of them P1-E)

| PBI | Register ids | ERP blocked | Notes |
|---|---|---|---|
| E-ADM-1 Dashboard | ADM-01 to ADM-10 approx | No | |
| E-ADM-2 Product management, including cost | ADM-27 and the G2 rows | No | ADM-27 cost capture is **technically verified by the product-cost pilot; contractual acceptance remains pending CX-01 and OD-12**. The code is merged and complete; the criteria are not closed. The other G2 rows are not started |
| E-ADM-3 Categories, brands, collections, content | G3 rows | No | |
| E-ADM-4 Inventory | ADM-70 to ADM-77 | **Yes, all 8 are P1-E** | Nothing here gets final criteria before PRE-09. ADM-76 and ADM-78 to ADM-80 are P2 or P3 |
| E-ADM-5 Order management | ADM-85 to ADM-93 | No | ADM-94 manual order creation is P2 |
| E-ADM-6 Customers | G6 rows | No | |
| E-ADM-7 Promotions and coupons admin | G7 rows | No | |
| E-ADM-8 Returns and refunds admin | G8 rows | No | The **Operations Console is DEF**: build only the G8 rows |
| E-ADM-9 Staff users, roles and audit | G9 rows | No | CX-01 |
| E-ADM-10 Self-service storefront and product control | G11 rows, SSC-01 to SSC-24 | No | `[key]`. Gutenberg blocks deliver it |

### E-MIG, catalogue migration (17 rows, all P1-L except MIG-14)

| PBI | Register ids | Blocked by | Notes |
|---|---|---|---|
| E-MIG-1 Import specification and source validation | MIG-01, MIG-03, MIG-06, MIG-19 | **CR-06**, CR-07 | P-008: encoding and re-run by SKU are sound; the gap is the operator safety net |
| E-MIG-2 Field, attribute, category and brand mapping | MIG-04, MIG-10, MIG-11, MIG-12 | E-MIG-1 | |
| E-MIG-3 Products, variations, prices and **cost** | MIG-09, **MIG-13**, MIG-15 | E-MIG-2, **data-integrity A5** | **Carries the MIG-13 precondition**: costs are imported before translations exist, or the runbook resolves the source-language element explicitly. A migration test asserts it |
| E-MIG-4 Images and alt text | MIG-16, MIG-17 | **CR-08 / OD-26** (image rights, in writing) | Legal prerequisite, R-01 |
| E-MIG-5 Arabic content integrity | MIG-02 | - | |
| E-MIG-6 Background execution and export | MIG-20, MIG-25 | - | |
| E-MIG-7 Initial stock load | **MIG-14 (P1-E)** | **PRE-09** | Destination (ERP or store) follows the specification |

Not built: MIG-05, MIG-07, MIG-08, MIG-18 (DEF), MIG-21, MIG-22 (P2), MIG-23, MIG-24 (OUT).

### E-ERP, ERP stock integration (9 rows, 3 key, 4 P1-E)

| PBI | Register ids | Stage | Notes |
|---|---|---|---|
| E-ERP-1 Stock source of truth, adapter seam | **ERP-01**, ERP-02, ERP-10, INT-16 | S1 | ERP-01 and the adapter rule are P1 and can be designed now as a seam. The behaviour behind it waits |
| E-ERP-2 Sale reduces ERP stock | **ERP-04** | S1 | P1, but unbuildable before PRE-09 defines the call |
| E-ERP-3 No sale against unvalidated stock | **ERP-05** | S1 | P1 and `[key]`. OD-41 confirmed the business rule: the store does not confirm the sale |
| E-ERP-4 Cart and view stock awareness | ERP-03 (P1-E) | S1 | PRE-09 |
| E-ERP-5 Restoration on failure, cancel and return | ERP-06 (P1-E) | S1 | PRE-09 |
| E-ERP-6 Stock in the store admin | ERP-07 (P1-E) | S1 | PRE-09. Pairs with E-ADM-4 |
| E-ERP-7 Product and variant matching, both languages | ERP-08 (P1-E) | S1 | PRE-09. **Our constraint is already fixed**: one ERP stock item per commercial item, resolved through WPML identity, never by SKU alone |

**ERP-09 is OUT.** No interim operation on a separately maintained stock balance is contracted.

### E-RPT, reports (5 rows)

| PBI | Register ids | Data-integrity dep | Notes |
|---|---|---|---|
| E-RPT-1 Sales report | RPT-01 (P1-L) | E (low risk) | Order level, so not exposed to the product split |
| E-RPT-2 Inventory report | RPT-10 (P1-L) | **B10, B11, verified defect** | P-020: one physical item listed twice. Blocking on acceptance. Needs a translation-group-aware query |
| E-RPT-3 Product cost captured | **RPT-11** | - | **Technically verified by the product-cost pilot; contractual acceptance remains pending CX-01 and OD-12** |
| E-RPT-4 Funnel and traffic via GA4 | RPT-08, RPT-09 (P1-L) | E (GA4 identity) | Delivered through GA4 configuration, not a built report |

Not built: RPT-02 (P2, profitability and margin), RPT-03 and RPT-07 (DEF), RPT-04 to RPT-06 (P2).

### E-INT, E-NOTF, E-NFR, E-DATA, E-EVT, E-MKT, E-DOD, E-ACC, E-HND

| Epic | Shape | Notes |
|---|---|---|
| E-INT | One slice per integration, each behind an adapter | INT-16 is `[key]`: every integration behind an adapter with retries, logging and webhook handling |
| E-NOTF | One slice for the email channel, one for the templates | Email-only at launch is **agreed**, recorded at OD-11 and NOTF-01 to NOTF-08 |
| E-NFR | Cross-cutting; attaches to other slices rather than being built alone | Exceptions that are their own work: NFR-08 backups and restore procedure, NFR-09 environments, NFR-10 observability. **NFR-07 data integrity is the register row this whole data-integrity workstream serves** |
| E-DATA | The data model rows (ENT-01 to ENT-21) | ENT-04 is P1-E. ENT-08 (snapshots) is already proved for price and cost |
| E-EVT | EVT-01 to EVT-20, GTM and GA4 configuration | Developer's work, in client-owned accounts. OD-21 decides which trackers are activated |
| E-MKT | Measurement, SEO, third-party access and site protection | 7 key rows. MKT-01 to MKT-03 are all built; activation is OD-21 |
| E-DOD | The definition of done, applied to every slice | Not a build epic |
| E-ACC | AC-01 to AC-25, the acceptance scenarios | The evidence requirement for the other epics. 5 rows are P1-E |
| E-HND | HND-01 to HND-12, handover | End of the engagement |

## What can proceed independently of the ERP, and what cannot

| Can proceed now | Must wait for PRE-09 |
|---|---|
| E-FND, E-ROLE (with CX-01 noted), E-SF-1 to E-SF-5, E-SF-7, E-SF-9 to E-SF-12 | E-ADM-4 (all 8 inventory rows are P1-E) |
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

## Recommended sequencing for the first real Option B features

The first features should be the ones that unblock the most, need no client decision, and settle the risks that
get more expensive with time.

| Order | Work | Why it is first | Blocked by |
|---|---|---|---|
| 1 | **Data-integrity probe B1**: does an Arabic order reduce stock on the English original? | Same mechanism as the cost defect, worse consequence. The answer changes the ERP adapter design and possibly the checkout. Cheap: one probe on the existing disposable runtime | Nothing |
| 2 | **Data-integrity inventory A12**: which fields WPML copies on `save_post`, and which a meta-only save can leave stale | Bounds the problem. Until it exists, every copied field is a suspected B1. Also cheap | Nothing |
| 3 | **E-PRE-6 preparation**: the ERP question set, and our own identity constraint (C3) | The ERP meeting is targeted for week 4 and gates 19 P1-E rows plus MIG-14. Being unprepared costs a week | Nothing. The question set is drafted |
| 4 | **E-FND-1 and E-FND-2**: bilingual baseline and information architecture | Everything on the storefront sits on them, and they need no client decision | Nothing |
| 5 | **E-ERP-1**: the adapter seam (ERP-01, ERP-02, ERP-10, INT-16) | P1, not P1-E. Designing the seam now means PRE-09 fills in a known shape instead of starting a design | Items 1 and 2, which tell the seam what the store does with stock |
| 6 | **E-RPT-2**: the inventory report, translation-group-aware | A contracted P1-L row with a measured defect (P-020). Fixing it needs the same identity helper as item 1 | Items 1 and 2 |
| 7 | **E-MIG-1 and E-MIG-3**: import specification and the catalogue load, with the MIG-13 precondition and its test | The cost half of MIG-13 is technically verified by the pilot and the precondition is recorded; the migration itself is not built. The real Amazon sample (CR-06) is the gate | **CR-06** |
| 8 | **E-PRE-5**: design system and interface design | Gates every storefront slice | **OD-01** (brand identity) |

Items 1, 2 and 3 are days of work, not weeks, and all three are unblocked today. Items 4 and 5 follow immediately.
Everything on the storefront is behind OD-01, which makes the brand decision the single most valuable thing to
obtain from the client.

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

## What this proposal deliberately does not do

- It does not create the GitHub Project, any issue, or any spec.
- It does not close the 232 historical Option C issues or Project #4.
- It does not give any P1-E row acceptance criteria, because PRE-09 is not approved.
- It does not build margin or profitability reporting (RPT-02 is P2), the Operations Console (DEF), the dedicated
  returns workflow (DEF), reusable migration tooling (DEF), or an external search engine (P2).
- It does not resolve CX-01 or any OD row. Contradictions are escalated, not resolved.
