# Option B delivery board

Written 4 October 2026 after B1 and A12 as a proposal; **created on 4 October after A11 and the price matrix**,
with the classification correction applied.

- **Board: https://github.com/orgs/Mizzey-Platform/projects/1**, "Mizzey Option B Delivery",
  organisation-owned and private. The suggested title carried an em dash; this repository's house rules
  forbid one in markdown (`tools/house_rules.py`), so the board is named plainly and the document and the
  board agree rather than describing one name and using another.
- 11 custom fields and 6 views created; **Status carries the Verified and Accepted distinction** in its own
  option descriptions.
- **The complete Stage 1 backlog is seeded**: 74 PBIs, #241 to #255 on 4 October and #266 to #324 on 5 October
  2026 (D-11). Nine second-release slices are recorded in `docs/scope/backlog-ownership.json` and not created.
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

## Board fields, as the live Project defines them

**Read from the live Project.** Where a field is a single-select, the option set below is exactly what the Project
holds; the document does not describe a field model the board does not have.

| Field | Type | Options, live | Why |
|---|---|---|---|
| Epic | single-select | `E-PRE` / `E-FND` / `E-ROLE` / `E-SF` / `E-RULES` / `E-ORD` / `E-ADM` / `E-MIG` / `E-ERP` / `E-RPT` / `E-INT` / `E-NOTF` / `E-NFR` / `E-DATA` / `E-EVT` / `E-DOD` / `E-ACC` / `E-MKT` / `E-HND` | Follows the register's own sections, so an epic is something the client can recognise |
| Register ids | text | the exact delivery ids, listed in full | The scope citation. Never a range: a range quietly includes non-delivery rows |
| Scope class | single-select | `P1` / `P1-L` / `P1-E` / `DLV` / `mixed` | **Computed from the cited ids**: one distinct delivery scope, that scope; more than one, `mixed`. Never chosen from the PBI's purpose |
| Stage | single-select | `S1` / `S2` / `mixed` / `per PRE-09` | **The exact stages of the cited register rows.** One distinct stage, that stage; more than one, `mixed`. `per PRE-09` exists for a row the register defers to the ERP specification. **Not** engineering scheduling, and never derived from Scope class |
| Status | single-select | `Blocked` / `Ready` / `In progress` / `In review` / `Verified` / `Accepted` | `Verified` and `Accepted` are deliberately separate: the product-cost pilot is Verified and not Accepted |
| ERP blocked | single-select | `no` / `partial` / `yes` | **A dependency, and only a dependency.** See the definitions below |
| Design dependency | single-select | `none` / `needs design` / `design approved` | Every storefront slice sits on the design system, #250. OD-01 gates approval of the design, not the structural work (D-10) |
| Data-integrity dependency | text | e.g. `A11`, `B6` | The workstream item that must be measured first |
| Client decision | text | e.g. `Acceptance gate (D-10): OD-01` | The PBI's open inputs, each named with its class under constitution M-10. Empty when there is none |
| Acceptance evidence | text | e.g. the verification record | What will show the outcome was reached |
| Delivery order | number | 1 to n | **Engineering scheduling**, which is a different concept from Stage and never changes a contractual stage |
| Dependencies | text | real GitHub issue numbers | Never a delivery-order number: on GitHub `#1` is a historical Option C issue |

### `ERP blocked`, defined

| Option | Meaning |
|---|---|
| `no` | No unresolved ERP or PRE-09 dependency prevents this PBI |
| `partial` | Part of the PBI can proceed, but some behaviour or final acceptance waits on the ERP or PRE-09 |
| `yes` | The PBI materially depends on the ERP meeting or PRE-09 before it can complete |

**P1-E is never encoded here.** Scope class records scope; this field records a dependency. `#243` is the proof:
it is `yes` while citing a **single DLV row, PRE-09**, because the ERP meeting and the specification it produces
are its dependency. ERP-10 is owned by #245 and is context for this PBI, not a row its acceptance closes.

### Stage and Delivery order are different concepts

| | Represents | Authority |
|---|---|---|
| **Stage** | The exact stages of the cited register rows | The Feature Register, through `docs/scope/register-ids.json` |
| **Delivery order** | When the work is scheduled | Engineering sequencing, which never moves a contractual stage |

No iteration field: GitHub will not create one through the API, so Sprint stays a single-select if it is wanted at
all. That trap cost time on Project #4 and is recorded so it is not rediscovered.

## Views

| View | Shape | Purpose |
|---|---|---|
| Delivery order | table, sorted by Delivery order | The working list. What to pick up next |
| By epic | board, grouped by Epic | Progress against the register's own structure, for a client conversation |
| Blocked | table, filtered to Status = Blocked | What cannot start. Since D-10 that means a predecessor PBI is not delivered: a client, vendor or ERP input alone does not put a PBI here. The one view that should shrink |
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

## The 74 PBIs, as the live board holds them

**Read from the live Project, which is the operational state; this document records it, never the reverse.**
Regenerated 6 October 2026, by `tools/gen_backlog_docs.py` from the live Project.

