# Feature Specification: Product cost captured from day one

**Feature Branch**: `001-product-cost-capture`

**Created**: 2026-09-22

**Status**: Draft

**Work type**: requirement

**Input**: User description: "ADM-27 / RPT-11 Product cost captured from day one, with MIG-13 as the supporting migration requirement. Contractual stage S1, unchanged. Native WooCommerce Cost of Goods Sold unless a test proves a gap. Based on the approved v2 pilot draft."

<!--
  MIZZEY RULES FOR THIS TEMPLATE (constitution M-1 to M-6). tools/scope_trace.py checks the sections marked [checked].
-->

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-27 | P1 | S1 | Product cost: captured from day one so margin reporting can later be built on accurate history |
| RPT-11 | P1 | S1 | Product cost captured from day one |
| MIG-13 | P1-L | S1 | Price, sale price and cost are migrated where present in the agreed source |

ADM-27 and RPT-11 are key requirements. Stories: Functional Specification MS-SPC-2026-032 v1.2, US-16-05 (product cost
captured from day one) and US-16-02 (product cost recordable).

## Context rows (no obligation)

| ID | Scope | Why it matters here |
|---|---|---|
| ROLE-06 | DEF | Accountant permission profile. Lists ADM-27 and RPT-11, but is deferred. See CX-01 |
| ROLE-06a | P2 | Accountant advanced financial reporting. Not delivered |
| ROLE-09 | P1-L | The standard commerce role set applies at launch. Relevant to who counts as having financial permission |
| RPT-02 | P2 | Profitability, COGS, gross margin and product margin reporting. Not delivered |
| ADM-159 | P1 | Admin interface language: English only. Owed by its own row, not by this feature. It means there is no Arabic admin UI to test here |
| ADM-159a | P2 | Arabic administrative interface. Not delivered |
| MIG-05, MIG-07, MIG-18 | DEF | Reusable import tooling. Not built here |

## Open contract items

- **CX-01 (pending)**: the register's narrative says the Accountant role exists at launch; its ROLE-06 row says DEF.
  Which staff roles hold "financial permission" (US-16-05 AC2) is therefore unresolved. This spec takes no position.
- **OD-12 (pending)**: product cost basis (purchase price only, or landed cost) and who enters it. Client decision,
  due before catalogue load. It decides what the value means, not whether it can be captured. Add to that
  conversation: WooCommerce stores a cost of zero as "no cost", so a deliberate zero cost (a free sample, a gift)
  cannot be told apart from a product nobody has costed yet. If the client needs that distinction, it is a change
  request, not a build task.
- **WPML-1 (closed)**: cost changed by code did not reach translations. Root cause found, fix implemented and
  verified (see Native coverage and verification.md). No longer open.
- **WPML-2 (closed)**: `mizzey-site/wpml-config.xml` was shown to be unnecessary on a clean runtime and was removed.
- **PRE-09**: not relevant. No row here is P1-E; the ERP integration is stock only.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A cost value can be recorded on every product and on every variation of a variable product, and reads back unchanged | ADM-27, RPT-11 | final |
| AC-2 | The cost that applied when an order was placed stays on that order, unchanged by later edits to the product's cost | ADM-27, RPT-11 | final |
| AC-3 | Orders placed in either storefront language, English or Arabic, carry the cost of the product sold | ADM-27, RPT-11 | final |
| AC-4 | Cost is populated during catalogue load and imported where the agreed source file carries it | ADM-27, MIG-13 | final |
| AC-5 | Cost is never visible to visitors or customers, on any storefront page or public or customer-accessible interface | ADM-27 | final |
| AC-6 | Cost is visible only to staff roles with financial permission | ADM-27 | pending CX-01 |
| AC-7 | The meaning of the cost value (basis) and who enters it follow the client's answer to OD-12 | ADM-27, RPT-11 | pending OD-12 |

AC-5 and AC-6 are the customer half and the staff half of US-16-05 AC2. They are separated so the unblocked half can
be verified now. Margin and profitability reporting (US-16-05 AC4) is P2 and is not delivered here.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Store owner records cost on products and variations (Priority: High)

The store owner enters a cost for each product, and for each variation of a variable product, in the (English)
admin, so that margin can later be calculated from real history.

**Why this priority**: ADM-27 and RPT-11 are key Stage 1 rows, and cost that is not captured from the first day is
lost.

**Independent Test**: Record a cost on a simple product and on two variations of a variable product, save, reload,
and read each value back.

**Acceptance Scenarios**:

1. **Given** cost capture is enabled, **When** the owner sets a cost on a simple product and saves, **Then** the same
   value reads back after reload (AC-1)
2. **Given** a variable product with two variations, **When** the owner sets a different cost on each, **Then** each
   variation keeps its own value (AC-1)

---

### User Story 2 - Historical orders keep the cost that applied on the day (Priority: High)

The store owner can rely on past orders keeping the cost that applied when they were placed.

**Why this priority**: ADM-27 exists so that margin can be built on accurate history. A cost rewritten by a later
edit destroys that history.

