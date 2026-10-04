# Option B delivery board

Written 4 October 2026 after B1 and A12 as a proposal; **created on 4 October after A11 and the price matrix**,
with the classification correction applied.

- **Board: https://github.com/orgs/Mizzey-Platform/projects/1**, "Mizzey Option B Delivery",
  organisation-owned and private. The suggested title carried an em dash; this repository's house rules
  forbid one in markdown (`tools/house_rules.py`), so the board is named plainly and the document and the
  board agree rather than describing one name and using another.
- 11 custom fields and 6 views created; **Status carries the Verified and Accepted distinction** in its own
  option descriptions.
- **The first 15 PBIs are seeded** (#241 to #255) and no more. The remaining 45 to 55 wait for review.
- **Project #4 and the 232 historical Option C issues are untouched**, verified after creation: 232 items, still
  open, under its original owner, and every issue up to #234 still open and unmodified.

**Filters and grouping are the one manual step.** `createProjectV2View` accepts a name, a layout and the visible
fields; the public GraphQL schema has no input for a filter or a grouping, so those six settings are applied once
in the browser:

| View | Setting to apply |
|---|---|
| Delivery order | sort by `Delivery order` ascending |
| By epic | group by `Epic` |
| Blocked | filter `status:Blocked` |
| ERP-gated | filter `-"ERP blocked":no` |
| Needs a client decision | filter `has:"Client decision"` |
| Acceptance | filter `status:Verified,Accepted` |

## The model

| Principle | What it means here |
|---|---|
| The register provides traceability, not the work breakdown | 595 delivery rows (P1 435, P1-L 117, P1-E 19, DLV 24) are the scope authority. **They do not become 595 issues** |
| A PBI is a vertical slice | One outcome, through every layer it needs, with its own acceptance evidence. Not "the data layer for X" then "the UI for X" |
| Register ids attach to PBIs | Each PBI cites the rows it satisfies. A PBI with no id is a change request, not a PBI |
| Non-delivery scope never becomes an active issue | DEF (44), P2 (74), P3 (32), OUT (9) are recorded as boundaries inside the PBI that borders them, so nobody builds them by accident |
| Option C stays archival | Project #4 and the 232 historical issues are not closed, relabelled or migrated |

Roughly **60 to 70 PBIs** across 19 epics, against 595 rows. That ratio is the point: an issue per row would be a
filing system, not a plan.

## Board fields

| Field | Type | Values | Why |
|---|---|---|---|
| Epic | single-select | E-PRE, E-FND, E-ROLE, E-SF, E-RULES, E-ORD, E-ADM, E-MIG, E-ERP, E-RPT, E-INT, E-NOTF, E-NFR, E-DATA, E-EVT, E-DOD, E-ACC, E-MKT, E-HND | Follows the register's own sections, so an epic is something the client can recognise |
| Register ids | text | e.g. `ADM-27, RPT-11` | The scope citation. Free text because a PBI can cite many rows |
| Scope class | single-select | P1, P1-L, P1-E, DLV, mixed | P1-L bounds what "done" means; P1-E cannot be finalised before PRE-09 |
| Stage | single-select | S1, S2, per PRE-09 | **The contractual stage never moves for engineering convenience** |
| Status | single-select | Blocked, Ready, In progress, In review, Verified, Accepted | "Verified" and "Accepted" are deliberately separate: the cost pilot is Verified and not Accepted |
| ERP blocked | single-select | no, yes (P1-E), partial | Set to yes when any cited row is P1-E |
| Design dependency | single-select | none, needs design, design approved | Every storefront slice is behind OD-01 |
| Data-integrity dependency | text | e.g. `A11`, `B6`, `A12 price` | The workstream item that must be measured first |
| Client decision | text | e.g. `CX-01`, `OD-12` | Empty for most PBIs |
| Acceptance evidence | text | e.g. `AC-05, t17` | The Section N rows plus the local scenario or probe |
| Delivery order | number | 1 to n | The proposed sequence, so the board carries it rather than a separate document |

No iteration field: GitHub will not create one through the API, so Sprint stays a single-select if it is wanted at
all. That trap cost time on Project #4 and is recorded so it is not rediscovered.

## Views

| View | Shape | Purpose |
|---|---|---|
| Delivery order | table, sorted by Delivery order | The working list. What to pick up next |
| By epic | board, grouped by Epic | Progress against the register's own structure, for a client conversation |
| Blocked | table, filtered to Status = Blocked | What is waiting, and on whom. The one view that should shrink |
| ERP-gated | table, filtered to ERP blocked != no | The 19 P1-E rows plus MIG-14, in one place, so PRE-09's approval unblocks a visible set |
| Needs a client decision | table, filtered to Client decision not empty | The agenda for the next Stage 1 review, maintained as a by-product |
| Acceptance | table, filtered to Status in Verified, Accepted | What is claimable, and what is not yet |

## Issue templates

Two, matching the classification the repository already enforces.

**Requirement PBI**: outcome and title; register ids; scope class per id; stage; dependencies; ERP-blocked; design
dependency; data-integrity dependency; client-decision dependency; acceptance evidence expected; the explicit
non-goals (the DEF, P2, P3 or OUT rows this slice borders). It carries the same scope-check boxes as the PR
template, so the PBI and the PR that closes it make the same four statements.

**Internal task**: category (governance, ci, test-infrastructure, tooling, security-maintenance, documentation); a
statement of no client-facing change; the paths the category allows. No register id, because internal work has
none.

Neither template invents acceptance criteria. Criteria come from the spec, which comes from the register.

## The fifteen seeded PBIs, as they exist on the board

Read from the live Project on 4 October 2026, after A11 and the price matrix, and after the classification
correction. **This table describes what exists.** The earlier proposal table, written before those probes ran, is
gone: it promised a price-integrity PBI and a standalone A11 PBI, and neither exists, because neither should.

| Order | Issue | PBI | Epic | Register ids | Scope | Stage | Status | ERP | Design | Data-integrity | Client decision | Depends on |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | #241 | Bilingual platform baseline: English default, Arabic fully delivered right to left | E-FND | `FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14` | P1 | S1 | **Ready** | no | none | - | - | - |
| 2 | #242 | Information architecture and URL structure, in both languages | E-FND | `IA-01 and 31 more, listed in the issue` | mixed | S1 | **Blocked** | no | none | - | - | #241 bilingual platform baseline |
| 3 | #243 | ERP Integration Specification approved (PRE-09) | E-PRE | `PRE-09, ERP-10` | DLV | S1 | **Blocked** | yes (P1-E) | none | B1 and A12 raised M16 to M18 | The ERP technical meeting, targeted week 4 | - |
| 4 | #244 | Infrastructure provisioned for development, staging and production | E-PRE | `PRE-01 and 5 more, listed in the issue` | mixed | S1 | **Blocked** | no | none | - | OD-27 hosting outside Egypt, in writing; OD-15 volumes; OD-14 operating budget | - |
| 5 | #246 | The inventory report counts one physical item once, not once per language | E-RPT | `RPT-10, FIX-04, NFR-04` | P1-L | S1 | **Blocked** | partial | none | B10 measured defect (P-020); B11 open | - | #241 bilingual platform baseline |
| 6 | #247 | The catalogue migration does not corrupt Arabic product or variation content | E-MIG | `MIG-02, MIG-09, MIG-13, SSC-21` | P1-L | S1 | **Blocked** | no | none | B1 trigger measured; A11 measured (t19) | CR-06 a real, unmodified Amazon sample export | #249 catalogue import specification |
| 7 | #249 | Catalogue import specification, validated against the real source file | E-MIG | `PRE-05 and 6 more, listed in the issue` | mixed | S1 | **Blocked** | no | none | A5 duplicate SKU resolution | CR-06 a real, unmodified Amazon sample export; CR-07 Professional Seller account and Category Listings Report | - |
| 8 | #245 | ERP adapter seam: one commercial item resolves to one ERP stock item | E-ERP | `ERP-01, ERP-02, ERP-10, INT-16` | P1 | S1 | **Blocked** | partial | none | B1 measured (closed); M17 open | - | #243 the ERP Integration Specification, for the behaviour behind the seam; #241 bilingual platform baseline |
| 9 | #248 | Order status model with a queryable status history | E-ORD | `ORD-01 and 12 more, listed in the issue` | mixed | S1 | **Blocked** | no | none | D6 order language context; D7 shipment status history | - | #241 bilingual platform baseline |
| 10 | #250 | Design system and interface design delivered as working HTML and CSS | E-PRE | `PRE-07, PRE-08` | DLV | S1 | **Blocked** | no | needs design | - | OD-01 brand name, logo and visual identity | - |
| 11 | #251 | Launch staff roles and permissions | E-ROLE | `ROLE-01, ROLE-02, ROLE-07, ROLE-08, ROLE-09` | mixed | S1 | **Blocked** | no | none | - | CX-01 which staff roles hold financial permission | #241 bilingual platform baseline |
| 12 | #252 | Product cost: carry CX-01 and OD-12 to the Stage 1 gate | E-ADM | `ADM-27, RPT-11` | P1 | S1 | **Verified** | no | none | - | CX-01 staff financial permission (AC-6); OD-12 cost basis, who enters it, and zero versus missing (AC-7) | - |
| 13 | #253 | Header, navigation and footer, mirrored right to left | E-SF | `NAV-01 and 9 more, listed in the issue` | mixed | S1 | **Blocked** | no | needs design | - | OD-01 brand identity | #250 design system; #241 bilingual platform baseline; #242 information architecture |
| 14 | #254 | Product details page, in both languages | E-SF | `PDP-01 and 21 more, listed in the issue` | mixed | S1 | **Blocked** | partial | needs design | A3 variation identity (measured working) | OD-01 brand identity; OD-13 authenticity and warranty policy | #250 design system; #253 header and navigation; #241 bilingual platform baseline |
| 15 | #255 | Checkout and order placement, in both languages | E-SF | `CHK-01 and 13 more, listed in the issue` | mixed | S1 | **Blocked** | yes (P1-E) | needs design | B6 concurrency (needs staging); D6 order language context | OD-05 COD; OD-09 VAT and invoicing; OD-19 payment methods at launch; OD-29 COD verification | #250 design system; #254 product details page; #245 ERP adapter seam |

Every `Depends on` value is a real GitHub issue number. They were delivery-order numbers at first, which on
GitHub resolve to historical Option C issues, and that was corrected: the shorthand is never used in an issue
body or a board field.

Each PBI's body cites its exact delivery ids and names the non-delivery rows of the surrounding register section
that it excludes. **131 cited ids across the fifteen PBIs were validated against `docs/scope/register-ids.json`:
all real, all delivery scope, no ranges.**

Only **#241** is `Ready`. Everything else waits on a client decision, on PRE-09, or on a predecessor. **#252 is
`Verified`, not `Accepted`**, and is the reference case for that distinction.

## What the probes changed in the backlog

All four probes have now run: B1 and A12 (t17, t18), then A11 and the price matrix (t19, t20). The consequences
below are the settled ones, and they replace the interim list written between the two pairs.

| Change | Reason |
|---|---|
| **No price PBI exists, and none will be created from this evidence** | The price matrix measured every contracted price-maintenance path as working: the wp-admin product form (ADM-25, ADM-26), the wp-admin variations AJAX save (ADM-33), the native CSV importer (MIG-13), and WooCommerce's scheduled-sales cron. Only REST, WP-CLI and custom code leave a stale Arabic price, and none of those is a contracted price-maintenance path; ADM-28, scheduled price changes, is **P2**. The residual risk is a **developer-quality safeguard** for any future slice that chooses to write prices programmatically, recorded in the workstream document and in #254. It is not a PBI and not a class |
| **No standalone A11 PBI exists** | A11 is a probe: `internal:test-infrastructure / verification`. It has run, and what it produced is evidence, not scope |
| **The contracted outcome A11 exposed belongs to #247** | MIG-02, MIG-09, MIG-13 and SSC-21 independently require migration and Arabic content integrity, so the outcome is contracted and owns a requirement PBI. A11's result also decided the correction: sequencing, not runtime code, because creating all sources first and translating second avoids the defect entirely |
| **No stock-mechanism fix** | B1 refuted the hypothesis that stock shared the cost gap. WCML hooks `woocommerce_product_set_stock` and `woocommerce_variation_set_stock` directly, so stock never depended on `save_post`. This removed anticipated work rather than adding it |
| **#245 records the one-commercial-item identity constraint** | It follows from ERP-01 and is settled before the ERP meeting. Where the canonical mapping is stored stays an architecture decision for PRE-09 and the implementation design |
| **#246 remains a contracted inventory-report defect** | P-020 measured the double count on RPT-10, a P1-L row |
| **The other A12 field findings attach to their owning slices** | `weight` and dimensions to shipping, `catalog_visibility` to the catalogue admin, `manage_stock` to PRE-09 because ERP-07 is P1-E. None became a PBI of its own |
| **Nothing was created for a non-Option-B finding** | `product_cat` needs interpretation first, since WPML translates taxonomies as their own elements; a custom field not being copied is a configuration rule. Verification defects outside current scope are evidence, not new scope |

**The rule this round enforced in both directions:** only contracted behaviours produce requirement PBIs. A risk or
a probe does not, however real the finding.

## Range citations, and the error they caused

The first seeding cited register rows as ranges, and a range quietly includes non-delivery rows. A check against
`docs/scope/register-ids.json` found two: **IA-36 (P2)** inside "IA-01 to IA-36", and **CHK-13 (P2)** inside
"CHK-01 to CHK-13". Both were corrected, and so were three more ranges the same check exposed once the ids were
expanded:

| PBI | Range cited | Delivery rows actually cited | Excluded, and named in the issue |
|---|---|---|---|
| #242 | IA-01 to IA-36 | 31 | IA-12, IA-13, IA-34, IA-36 (P2), IA-22 (DEF) |
| #248 | ORD-01 to ORD-14 | 11 | ORD-09, ORD-10, ORD-11 (DEF) |
| #251 | "ROLE rows (Section B)" | 5 | ROLE-03, ROLE-04, ROLE-05, ROLE-06, ROLE-10 (DEF), ROLE-06a (P2), ROLE-11 (P3) |
| #254 | PDP-01 to PDP-24 | 22 | PDP-20, PDP-23 (P2) |
| #255 | CHK-01 to CHK-13 | 12 | CHK-13 (P2) |

Every PBI now cites exact ids, and each issue body names the excluded neighbours with their scope, so nobody
builds one by mistake. **131 cited ids were re-validated: all real, all delivery scope, no ranges left.**

**Cite exact ids, never a range.** A range is how non-delivery scope gets into a delivery board.

## What was deliberately not done

- The remaining 45 to 55 PBIs are not created.
- The 232 Option C issues are not closed, relabelled or migrated, and Project #4 is not touched.
- It does not give any P1-E row acceptance criteria, because PRE-09 is unapproved.
- It does not turn a measured defect outside Option B into work.
- It does not build a general multilingual synchronisation framework. Each finding attaches to the PBI that owns
  the behaviour.