| Order | Issue | Title | Epic | Register ids | Scope class | Stage | Status | ERP blocked | Design dependency | Data-integrity dependency | Client decision | Depends on |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | #241 | Bilingual platform baseline: English default, Arabic fully delivered right to left | E-FND | FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14 | **P1** | **S1** | **Verified** | **no** | none | - | - | - |
| 2 | #242 | Information architecture and URL structure, in both languages | E-FND | 30 ids, listed in the issue | **mixed** | **S1** | **Verified** | **no** | none | - | - | #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113. Its remaining AC-9 browser matrix is not a blocker for this PBI |
| 3 | #243 | ERP Integration Specification approved (PRE-09) | E-PRE | PRE-09 | **DLV** | **per PRE-09** | **In progress** | **yes** | none | B1 and A12 raised M16 to M18 | ERP input (D-10, D-12): the question pack is ready to send, not sent. The SKU is the contracted baseline. Awaiting ERP input; blocks no unrelated development | #245 owns ERP-10, the adapter rule, which this specification is context for |
| 4 | #244 | Infrastructure provisioned for development, staging and production | E-NFR | NFR-08, NFR-09 | **P1** | **S1** | **In progress** | **no** | none | - | Production and launch gate only (D-10): OD-27 written hosting instruction, not client-approved; OD-14 operating budget. Development and staging delivered on the developer machine by owner decision, not client-confirmed. A public staging address needs one Cloudflare sign-in | - |
| 5 | #246 | The inventory report counts one physical item once, not once per language | E-RPT | RPT-10 | **P1-L** | **S1** | **Verified** | **partial** | none | B10 measured defect (P-020); B11 open | - | #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113. It owns FIX-04 and NFR-04. Its remaining AC-9 browser matrix is not a blocker for this PBI |
| 6 | #247 | The catalogue migration does not corrupt Arabic product or variation content | E-MIG | MIG-02, MIG-09, MIG-13, SSC-21 | **mixed** | **S1** | **Blocked** | **no** | none | B1 trigger measured; A11 measured (t19) | Acceptance gate (D-10): CR-06 a real, unmodified Amazon sample export. Development proceeds on a fixture | #249 catalogue import specification, not yet delivered: the only development blocker |
| 7 | #249 | Catalogue import specification, validated against the real source file | E-MIG | MIG-01, MIG-03, MIG-04, MIG-06, MIG-19 | **P1-L** | **S1** | **Ready** | **no** | none | A5 duplicate SKU resolution | Acceptance gate (D-10): CR-06 a real, unmodified Amazon sample export; CR-07 Professional Seller account and Category Listings Report. Development proceeds on a fixture in the documented export format. CX-04 resolved by the owner (D-12), not client-confirmed: each developer-operated run is a recorded Import Run, with no self-service import history | - |
| 8 | #245 | ERP adapter seam: one commercial item resolves to one ERP stock item | E-ERP | ERP-01, ERP-02, ERP-10, INT-16 | **P1** | **S1** | **Ready** | **partial** | none | B1 measured (closed); M17 open | - | #243 the ERP Integration Specification, for the production behaviour behind the seam: an ERP input, not a development blocker, the seam is built against a mock and a contract on the D-10 invariants; #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113 |
| 9 | #248 | Order status model with a queryable status history | E-ORD | 13 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | needs design | D6 order language context; D7 shipment status history | - | #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113 |
| 10 | #250 | Design system and interface design delivered as working HTML and CSS | E-PRE | PRE-03b | **DLV** | **S1** | **Ready** | **no** | needs design | - | Acceptance gate (D-10): OD-01 brand name, logo and visual identity. Structural design proceeds on neutral tokens; approval of the interface design waits for OD-01 | - |
| 11 | #251 | Launch staff roles and permissions | E-ROLE | ROLE-01, ROLE-02, ROLE-07, ROLE-08, ROLE-09 | **mixed** | **S1** | **Ready** | **no** | none | - | CX-01 resolved by the owner (D-10), not client-confirmed: the Accountant role exists at launch, least privilege, refunds a separate capability. ROLE-06 still reads DEF in the register, so the Accountant criteria wait for the register correction | #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113 |
| 12 | #252 | Product cost: carry CX-01 and OD-12 to the Stage 1 gate | E-ADM | ADM-27, RPT-11 | **P1** | **S1** | **Verified** | **no** | none | - | CX-01 resolved by the owner (D-10), not client-confirmed (AC-6). Acceptance gate: OD-12 cost basis, who enters it, and zero versus missing (AC-7), a client input | - |
| 13 | #253 | Header, navigation and footer, mirrored right to left | E-SF | 10 ids, listed in the issue | **P1** | **S1** | **Blocked** | **no** | needs design | - | Acceptance gate (D-10): OD-01 brand identity. Not a development blocker | #250 design system, not yet delivered: the only development blocker; #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113; #242 information architecture: SATISFIED, merged 5 October 2026 as 3ff7488 |
| 14 | #254 | Product details page, in both languages | E-SF | 22 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **partial** | needs design | A3 variation identity (measured working) | Acceptance gate (D-10): OD-01 brand identity. Content input: OD-13 authenticity and warranty policy, a placeholder until supplied | #250 design system and #253 header and navigation, not yet delivered: the development blockers; #241 bilingual platform baseline: SATISFIED, merged 4 October 2026 as 405a113 |
| 15 | #255 | Checkout and order placement, in both languages | E-SF | 14 ids, listed in the issue | **P1** | **S1** | **Blocked** | **partial** | needs design | B6 concurrency (needs staging); D6 order language context | Owner working defaults, not client-confirmed (D-10): OD-05 COD capability built, the launch switch stays with the client; OD-19 cards and wallets, activation depends on the provider. Client input and acceptance gate: OD-09 VAT and invoicing. OD-29 COD verification stays P2 | #250 design system, #254 product details page and #245 ERP adapter seam, not yet delivered: the development blockers |
| 16 | #266 | Pre-development documents delivered and approved | E-PRE | 7 ids, listed in the issue | **DLV** | **S1** | **Verified** | **no** | none | - | Blocks final acceptance: the Stage 1 Approval Record, blank. PRE-08 approval pending / sent date not evidenced: the deemed-approval rule is not applied (D-12) | - |
| 17 | #267 | Store operations walkthrough held on the live admin, with the Section G10 list confirmed | E-PRE | PRE-07 | **DLV** | **S1** | **Ready** | **no** | none | - | Blocks final acceptance: client attendance and written confirmation of the G10 list. Overdue in its literal timing, prepared, not held (D-12) | #244: staging exists since 5 October 2026; context, not a blocker |
| 18 | #268 | Definition of done applied to every Stage 1 PBI, with the evidence recorded | E-DOD | 9 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | - | Blocks final acceptance: #244 staging environment | - |
| 19 | #269 | Cross-cutting standards met: mobile first, performance, locale formatting, security, data integrity, accessibility, privacy, scalability | E-NFR | 9 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | A7 verified working; A12 measured (nine of fourteen fields do not follow a code-level save); B6 not yet tested (needs staging) | Configurable working default: OD-15; Blocks production or launch only: OD-27, OD-14 | #244: context, not a blocker |
| 20 | #270 | Observability: application logs, error monitoring, integration logs and admin audit logs | E-NFR | NFR-10 | **P1-L** | **S1** | **Ready** | **no** | none | - | Blocks production or launch only: OD-14 | #244: context, not a blocker |
| 21 | #271 | The remaining fixed core decisions hold across the build | E-FND | 9 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | - | - | #290: context, not a blocker; #281: context, not a blocker; #305: context, not a blocker |
| 22 | #272 | Vision, objectives and platform-type guardrails are met by the delivered store | E-FND | 15 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | - | - | #278: context, not a blocker; #311: context, not a blocker |
| 23 | #273 | The contracted data model exists: every launch entity is stored, related and documented | E-DATA | 15 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | D1 verified working (observed); D4, D5 not yet tested | Blocks final acceptance: CX-03, CX-04. CX items resolved by the owner (D-12), not client-confirmed | #307: context, not a blocker; #248: context, not a blocker; #249: context, not a blocker |
| 24 | #274 | Event Tracking Plan: every event, trigger, parameter and source of truth per business metric | E-EVT | EVT-20 | **DLV** | **S1** | **Ready** | **no** | none | - | Configurable working default: OD-21; Blocks final acceptance: CR-13 | - |
| 25 | #275 | Staff accounts with two-factor sign-in and a searchable log that ordinary users cannot delete | E-ADM | ADM-130, ADM-131, ADM-132, ADM-133, ADM-134 | **mixed** | **S1** | **Ready** | **no** | none | - | Blocks final acceptance: ROLE-06 register correction, CX-03. CX items resolved by the owner (D-12), not client-confirmed | #251: context, not a blocker |
| 26 | #276 | Product management in admin: content, media, pricing, variants, SEO fields, flags, bulk import, export and update | E-ADM | 28 ids, listed in the issue | **mixed** | **S1** | **Ready** | **partial** | none | A3 verified working; A4 shared SKU by design; A10, A11 (the browser translation flow is a staging check); A12 measured | ERP input: PRE-09; Content or client input: CR-15; Blocks final acceptance: CX-03. CX items resolved by the owner (D-12), not client-confirmed | #245: context, not a blocker; #247: context, not a blocker; #273: context, not a blocker |
| 27 | #277 | Categories, brands, collections, home page sections and static pages managed from admin | E-ADM | 7 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | needs design | A12 (product_cat is translated by WPML as its own element) | Content or client input: CR-15; Blocks final acceptance: OD-01 | #250: context, not a blocker; #311: context, not a blocker |
| 28 | #278 | Everyday admin tasks in the standard administration: quick product entry, bulk edits, search, export, mobile checks | E-ADM | 12 ids, listed in the issue | **mixed** | **S1** | **Ready** | **partial** | none | A5 verified defect, bounded (SKU lookup); A12 (the list-table bulk-edit channel was not measured) | ERP input: PRE-09 | #267: context, not a blocker; #245: context, not a blocker |
| 29 | #279 | Customer records in admin: profile, internal notes, and suspension with a reason | E-ADM | ADM-100, ADM-103, ADM-104 | **mixed** | **S1** | **Ready** | **no** | none | - | - | - |
| 30 | #280 | Order management in admin: find, annotate, ship, cancel, refund and print | E-ADM | 7 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | B7 measured working on the WooCommerce side; D7, F1, F3 not yet tested | Content or client input: OD-09; Blocks production or launch only: OD-18 | #248: context, not a blocker; #291: context, not a blocker; #283: context, not a blocker |
| 31 | #281 | Promotion engine: discounts, conditions, limits and schedules configurable from admin | E-RULES | 17 ids, listed in the issue | **P1** | **S1** | **Ready** | **no** | none | - | - | - |
| 32 | #282 | Merchandising controls: badges, featured products, manual ordering, curated collections and related products | E-RULES | 7 ids, listed in the issue | **P1** | **S1** | **Ready** | **no** | needs design | A12 (catalog_visibility and other fields do not follow a code-level save) | Blocks final acceptance: OD-01 | #250: context, not a blocker |
| 33 | #283 | Payments through Paymob: verified callbacks, no duplicate charge, refunds from the order screen | E-ORD | 16 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | needs design | B8 not yet tested (failed payment, both languages); F3 not yet tested multilingually | Vendor or account input: CR-01, Wallet approval; Configurable working default: OD-19, OD-05 | #248: context, not a blocker; #255: context, not a blocker; #245: context, not a blocker |
| 34 | #284 | Transactional email service, Google Analytics with Tag Manager, Meta Pixel and the Merchant Center feed connected | E-INT | INT-05, INT-09, INT-10, INT-12 | **mixed** | **S1** | **Ready** | **no** | none | - | Vendor or account input: Email provider account, Google, Meta and Merchant Center accounts; Configurable working default: OD-21; Blocks production or launch only: OD-18 | #245: context, not a blocker |
| 35 | #285 | Transactional emails for account, order, payment, shipping, delivery and refund | E-NOTF | 9 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | needs design | D6 not yet tested (the language an order was placed in decides the language of its emails) | Blocks production or launch only: OD-18; Content or client input: OD-01 | #248: context, not a blocker; #284: context, not a blocker |
| 36 | #286 | Sales report: revenue, net sales, orders, units, average order value, discounts and shipping | E-RPT | RPT-01 | **P1-L** | **S1** | **Ready** | **no** | none | D8 not yet tested; workstream E classes RPT-01 as not yet tested, low risk | - | - |
| 37 | #287 | Search engine controls: editable meta and canonical, redirects, missing-page log, sitemap and robots, hreflang | E-MKT | 8 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | none | - | Vendor or account input: Google Search Console and Bing Webmaster accounts; Blocks production or launch only: OD-18 | #242: SATISFIED; #311: context, not a blocker |
| 38 | #288 | Measurement and advertising tools ready for a marketing team: analytics, pixels, product feeds, attribution, consent | E-MKT | 7 ids, listed in the issue | **mixed** | **S1** | **Ready** | **no** | needs design | Workstream E: product-level identity inside GA4 is not yet tested | Vendor or account input: Google, Meta and TikTok accounts; Configurable working default: OD-21 | #274: context, not a blocker; #284: context, not a blocker |
| 39 | #289 | Promotions and coupons managed from admin: create, schedule, pause, keep the usage history | E-ADM | ADM-110, ADM-111, ADM-113, ADM-115 | **mixed** | **S1** | **Blocked** | **no** | none | - | - | #281: not yet delivered, a predecessor |
| 40 | #290 | Launch business rules enforced: welcome discount, free shipping on two items, explicit stacking, refund ceiling | E-RULES | 9 ids, listed in the issue | **P1** | **S1** | **Blocked** | **partial** | none | B6 not yet tested (BR-003, needs staging); B7 measured working on the WooCommerce side; B8 not yet tested | Configurable working default: OD-03, OD-04; ERP input: PRE-09 | #281: not yet delivered, a predecessor; #245: context, not a blocker |
| 41 | #291 | Shipping through Bosta with an explicit status mapping, governorate rates and cash-on-delivery reconciliation | E-ORD | 13 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | none | D7 not yet tested; F2 verified defect in the carrier layer (P-012); F5 requires OD-20; F6 verified defect (P-018) | Vendor or account input: CR-03, Bosta status code legend, OD-20 | #248: not yet delivered, a predecessor |
| 42 | #292 | Refunds recorded from the standard order screen, with the standard refund email | E-ORD | RET-08 | **P1-L** | **S1** | **Blocked** | **no** | none | F3 not yet tested multilingually; F4 not yet tested | Content or client input: OD-08 | #283: not yet delivered, a predecessor |
| 43 | #293 | A refund is recorded with its payment reference from the standard order screen | E-ADM | ADM-124 | **P1** | **S1** | **Blocked** | **no** | none | F3 not yet tested multilingually | - | #283: not yet delivered, a predecessor |
| 44 | #294 | Admin dashboard: sales figures, top products, and low and out-of-stock counts that count each item once | E-ADM | 12 ids, listed in the issue | **mixed** | **S1** | **Ready** | **partial** | none | B10 verified defect (P-020); B11 not yet tested; P-019 for ADM-09 | ERP input: PRE-09; Blocks final acceptance: CX-05. CX items resolved by the owner (D-12), not client-confirmed | #246: SATISFIED; #245: context, not a blocker; #248: context, not a blocker |
| 45 | #295 | Attributes, categories, brands and SEO fields mapped into the store in the initial migration | E-MIG | MIG-10, MIG-11, MIG-12, MIG-15 | **P1-L** | **S1** | **Blocked** | **no** | none | A11 (sequencing invariant, owned by #247); A12 (product_cat) | Blocks final acceptance: CR-06, CR-07; Blocks production or launch only: CR-09 | #249: not yet delivered, a predecessor |
| 46 | #296 | Product images imported to the Mizzey server with alt text, once rights are confirmed | E-MIG | MIG-16, MIG-17 | **P1-L** | **S1** | **Blocked** | **no** | none | - | Content or client input: CR-08, OD-26; Blocks production or launch only: CR-05 | #249: not yet delivered, a predecessor |
| 47 | #297 | The initial migration runs in the background, and the catalogue stays exportable | E-MIG | MIG-20, MIG-25 | **P1-L** | **S1** | **Blocked** | **no** | none | A11 (owned by #247) | Blocks production or launch only: CR-09 | #247: not yet delivered, a predecessor |
| 48 | #298 | Third parties work through a restricted role, Tag Manager and staging, under a written protocol | E-MKT | 6 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | none | - | Blocks final acceptance: CR-13, CX-02, CX-03. CX items resolved by the owner (D-12), not client-confirmed | #251: not yet delivered, a predecessor; #244: context, not a blocker; #275: context, not a blocker; #273: context, not a blocker |
| 49 | #299 | Products and variants are matched to the ERP, one stock identity per item in both languages | E-ERP | ERP-08 | **P1-E** | **S1** | **Blocked** | **yes** | none | C1, C2, C3, C4 require ERP clarification; A2 verified working; A5 verified defect, bounded | ERP input: PRE-09, CR-18 | #245: not yet delivered, a predecessor; #243: context, not a blocker |
| 50 | #300 | A sale on the website reduces the corresponding stock in the ERP, exactly once | E-ERP | ERP-04 | **P1** | **S1** | **Blocked** | **partial** | none | B4, B5 measured working; C8, C9, C10 require ERP clarification | ERP input: PRE-09, CR-16 | #245: not yet delivered, a predecessor; #243: context, not a blocker |
| 51 | #301 | No sale is confirmed against stock the ERP cannot validate, including when it is unreachable | E-ERP | ERP-05 | **P1** | **S1** | **Blocked** | **partial** | needs design | B6 not yet tested; C6, C11 | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #243: context, not a blocker; #255: context, not a blocker |
| 52 | #302 | Home page, in both languages, with sections the client edits and reorders | E-SF | 11 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | needs design | - | Blocks final acceptance: OD-01; Content or client input: CR-15; Blocks production or launch only: CR-05 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #311: context, not a blocker; #282: context, not a blocker |
| 53 | #303 | Product listing, category and collection pages with filters and sorting, in both languages | E-SF | 21 ids, listed in the issue | **P1** | **S1** | **Blocked** | **partial** | needs design | B2 measured working (each language record mirrors stock while the translation group is intact) | Blocks final acceptance: OD-01; Content or client input: CR-15; ERP input: PRE-09 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #245: context, not a blocker |
| 54 | #304 | Search that works in English and Arabic, with an admin-managed synonym list | E-SF | 9 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | needs design | A4 (a duplicate shares its original's SKU, which SRCH-02 searches) | Blocks final acceptance: OD-01; Content or client input: Initial synonym and correction entries | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #303: not yet delivered, a predecessor |
| 55 | #305 | Registration, sign-in and Google sign-in resolve to one account, with guest checkout kept open | E-SF | 16 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | needs design | - | Vendor or account input: Google sign-in credentials; Configurable working default: OD-03; Blocks final acceptance: OD-01 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #290: context, not a blocker; #255: context, not a blocker |
| 56 | #306 | Cart that survives registration, sign-in and return visits, in both languages | E-SF | 18 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **partial** | needs design | B1 measured (stock limits read one effective balance while the translation group is intact) | Configurable working default: OD-04, Guest cart retention window; ERP input: PRE-09; Blocks final acceptance: OD-01 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #305: not yet delivered, a predecessor; #281: context, not a blocker; #290: context, not a blocker; #245: context, not a blocker; #307: context, not a blocker |
| 57 | #307 | Wishlist for guests and customers, and recently viewed products | E-SF | WISH-01, WISH-02, WISH-03, WISH-04, WISH-07 | **P1** | **S1** | **Blocked** | **no** | needs design | - | Blocks final acceptance: OD-01 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor |
| 58 | #308 | My Account: profile, addresses, order history, tracking and invoice download | E-SF | 9 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | needs design | D1 verified working (observed); D5 not yet tested generally | Content or client input: OD-09; Blocks production or launch only: OD-18; Blocks final acceptance: OD-01 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #305: not yet delivered, a predecessor; #248: context, not a blocker; #307: context, not a blocker; #280: context, not a blocker |
| 59 | #309 | Moderated product reviews with ratings, and a review invitation after delivery | E-SF | REV-01, REV-02, REV-03, REV-04, REV-05 | **P1** | **S1** | **Blocked** | **no** | needs design | - | Blocks final acceptance: OD-01 | #250: not yet delivered, a predecessor; #254: not yet delivered, a predecessor; #248: context, not a blocker; #285: context, not a blocker |
| 60 | #310 | Trust and support pages, editable without a developer, in both languages | E-SF | 10 ids, listed in the issue | **P1** | **S1** | **Blocked** | **no** | needs design | - | Blocks production or launch only: CR-04, OD-18; Content or client input: OD-08, OD-13, CR-15; Blocks final acceptance: OD-01 | #250: not yet delivered, a predecessor; #253: not yet delivered, a predecessor; #242: SATISFIED; #311: context, not a blocker |
| 61 | #311 | Self-service storefront and product control: everything a customer sees is editable in both languages, with preview | E-ADM | 23 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **partial** | needs design | A8 verified working; A10, A11 (the browser translation flow is a staging check) | Blocks final acceptance: OD-01, CX-06; Content or client input: CR-15; ERP input: PRE-09. CX items resolved by the owner (D-12), not client-confirmed | #250: not yet delivered, a predecessor; #253: context, not a blocker |
| 62 | #312 | The standard e-commerce events fire across the funnel, in both languages | E-EVT | 17 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | none | Workstream E: product-level identity inside GA4 is not yet tested | Vendor or account input: Google, Meta and TikTok accounts; Configurable working default: OD-21 | #274: not yet delivered, a predecessor; #288: not yet delivered, a predecessor; #303: not yet delivered, a predecessor; #304: not yet delivered, a predecessor; #254: not yet delivered, a predecessor; #307: not yet delivered, a predecessor; #306: not yet delivered, a predecessor; #255: not yet delivered, a predecessor; #305: not yet delivered, a predecessor |
| 63 | #313 | Funnel and traffic reports available in Google Analytics | E-RPT | RPT-08, RPT-09 | **P1-L** | **S1** | **Blocked** | **no** | none | Workstream E: RPT-08 and RPT-09 not yet tested | Vendor or account input: GA4 property in a client-owned account | #312: not yet delivered, a predecessor; #288: not yet delivered, a predecessor |
| 64 | #314 | Adding to the cart and viewing the cart take account of ERP stock | E-ERP | ERP-03 | **P1-E** | **S1** | **Blocked** | **yes** | none | C5, C6, C7 require ERP clarification | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #306: not yet delivered, a predecessor; #243: context, not a blocker |
| 65 | #315 | Stock is restored in the ERP on a failed payment or a cancellation, and returned parcels are received | E-ERP | ERP-06 | **P1-E** | **S1** | **Blocked** | **yes** | none | B7 measured working on the WooCommerce side; B8, B9 not yet tested; C13; F4 | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #300: not yet delivered, a predecessor; #243: context, not a blocker |
| 66 | #316 | Stock appears in, and is changed through, the store admin as PRE-09 settles | E-ERP | ERP-07 | **P1-E** | **S1** | **Blocked** | **yes** | none | C12 requires ERP clarification | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #243: context, not a blocker |
| 67 | #317 | Stock on hand and stock available shown in admin from the ERP | E-ADM | ADM-70, ADM-72 | **P1-E** | **S1** | **Blocked** | **yes** | none | B2 measured working; B3 measured defect, conditional on group integrity; C12 | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #316: not yet delivered, a predecessor; #243: context, not a blocker |
| 68 | #318 | Inventory tasks whose method and timing PRE-09 sets | E-ADM | 6 ids, listed in the issue | **P1-E** | **per PRE-09** | **Blocked** | **yes** | none | C7, C12 require ERP clarification | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #243: not yet delivered, a predecessor |
| 69 | #319 | Inventory transaction records, as PRE-09 settles | E-DATA | ENT-04 | **P1-E** | **per PRE-09** | **Blocked** | **yes** | none | C12 | ERP input: PRE-09 | #245: not yet delivered, a predecessor; #243: not yet delivered, a predecessor |
| 70 | #320 | Initial stock quantities loaded where PRE-09 settles | E-MIG | MIG-14 | **P1-E** | **S1** | **Blocked** | **yes** | none | C14 requires ERP clarification | ERP input: PRE-09; Blocks production or launch only: CR-09 | #247: not yet delivered, a predecessor; #245: not yet delivered, a predecessor; #243: context, not a blocker |
| 71 | #321 | ERP stock acceptance scenarios, finalised in PRE-09, pass against the ERP test environment | E-ACC | AC-21, AC-22, AC-23, AC-24, AC-25 | **P1-E** | **S1** | **Blocked** | **yes** | none | B6 not yet tested (AC-22); C5, C6, C11, C13 | ERP input: PRE-09, CR-16 | #300: not yet delivered, a predecessor; #301: not yet delivered, a predecessor; #314: not yet delivered, a predecessor; #315: not yet delivered, a predecessor; #299: not yet delivered, a predecessor; #243: context, not a blocker |
| 72 | #322 | The customer journey holds end to end, from discovery to after-sales | E-FND | 10 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **no** | none | - | - | #302: not yet delivered, a predecessor; #304: not yet delivered, a predecessor; #254: not yet delivered, a predecessor; #306: not yet delivered, a predecessor; #305: not yet delivered, a predecessor; #255: not yet delivered, a predecessor; #248: not yet delivered, a predecessor; #292: not yet delivered, a predecessor |
| 73 | #323 | The client's acceptance scenarios pass as the UAT script | E-ACC | 20 ids, listed in the issue | **mixed** | **S1** | **Blocked** | **partial** | none | B6 not yet tested (AC-05); D3 verified working (AC-09); F2 (AC-16) | ERP input: PRE-09; Blocks final acceptance: CR-14 | #306: not yet delivered, a predecessor; #290: not yet delivered, a predecessor; #281: not yet delivered, a predecessor; #255: not yet delivered, a predecessor; #283: not yet delivered, a predecessor; #251: not yet delivered, a predecessor; #305: not yet delivered, a predecessor; #292: not yet delivered, a predecessor; #291: not yet delivered, a predecessor; #247: not yet delivered, a predecessor; #311: not yet delivered, a predecessor; #301: not yet delivered, a predecessor; #241: SATISFIED |
| 74 | #324 | Handover: code, design, documentation, accounts, environments, training and warranty | E-HND | 12 ids, listed in the issue | **DLV** | **S1** | **Blocked** | **no** | none | - | Blocks production or launch only: OD-27, OD-14, OD-18; Vendor or account input: Client-owned provider accounts | #244: not yet delivered, a predecessor; #250: not yet delivered, a predecessor; #298: not yet delivered, a predecessor; #274: not yet delivered, a predecessor; #323: not yet delivered, a predecessor |