**Independent Test**: Place an order, change the product's cost, and re-read the order.

**Acceptance Scenarios**:

1. **Given** a product with cost 100, **When** an order for 2 units is placed and the product cost is then changed to
   999, **Then** the order still records a cost of 200 (AC-2)
2. **Given** the storefront in Arabic, **When** a customer places an order for the Arabic version of a product,
   **Then** the order records that product's cost (AC-3)

---

### User Story 3 - Cost arrives with the initial catalogue (Priority: Medium)

The Developer loads the agreed catalogue source file once, and any cost it carries lands on the matching products
and variations.

**Why this priority**: MIG-13 is P1-L and one-off. It depends on the catalogue source, which is not yet received.

**Independent Test**: Import a fixture catalogue file with a cost column and with a row that has no cost.

**Acceptance Scenarios**:

1. **Given** a source file with a cost column, **When** it is imported, **Then** each product and variation has the
   cost from its row (AC-4)
2. **Given** a source row with no cost, **When** it is imported, **Then** the product is created without a cost, and
   is not given a cost of zero (AC-4)

---

### User Story 4 - Customers never see cost (Priority: High)

Visitors and customers must never be able to read product cost.

**Why this priority**: Exposing cost to customers is a commercial and confidentiality failure.

**Independent Test**: Request products as a visitor and as a logged-in customer, through storefront pages and every
public or customer-accessible interface, and look for cost.

**Acceptance Scenarios**:

1. **Given** a product with a cost, **When** a visitor or customer views it or requests it through any public or
   customer-accessible interface, **Then** no cost value appears (AC-5)
2. **Given** staff accounts in each standard role, **When** they open a product, **Then** the roles that can see cost
   are recorded as a fact for the CX-01 discussion, with no pass or fail verdict (AC-6, pending)

---

### Edge Cases

- An order is placed while cost capture is disabled: that order carries no cost, and enabling capture later does not
  add one. The go-live check (safeguard S-1) exists for this reason.
- A product whose Arabic and English versions differ in cost, if translation management allows it: record what
  happens.
- A cost of zero is stored by WooCommerce as "no cost", the same as a blank (native `adjust_cogs_value_before_set`,
  verified in t02). No register row requires zero to differ from blank. If the client needs zero-cost items (for
  example free samples) told apart from uncosted ones, that is a question for the OD-12 discussion, not a build task.
- While cost capture is off, setting a cost does nothing (`set_cogs_value` checks the flag). Edits or imports made
  before enablement lose their cost silently. Safeguard S-1 addresses this.
- A variation with no cost of its own: record whether it falls back to anything or stays empty.
- Saving a translation makes WooCommerce write its own bookkeeping fields (product version, rating and review
  counters) and regenerate a variation's `post_title` from the parent name and attributes. Measured in t14: it
  happens on any save, including WCML's own, and changes nothing an Arabic customer reads.

## Native coverage

Verified on clean, scripted baselines of the disposable runtime (WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7,
WCML 5.5.7, PHP 8.3.6, MySQL 8.3.0). Evidence: `evidence/` and `verification.md`.

| Capability | Evidence | Status |
|---|---|---|
| Cost on simple products and variations, saved and read back | t02, t03 | VERIFIED |
| Cost frozen onto the order line; later edits do not change it | t04 (200 kept after 999) | VERIFIED |
| Cost carried to a translation created after the cost exists, by WPML duplicate or by the WCML translation editor, for simple products and variations | t11 part 1 | VERIFIED |
| Cost changed later through wp-admin (product form, variations AJAX) or the wp-admin CSV importer, reaching the Arabic copy | t11, all admin-http and import-http cases | VERIFIED (native) |
| Cost changed later through the REST API, WP-CLI, or code in a front-end request | t11, rest-http, crud-cli, crud-web | **GAP in WooCommerce and WPML** (see below). Closed by `MizzeySite\Catalogue\CostTranslationSync` |
| The hook that carries the copy in each channel, recorded inside the request that does the work | t15, ten cases | VERIFIED |
| The copy reaches the right post and only that post: the WPML original, the matching variation, never a sibling or an unrelated product, never back onto the original | t12 | VERIFIED |
| First cost, increase, decrease, no change, repeated saves, clearing, zero; and the number of writes each causes | t13 | VERIFIED |
| A translation that cannot be saved: other products still copied, the gap logged, repaired by the next save of the original | t13 | VERIFIED |
| The copy changes no authored field, no translation relationship, no unrelated custom field, and nothing an Arabic customer reads | t14, both translation methods, simple and variation | VERIFIED |
| Native CSV import carries "Cost of goods"; a blank stays blank | t07 | VERIFIED |
| No cost to visitors or customers: product pages, Store API, REST v3, My Account order view | t08 | VERIFIED (order emails not tested) |
| Which staff roles see cost | t09: administrator and shop_manager only; every other role 403 | FACT for CX-01 |
| A zero cost is stored as "no cost", like a blank | t02, native `adjust_cogs_value_before_set` | FACT, raised under OD-12 |
| Setting a cost while the feature is off does nothing, and the importer drops the column | source: `set_cogs_value`, importer line 989 | VERIFIED (source) |

