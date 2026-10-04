# Option B delivery board: proposal

Written 4 October 2026, after B1 and A12. **A proposal for review. Nothing is created from it**: no GitHub
Project, no issues, no fields, no views, no templates. The historical Option C Project #4 is untouched and stays
archival.

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

## The first fifteen PBIs, with every field filled

Ordered as proposed. Later PBIs follow the epic structure in
`docs/2026-10-04-option-b-backlog-structure.md`; the point of listing these is that the shape is reviewable.

| # | PBI | Epic | Register ids | Scope | Stage | Depends on | ERP | Design | Data-integrity | Client decision | Acceptance evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Arabic and English price agree after any supported save | E-SF | PDP-05, PLP-08, BR-005 | P1 | S1 | - | no | none | **A12 price** (measured defect) | - | A new scenario in the pilot's pattern, all five channels, plus an Arabic order at the changed price |
| 2 | wp-admin translation of a variable product produces a correct Arabic parent and variations | E-ADM | ADM-54, SSC-12, NFR-04 | P1 | S1 | - | no | none | **A11**, then A10 | - | A probe through the real wp-admin flow, not WP-CLI |
| 3 | A batch that creates products cannot corrupt an existing translation group | E-MIG | MIG-01, MIG-03, MIG-06, MIG-09, MIG-13 | P1-L | S1 | 2 | no | none | **B1 trigger** | - | The t17 trigger sequence, asserted not to corrupt; plus the MIG-13 precondition test |
| 4 | Infrastructure provisioned | E-PRE | PRE-01, PRE-02 | DLV | S1 | - | no | none | - | **OD-27**, OD-15, OD-14 | The environment exists and is reachable |
| 5 | Development, staging and production environments | E-PRE | PRE-03a, PRE-03b, NFR-09 | DLV, P1 | S1 | 4 | no | none | - | OD-27 | Staging serves Arabic `/ar/` URLs, which unblocks the rendered-Arabic checks the pilot could not run |
| 6 | ERP Integration Specification approved | E-PRE | **PRE-09** | DLV | S1 | - | **n/a** | none | B1, A12 (M16 to M18) | the ERP meeting | The approved specification, with its own acceptance criteria |
| 7 | Bilingual platform baseline | E-FND | A4 rows, FIX-01 to FIX-10, NFR-04, NFR-04a | P1 | S1 | 5 | no | none | - | - | Arabic right-to-left storefront, English-only admin (ADM-159) |
| 8 | Information architecture and URL structure | E-FND | IA-01 to IA-36 | P1, P1-L | S1 | 7 | no | none | - | - | The agreed URL map, in both languages |
| 9 | ERP adapter seam | E-ERP | **ERP-01**, ERP-02, ERP-10, INT-16 | P1 | S1 | 1, 3 | **partial** | none | B1 (closed), M17 | - | The seam exists, is behind an adapter with retries and logging, and resolves one commercial item to one mapping |
| 10 | Inventory report counts one physical item once | E-RPT | **RPT-10** | P1-L | S1 | 7 | partial | none | B10 (measured defect), B11 | - | A translation-group-aware query; P-020's double count gone |
| 11 | Catalogue import specification | E-MIG | MIG-01, MIG-03, MIG-06, MIG-19 | P1-L | S1 | - | no | none | - | **CR-06**, CR-07 | A validation pass over the real sample file, reporting creates, updates and rejects |
| 12 | Design system and interface design | E-PRE | PRE-07, PRE-08 | DLV | S1 | - | no | **is the design** | - | **OD-01** | HTML and CSS with tokens, two revision rounds |
| 13 | Launch roles and permissions | E-ROLE | ROLE rows (P1, P1-L) | P1, P1-L | S1 | 7 | no | none | - | **CX-01** | Dependent criteria stay `pending CX-01` until the client answers |
| 14 | Product cost, contractual closure | E-ADM, E-RPT | ADM-27, RPT-11 | P1 | S1 | - | no | none | - | **CX-01**, **OD-12** | **Technically verified by the product-cost pilot; contractual acceptance remains pending CX-01 and OD-12.** No code work: this PBI exists only to carry the two decisions to the Stage 1 gate |
| 15 | Order status model and history | E-ORD | ORD-01 to ORD-14 | P1, P1-L | S1 | 7 | no | none | D6, D7 | - | ADR-0002's minimum shipment state record; P-018's gap closed |

PBI 14 is the pattern for anything already built but not accepted: it carries decisions, not work, and its Status
is Verified rather than Accepted.

## What changed in the backlog because of B1 and A12

| Change | Reason |
|---|---|
| **A new PBI, now first**: Arabic and English price agree after any supported save | A12 measured `regular_price` and `sale_price` not reaching the Arabic record on a code-level save. Customer-facing and financial. It did not exist in the pre-probe backlog |
| **E-MIG gains a data-integrity dependency and a new PBI** | B1 measured that a batch creating products in one process corrupts an existing translation group, breaks variation stock sync and overwrites Arabic variation titles. The migration is the batch, so this is now an E-MIG blocker, not a theoretical risk |
| **A11 is promoted to a PBI of its own, ahead of E-MIG** | It decides how much of the B1 corruption is a production concern rather than a WP-CLI artefact, and therefore how much work PBI 3 is |
| **E-ERP-1 keeps its position but gains M17** | B1 settled the store-side question the seam was waiting on, which is why it did not move further forward. M17 (per-variant ERP stock) is new |
| **The stock mechanism needs no fix** | The pre-probe backlog carried B1 as a suspected second cost defect. It is not: WCML hooks the stock write directly. That removed anticipated work rather than adding it |
| **E-ORD-3 gains a data-integrity dependency** | A12 measured `weight` and dimensions not following, so an Arabic order could be rated on stale shipping dimensions |
| **E-ADM-3 gains one** | `catalog_visibility` does not follow |
| **Nothing was added for a non-Option-B field** | `product_cat` needs interpretation before it is called anything, and custom fields are a configuration rule, not a defect. Neither created a PBI |

## What this proposal deliberately does not do

- It does not create the Project, any field, any view, any template or any issue.
- It does not close, relabel or migrate the 232 Option C issues, or touch Project #4.
- It does not give any P1-E row acceptance criteria, because PRE-09 is unapproved.
- It does not turn a measured defect outside Option B into work.
- It does not build a general multilingual synchronisation framework. Each finding attaches to the PBI that owns
  the behaviour.