### Exact register ids, every PBI in full

The table above abbreviates a long id list for readability. **This appendix is the complete citation**, and
it is what `tools/tests/test_board_metadata.py` reads, so **no PBI escapes Scope class and Stage
validation.** Both are generated from the live Project in the same pass, so there is one truth and not two.

- **#241** (5 ids): `FIX-04, FIX-04a, NFR-04, NFR-04a, NFR-14`
- **#242** (30 ids): `IA-01, IA-02, IA-03, IA-04, IA-05, IA-06, IA-07, IA-08, IA-09, IA-10, IA-11, IA-14, IA-15, IA-16, IA-17, IA-18, IA-19, IA-20, IA-21, IA-24, IA-25, IA-26, IA-27, IA-28, IA-29, IA-30, IA-31, IA-32, IA-35, NFR-03`
- **#243** (1 ids): `PRE-09`
- **#244** (2 ids): `NFR-08, NFR-09`
- **#246** (1 ids): `RPT-10`
- **#247** (4 ids): `MIG-02, MIG-09, MIG-13, SSC-21`
- **#249** (5 ids): `MIG-01, MIG-03, MIG-04, MIG-06, MIG-19`
- **#245** (4 ids): `ERP-01, ERP-02, ERP-10, INT-16`
- **#248** (13 ids): `ORD-01, ORD-02, ORD-03, ORD-04, ORD-05, ORD-06, ORD-07, ORD-08, ORD-12, ORD-13, ORD-14, ADM-86, ADM-87`
- **#250** (1 ids): `PRE-03b`
- **#251** (5 ids): `ROLE-01, ROLE-02, ROLE-07, ROLE-08, ROLE-09`
- **#252** (2 ids): `ADM-27, RPT-11`
- **#253** (10 ids): `NAV-01, NAV-02, NAV-03, NAV-04, NAV-05, NAV-06, NAV-07, NAV-08, NAV-09, NAV-10`
- **#254** (22 ids): `PDP-01, PDP-02, PDP-03, PDP-04, PDP-05, PDP-06, PDP-07, PDP-08, PDP-09, PDP-10, PDP-11, PDP-12, PDP-13, PDP-14, PDP-15, PDP-16, PDP-17, PDP-18, PDP-19, PDP-21, PDP-22, PDP-24`
- **#255** (14 ids): `CHK-01, CHK-02, CHK-03, CHK-04, CHK-05, CHK-06, CHK-07, CHK-08, CHK-09, CHK-10, CHK-11, CHK-12, BR-005, ENT-08`
- **#266** (7 ids): `PRE-01, PRE-02, PRE-03a, PRE-04, PRE-05, PRE-06, PRE-08`
- **#267** (1 ids): `PRE-07`
- **#268** (9 ids): `DOD-01, DOD-02, DOD-03, DOD-04, DOD-05, DOD-06, DOD-07, DOD-08, DOD-09`
- **#269** (9 ids): `NFR-01, NFR-02, NFR-04b, NFR-05, NFR-06, NFR-07, NFR-11, NFR-12, NFR-13`
- **#270** (1 ids): `NFR-10`
- **#271** (9 ids): `FIX-01, FIX-02, FIX-03, FIX-05, FIX-06, FIX-07, FIX-08, FIX-09, FIX-10`
- **#272** (15 ids): `VIS-01, VIS-02, VIS-03, VIS-04, VIS-05, VIS-06, OBJ-01, OBJ-02, OBJ-03, OBJ-04, OBJ-05, PLT-01, PLT-02, PLT-03, PLT-06`
- **#273** (15 ids): `ENT-01, ENT-02, ENT-03, ENT-05, ENT-06, ENT-07, ENT-09, ENT-10, ENT-11, ENT-13, ENT-14, ENT-15, ENT-16, ENT-17, ENT-19`
- **#274** (1 ids): `EVT-20`
- **#275** (5 ids): `ADM-130, ADM-131, ADM-132, ADM-133, ADM-134`
- **#276** (28 ids): `ADM-20, ADM-21, ADM-22, ADM-23, ADM-24, ADM-25, ADM-26, ADM-29, ADM-30, ADM-31, ADM-32, ADM-33, ADM-34, ADM-35, ADM-36, ADM-37, ADM-38, ADM-39, ADM-40, ADM-41, ADM-42, ADM-43, ADM-44, ADM-45, ADM-47, ADM-48, ADM-49, ADM-50`
- **#277** (7 ids): `ADM-55, ADM-56, ADM-57, ADM-59, ADM-60, ADM-61, ADM-63`
- **#278** (12 ids): `ADM-146, ADM-148, ADM-150, ADM-151, ADM-152, ADM-153, ADM-154, ADM-157, ADM-158, ADM-159, ADM-160, ADM-161`
- **#279** (3 ids): `ADM-100, ADM-103, ADM-104`
- **#280** (7 ids): `ADM-85, ADM-88, ADM-89, ADM-90, ADM-91, ADM-92, ADM-93`
- **#281** (17 ids): `PROMO-01, PROMO-02, PROMO-03, PROMO-04, PROMO-05, PROMO-06, PROMO-07, PROMO-08, PROMO-09, PROMO-11, PROMO-12, PROMO-13, PROMO-14, PROMO-15, PROMO-18, PROMO-19, PROMO-23`
- **#282** (7 ids): `MER-01, MER-02, MER-03, MER-04, MER-05, MER-06, MER-07`
- **#283** (16 ids): `PAY-01, PAY-02, PAY-03, PAY-04, PAY-05, PAY-06, PAY-07, PAY-08, PAY-09, PAY-13, PAY-14, PAY-15, PAY-16, PAY-17, PAY-19, INT-01`
- **#284** (4 ids): `INT-05, INT-09, INT-10, INT-12`
- **#285** (9 ids): `NOTF-01, NOTF-02, NOTF-03, NOTF-04, NOTF-06, NOTF-08, NOTF-11, NOTF-12, NOTF-13`
- **#286** (1 ids): `RPT-01`
- **#287** (8 ids): `MKT-12, MKT-13, MKT-14, MKT-15, MKT-16, MKT-17, MKT-18, MKT-20`
- **#288** (7 ids): `MKT-01, MKT-02, MKT-03, MKT-05, MKT-06, MKT-09, MKT-10`
- **#289** (4 ids): `ADM-110, ADM-111, ADM-113, ADM-115`
- **#290** (9 ids): `BR-001, BR-002, BR-003, BR-004, BR-006, BR-007, BR-008, BR-009, BR-010`
- **#291** (13 ids): `SHIP-01, SHIP-03, SHIP-04, SHIP-05, SHIP-08, SHIP-09, SHIP-12, SHIP-13, SHIP-14, SHIP-15, SHIP-16, SHIP-17, INT-02`
- **#292** (1 ids): `RET-08`
- **#293** (1 ids): `ADM-124`
- **#294** (12 ids): `ADM-01, ADM-02, ADM-03, ADM-04, ADM-06, ADM-08, ADM-09, ADM-10, ADM-11, ADM-12, ADM-13, ADM-15`
- **#295** (4 ids): `MIG-10, MIG-11, MIG-12, MIG-15`
- **#296** (2 ids): `MIG-16, MIG-17`
- **#297** (2 ids): `MIG-20, MIG-25`
- **#298** (6 ids): `MKT-21, MKT-22, MKT-23, MKT-24, MKT-25, MKT-26`
- **#299** (1 ids): `ERP-08`
- **#300** (1 ids): `ERP-04`
- **#301** (1 ids): `ERP-05`
- **#302** (11 ids): `HOME-01, HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-07, HOME-08, HOME-09, HOME-10, HOME-11`
- **#303** (21 ids): `PLP-01, PLP-02, PLP-03, PLP-04, PLP-05, PLP-06, PLP-07, PLP-08, PLP-09, PLP-10, PLP-12, PLP-13, PLP-14, PLP-15, PLP-16, PLP-17, PLP-18, PLP-19, PLP-20, PLP-21, PLP-22`
- **#304** (9 ids): `SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-05, SRCH-06, SRCH-06a, SRCH-07, SRCH-08`
- **#305** (16 ids): `AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09, AUTH-10, AUTH-11, AUTH-12, AUTH-13, AUTH-14, AUTH-15, AUTH-16`
- **#306** (18 ids): `CART-01, CART-02, CART-03, CART-04, CART-05, CART-06, CART-07, CART-08, CART-09, CART-10, CART-11, CART-12, CART-13, CART-14, CART-15, CART-16, CART-17, CART-18`
- **#307** (5 ids): `WISH-01, WISH-02, WISH-03, WISH-04, WISH-07`
- **#308** (9 ids): `ACCT-01, ACCT-02, ACCT-03, ACCT-04, ACCT-05, ACCT-06, ACCT-07, ACCT-08, ACCT-12`
- **#309** (5 ids): `REV-01, REV-02, REV-03, REV-04, REV-05`
- **#310** (10 ids): `CMS-01, CMS-02, CMS-03, CMS-04, CMS-05, CMS-06, CMS-07, CMS-08, CMS-09, CMS-10`
- **#311** (23 ids): `SSC-01, SSC-02, SSC-03, SSC-04, SSC-05, SSC-06, SSC-07, SSC-08, SSC-09, SSC-10, SSC-11, SSC-12, SSC-13, SSC-14, SSC-20, SSC-22, SSC-23, SSC-24, SSC-25, SSC-26, SSC-27, SSC-28, SSC-29`
- **#312** (17 ids): `EVT-01, EVT-02, EVT-03, EVT-04, EVT-05, EVT-06, EVT-07, EVT-08, EVT-09, EVT-10, EVT-11, EVT-12, EVT-13, EVT-14, EVT-15, EVT-16, EVT-18`
- **#313** (2 ids): `RPT-08, RPT-09`
- **#314** (1 ids): `ERP-03`
- **#315** (1 ids): `ERP-06`
- **#316** (1 ids): `ERP-07`
- **#317** (2 ids): `ADM-70, ADM-72`
- **#318** (6 ids): `ADM-71, ADM-73, ADM-74, ADM-75, ADM-77, ADM-149`
- **#319** (1 ids): `ENT-04`
- **#320** (1 ids): `MIG-14`
- **#321** (5 ids): `AC-21, AC-22, AC-23, AC-24, AC-25`
- **#322** (10 ids): `JRN-01, JRN-02, JRN-03, JRN-04, JRN-05, JRN-06, JRN-07, JRN-08, JRN-09, JRN-10`
- **#323** (20 ids): `AC-01, AC-02, AC-03, AC-04, AC-05, AC-06, AC-07, AC-08, AC-09, AC-10, AC-11, AC-12, AC-13, AC-14, AC-15, AC-16, AC-17, AC-18, AC-19, AC-20`
- **#324** (12 ids): `HND-01, HND-02, HND-03, HND-04, HND-05, HND-06, HND-07, HND-08, HND-09, HND-10, HND-11, HND-12`