**The gap, and why custom code was needed.** WooCommerce's data store skips `wp_update_post()` when a save changes
only meta, so `save_post` never fires for a cost-only change. WPML and WCML copy fields to translations on
`save_post` (and, in wp-admin, on the variations AJAX save and WCML's importer hook, which runs
`wpml_sync_all_custom_fields`). A cost changed through REST, WP-CLI or other code therefore never reached the
Arabic copy, and an order for the Arabic product recorded cost 0 with nothing on screen to show it. Tested with and
without a `wpml-config.xml` declaring the cost fields as copied: the file changed WPML's settings and changed no
outcome, so it was removed (WPML-2). WPML's own downloaded configuration already declares the real cost field,
`_cogs_total_value`, as copied and locked, which confirms that the missing piece was never the declaration.

`MizzeySite\Catalogue\CostTranslationSync` closes it: after WooCommerce saves a product or variation, the
source-language original copies its cost to its translations through WooCommerce CRUD, comparing first and writing
only on a difference. It adds no field and syncs nothing but cost.

Side effect recorded, not caused by this feature: saving a variation makes WooCommerce regenerate that variation's
title from the parent name and attributes. An Arabic variation authored in the WCML editor is therefore renamed the
first time any save reaches it, including WCML's own admin sync. Price, stock, SKU and status are untouched (t11).

## Requirements

### Functional Requirements

- **FR-001**: Cost capture MUST be enabled on every environment before any live order is placed (AC-2)
- **FR-002**: The system MUST store a cost per simple product and per variation (AC-1)
- **FR-003**: The system MUST keep, on each order, the cost that applied at the time of sale (AC-2)
- **FR-004**: The system MUST carry cost onto orders placed in either storefront language (AC-3)
- **FR-005**: The initial catalogue import MUST carry cost where the source provides it, and MUST NOT turn a blank
  cost into zero (AC-4)
- **FR-006**: Cost MUST NOT be exposed to visitors or customers through any page or interface (AC-5)
- **FR-007**: Which staff roles may see cost follows the resolution of CX-01. Until then, native behaviour is recorded
  and no restriction is built (AC-6)

### Key Entities

- **Product cost**: a monetary value per simple product or variation, in EGP. Its basis (purchase or landed) is set
  by OD-12.
- **Order line cost**: the cost recorded on an order line at the time of sale. It does not change afterwards.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | Go-live check that cost capture is enabled: a runbook line, plus a scripted assertion in the deploy step | An order placed while it is off loses its cost permanently | Runbook: no. Assertion: a few lines of deploy script, no plugin code |
| S-2 | Record the enablement date and WooCommerce version in `DECISIONS.md` | Shows which orders can carry cost | No |
| S-3 | A scripted clean baseline for the disposable runtime (`mizzey-site/tests/integration/baseline/`) | The first WPML results were wrong because the runtime had drifted. A scripted baseline makes a result repeatable | No (test tooling) |

Rejected as unnecessary: a custom cost field, a catalogue service, a cost repository, an admin notice, or a CLI
command. No contractual criterion needs any of them.

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Profitability, COGS and margin reporting | RPT-02, P2 |
| Accountant role profile and advanced financial reporting | ROLE-06 DEF, ROLE-06a P2 |
| Cost-basis label stored beside the value | Option C era proposal (9 Sep decision doc). Revisit only after OD-12 |
| Landed cost, supplier and purchase orders | ENT-18, P2 |
| Reusable import tooling | MIG-05, MIG-07, MIG-18, DEF |
| Arabic admin interface | ADM-159a, P2 |

## Success Criteria

- **SC-001**: 100% of products and variations in the test catalogue hold the cost entered or imported for them.
- **SC-002**: An order keeps its recorded cost after the product's cost is changed, in 100% of test orders.
- **SC-003**: Orders placed in English and in Arabic both record cost.
- **SC-004**: No cost value appears in any visitor or customer response in the test run.
- **SC-005**: No custom code is added unless a failing contract test requires it, and each addition names that test.

## Delivery stage

Contractual stage: S1 for ADM-27, RPT-11 and MIG-13. Engineering timing: this is the first engineering pilot, run
during the governance phase. That timing does not change the contractual stage. Acceptance happens at the Stage 1
gate under the Acceptance and UAT Plan MS-UAT-2026-027.

## Assumptions

- WooCommerce 11.1.0, WPML 4.9.7 and WCML 5.5.7, as recorded in `stack.lock.json`. Results are evidence for those
  versions only.
- Currency is EGP only. No multi-currency cost.
- The catalogue source file is not yet received. The import is tested with a fixture file in the native format.
- Verification runs in the disposable local runtime (`../app/wp`), never in production.
- Three states are reported separately: workflow complete, technically verified, contractually accepted. This
  feature is not contractually accepted while AC-6 and AC-7 are pending.