**580 exact delivery ids across the 74 PBIs.**

### How Scope class and Stage are set

Both are **computed from the exact cited register ids** and from nothing else. Neither is inferred from the PBI's
purpose, and **Stage is never derived from Scope class**:

| Field | Rule |
|---|---|
| Scope class | One distinct delivery scope among the cited ids, that scope. More than one, `mixed` |
| Stage | One distinct register stage among the cited ids, that stage. More than one, `mixed`, and the issue then lists the literal stage per row |

`tools/tests/test_board_metadata.py` enforces both rules over this table in CI.

The reconciliation changed five Scope class values, because the stored value had been chosen from the PBI's
primary purpose rather than computed: **#243** DLV to mixed (PRE-09 is DLV, ERP-10 is P1), **#246** P1-L to mixed
(RPT-10 is P1-L, FIX-04 and NFR-04 are P1), **#247** P1-L to mixed (three MIG rows are P1-L, SSC-21 is P1),
**#253** mixed to P1 (all ten NAV rows are P1), **#255** mixed to P1 (CHK-01 to CHK-12, BR-005 and ENT-08 are all
P1).

**#243 is the only PBI whose Stage is `mixed`.** The register's stage column for PRE-09 is literally `-`, while
ERP-10 is S1. PRE-01 to PRE-08 are all S1; PRE-09 alone is unstaged, and nothing in this repository stages it.
The issue states the literal per-row stages and keeps the PBI's delivery scheduling separate from them.

### The board audit, and how to run it

Two checks, because they catch different things.

**1. In CI, on every push:** `tools/tests/test_board_metadata.py` validates the snapshot table above against
`docs/scope/register-ids.json`. Eleven cases: Scope class computed from the cited ids, Stage computed from the
register's own stage column, every cited id real and delivery scope, no S1 PBI citing an S2 row, `ERP blocked`
restricted to the dependency vocabulary and proved independent of Scope class, a contiguous delivery order, and
the Status vocabulary keeping Verified and Accepted apart. It was confirmed to bite by reintroducing the #243
`DLV` mislabel, which it rejected with `'DLV' != 'mixed'` and named the offending ids.

**2. By hand, whenever the live board changes:** regenerate this table from the Project and commit the result, so
the document keeps recording the board rather than drifting from it. The GitHub Project API is not reachable from
CI, so this step is deliberate rather than automated:

```bash
gh project item-list 1 --owner Mizzey-Platform --format json --limit 40
```

Read the Project, write the document. **Never the reverse**: the Project is the live operational state. If the two
disagree, the Project is right and the document is stale.

### ERP blocked means dependency, and only dependency

The option set was `no / partial / yes (P1-E)`. The `(P1-E)` conflated a contractual scope classification with a
dependency, and Scope class already records whether a cited row is P1-E. The options are now:

| Option | Meaning |
|---|---|
| `no` | Can be implemented and accepted without PRE-09 or any ERP decision |
| `partial` | Part of the PBI can proceed; some behaviour or final acceptance waits on PRE-09 or the ERP |
| `yes` | Cannot materially complete until the ERP or PRE-09 provides the required decisions |

**#243 is `yes`** even though its cited scopes are DLV and P1 rather than P1-E: the specification itself is what
the ERP meeting produces. That is the clearest demonstration that the field is a dependency and not a scope
restatement. **#245, #246, #254 and #255 are `partial`.** **#255 is `partial`, not `yes`:** its cited rows are all
P1 and its non-stock checkout behaviour can be built and accepted while stock validation waits.

No P1-E id was added to any PBI in order to justify a board field.

### Ownership boundaries, stated rather than assumed

**#255 does not own ERP-03 or ERP-05.** They belong to E-ERP-3 (ERP-05) and E-ERP-4 (ERP-03) in
`docs/2026-10-04-option-b-backlog-structure.md`, neither of which is seeded yet. #255 names them as dependencies
and boundaries, and does not cite them, so there is one owner each and no duplicate ownership.

### The one citation change this reconciliation made

**#242 dropped IA-23 and IA-33**, so the exact-id total across the fifteen PBIs went from **131 to 129**. Both are
delivery rows (P1-L), so both are contracted, but the register stages them **S2**. A Stage 1 PBI must not claim a
second-release row, so they are left to a future S2 PBI in the same epic and are named in #242 as deferred. No row
was added anywhere to make a board field easier to classify.

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
