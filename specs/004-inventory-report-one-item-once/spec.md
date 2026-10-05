# Feature Specification: The inventory report counts one physical item once, not once per language

**Feature Branch**: `004-inventory-report-one-item-once`

**Created**: 5 October 2026

**Status**: Implemented. Verification was reopened on 5 October 2026, when staging disproved AC-246-03 and
AC-246-04 in the signed-in browser session, and the feature was repaired the same day (C-5, C-6, FR-012, FR-013).
Where verification stands is in `verification.md`

**Work type**: requirement

**Input**: PBI #246, the third implementation feature of Option B. The standard low-stock and out-of-stock
reporting must describe physical items. Today it describes language records: one item that exists in English and
Arabic is either listed twice, or the list is cut down to one language and silently drops items that exist only in
the other. An operator restocks from this report, so both faults are purchasing errors.

## Register trace [checked]

One row, and only this row. FIX-04 and NFR-04, which make Arabic a full language and therefore make a duplicate
line a defect, are owned by #241 under the one-accepting-owner rule (D-09) and are context here.

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| RPT-10 | P1-L | S1 | Inventory: standard low-stock and out-of-stock reporting, on the figures your ERP supplies |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| FIX-04 | P1 | Arabic fully supported. Owned and accepted by #241. It is why one item has two records |
| NFR-04 | P1 | The same, as a non-functional row. Owned by #241 |
| ADM-159 | P1 | The admin interface is English only. The report is an admin screen, so it has no Arabic layout to verify |
| ADM-70, ADM-72 | P1-E | Stock on hand and stock available come from the ERP. Where those figures land in the store is PRE-09 |
| ADM-74 | P1-E | The low-stock threshold, per SKU or global, is staged "Per PRE-09". This feature does not define it |
| ADM-10, ADM-11 | P1 | The dashboard's low-stock and out-of-stock figures, "as reported by the ERP". Their own rows, with their own future owner. Not accepted here (D-246-1) |
| ADM-75 | P1-E | Low and out-of-stock alerts. A different capability from the report, and not built here |
| RPT-03 | DEF | Products, best sellers, slow movers, stock cover. Not built |
| ADM-76 | P2 | The full inventory transaction log. Not built |

## Open contract items

- **PRE-09**, the ERP Integration Specification, is not approved. RPT-10 says "on the figures your ERP supplies".
  Which store field carries those figures, and what defines "low", are ERP inputs. The criterion that depends on
  them is `pending PRE-09`. Everything else in this feature is a WooCommerce-side fault that exists whatever the
  ERP turns out to do (workstream items B10 and B11).
- No client decision is open on this feature. No working default from D-10 is used by it.

## Contractual acceptance criteria [checked]

"Physical item" means one sellable product or one sellable variation, however many language records describe it.
"The stock report" means the current standard stock report of the commerce platform, the Analytics Stock report
(D-246-1). The dashboard figures and the older stock report screen are not criteria of this feature.

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-246-01 | In the low-stock report, a physical item that exists in both languages is one line, not one line per language | RPT-10 | final |
| AC-246-02 | In the out-of-stock report, a physical item that exists in both languages is one line, not one line per language | RPT-10 | final |
| AC-246-03 | No physical item is left out because of the language of its record: an item that exists only in Arabic and an item that exists only in English each appear once | RPT-10 | final |
| AC-246-04 | The report lists the same physical items whichever language context the operator's admin session is in: English, Arabic or all languages | RPT-10 | final |
| AC-246-05 | The quantity on a line is the item's one stock figure. It is never the sum of its language records, and never differs between them | RPT-10 | final |
| AC-246-06 | The report's total, and its paging, count physical items: the total equals the number of lines across all pages | RPT-10 | final |
| AC-246-07 | The rule holds for a variation as it does for a simple product: a translated variation is one line | RPT-10 | final |
| AC-246-08 | The report's own export lists the same physical items as the screen | RPT-10 | final |
| AC-246-11 | The figures the report shows are the figures the ERP supplies | RPT-10 | pending PRE-09 |

AC-246-09 and AC-246-10 were drafted as `provisional` and are **removed by decision D-246-1**. The numbers are
not reused, so an earlier reference to either still means what it meant.

## Clarifications

### C-1. Which screens are "standard low-stock and out-of-stock reporting"? Decided: the stock report only

**Decision D-246-1, Mustafa, 5 October 2026.** RPT-10 is satisfied on the current standard stock report, the
Analytics Stock report, and on nothing else.

| Surface | Decision | Why |
|---|---|---|
| The stock report (Analytics, Stock) | **In.** The whole of this feature | It is the current standard report, with paging, sorting and export |
| The dashboard status counts | **Out of #246** | The signed register owns them separately: ADM-10 "Low stock, as reported by the ERP" and ADM-11 "Out of stock, as reported by the ERP", both P1, S1. They need their own accepting owner and are not absorbed into RPT-10 |
| The older stock report screen (Reports, Stock) | **Out of #246** | An obsolete screen being reachable does not create scope. Not fixed here |

**The two findings are kept as evidence, not lost.** Both are recorded in `research.md` with their measurements:
the dashboard counts double count a translated item in every language context, and are evidence and a
dependency for the future owner of ADM-10 and ADM-11; the older screen is language-scoped by the multilingual
plugin in the admin, and is a known admin compatibility finding for the Stage 1 ownership pass to place, either
under a contracted admin row or as a screen that is not exposed or recommended operationally.

### C-2. Which record stands for the item on its line?

The admin is English only (ADM-159), and English is the default language. So a line shows the item's
source-language record: for an item with both records, the original; for an item that exists in one language
only, that record. The line's link opens that record. No Arabic wording is added to an admin screen.

### C-3. What does "low" mean before PRE-09?

The report uses the platform's standard thresholds as they stand: the site-wide low-stock amount and the
per-product amount where one is set. This feature does not define, move or own the threshold. If PRE-09 places
the threshold in the ERP, the report reads whatever the store then holds; nothing here chooses that mechanism.

### C-4. An earlier measurement said the two language views were identical. Which is right?

P-020 (9 September) recorded that an internal request for the stock report returned the same list in English and
in Arabic, and left the scoped-view fault as "not ruled out" (B11). The audit of 5 October, on the current
baseline, measures the opposite: the list **is** scoped to the session language. Both are kept as dated
measurements. The cause of the difference is not established here and is a research task; the likeliest candidate
is that the baseline's language configuration changed when #241 landed. The feature does not depend on which is
true, because AC-246-03 and AC-246-04 hold in every context.

### C-5. What is "the operator's admin session", for AC-246-04? Added by the repair of 5 October 2026

It is the session as the operator has it: signed in, in a browser, on the Analytics Stock screen, with the admin
language switcher set to English, Arabic or all languages. The screen reads the report through a REST request,
and in the Arabic context that request goes to an address under the Arabic prefix. The criteria are therefore
verified through that request, over HTTP, and not only by calling the report inside one process. The first
verification did the second and was wrong about the first (`research.md` R-11). The same holds for the export: a
long export is built by a scheduled job, which can run in a request that is neither wp-admin nor WP-CLI.

### C-6. Is the summary under the table part of the report? Added by the repair of 5 October 2026

Yes. The screen shows, under the table, how many products, and how many low, out of stock, on backorder and in
stock. It is the same standard report, on the same screen, and AC-246-06 says the report's total counts physical
items. A summary reading "7 Low stock" beside a list of four lines is that criterion failing as a reader meets
it. Each figure of the summary is therefore the total of the list it summarises. This is not the dashboard: the
dashboard's figures stay with ADM-10 and ADM-11 (D-246-1), and nothing here changes them.

## User Scenarios and Testing *(mandatory)*

### User Story 1 - A buyer restocks from a list of real items (Priority: High)

The person who orders stock opens the low-stock report and sees each item that is running low exactly once, with
its one quantity, whichever language the item's content was written in.

**Why this priority**: it is the row. A list that doubles an item or hides one is not a basis for purchasing.

**Independent Test**: seed one item in both languages, one in Arabic only and one in English only, all low. The
report shows three lines.

**Acceptance Scenarios**:

1. **Given** a low item with an English and an Arabic record, **When** the low-stock report is opened, **Then** it
   appears once, with one quantity.
2. **Given** a low item that exists only in Arabic, **When** the report is opened in an English admin session,
   **Then** it appears.
3. **Given** the same catalogue, **When** the report is opened in an English, an Arabic and an all-languages
   session, **Then** the three lists name the same physical items.
4. **Given** an item that is out of stock in both of its records, **When** the out-of-stock report is opened,
   **Then** it appears once.

### User Story 2 - A variation is an item too (Priority: High)

A size or colour that is running low appears once, although its parent and the variation itself both have an
Arabic record.

**Independent Test**: a variable product with one low variation, translated. The report shows one line for it.

**Acceptance Scenarios**:

1. **Given** a translated variable product with one low variation, **When** the low-stock report is opened,
   **Then** that variation is one line.

### Edge Cases

- The source-language record is in the bin, or deleted, and the translation remains: the item is still one line,
  represented by the record that remains (workstream item D8).
- The two records of one item disagree on stock status because a write reached only one of them: the report must
  not show the item in two lists at once. Which figure wins is the item's source-language record, and the
  disagreement itself is a data-integrity finding, recorded and not repaired here.
- A translation group that has been corrupted so that an Arabic record points at the wrong original (A11): the
  report follows the translation relationship as stored. Repairing the relationship is #247.
- A product whose stock is not managed: it follows the platform's standard rule for the out-of-stock list and is
  still one line per physical item.
- More physical items than one page holds: the total and the last page are counted in physical items.
- Sorting by quantity, by status or by SKU keeps one line per item.

## Native coverage

Measured on the disposable runtime on 5 October 2026: WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual
5.5.7. Fixtures: one low item in both languages, one low item in Arabic only, one low item in English only, one
out-of-stock item in both languages. Three low physical items and one out-of-stock physical item are expected.

| Capability | Evidence | Status |
|---|---|---|
| A low-stock and an out-of-stock list exist, with paging, sorting and export | `Reports\Stock\Controller`, the `wc-analytics/reports/stock` endpoint | VERIFIED, native |
| The list in an English session | Low: 2 lines, the Arabic-only item missing. Out: 1 line | **GAP**: an item is omitted (B11, now measured) |
| The list in an Arabic session | Low: 2 lines, the English-only item missing. Out: 1 line | **GAP**: an item is omitted |
| The list in an all-languages session | Low: 4 lines for 3 items. Out: 2 lines for 1 item. The reported total is 4 and 2 | **GAP**: double count (B10, P-020) |
| One stock figure per item | Each language record holds its own `wc_product_meta_lookup` row with the full quantity | **GAP** at the root: two stockable records describe one item (P-020) |
| The dashboard counts | 4 low and 2 out for the same fixtures. The multilingual plugin hooks `woocommerce_status_widget_low_in_stock_count_query`, a filter WooCommerce 11.1.0 no longer applies | Measured defect, **not this feature**: evidence for the owner of ADM-10 and ADM-11 |
| The older stock report screen | Its query is scoped to the session language by the multilingual plugin's `filter_reports_stock_query`, which runs in the admin only | Finding by source reading, **not this feature** |
| The low-stock thresholds | The site-wide amount and the per-product amount, both native | VERIFIED, native, used as they stand |

The correction therefore needs custom code, and the gap is recorded as M-4 requires: no setting of WooCommerce,
WPML or WooCommerce Multilingual makes the stock report list one line per physical item across languages.

## Requirements

### Functional Requirements

- **FR-001**: The stock report MUST resolve every product and variation to its physical item before the list is
  built, so that one item yields one line whatever number of language records it has.
- **FR-002**: The stock report MUST include an item whose only record is in a language other than the session's.
- **FR-003**: The stock report MUST return the same set of physical items in an English, an Arabic and an
  all-languages admin session.
- **FR-004**: A line MUST be represented by the item's source-language record, or by its only record where it has
  one, and MUST show that record's stock figure.
- **FR-005**: The report's total and paging MUST count physical items.
- **FR-006**: Sorting and the report's own export MUST operate on the same resolved list.
- **FR-007** and **FR-008** were drafted for the dashboard counts and the older stock report screen and are
  **removed by D-246-1**. The numbers are not reused.
- **FR-009**: The resolution of a record to its physical item MUST be one shared piece of the reporting layer,
  not written once per report: the decision of 9 September records that a second report needs the same thing.
- **FR-010**: The feature MUST NOT change stock, how stock is synchronised between language records, or any
  storefront behaviour. It changes what a report lists.
- **FR-011**: The feature MUST NOT choose where the ERP's figures are stored or what defines "low" (M-3).
- **FR-012**: The resolution MUST hold in every kind of request the report is read in: the screen's REST request
  under either language's address, a scheduled export, wp-admin and WP-CLI. It MUST NOT depend on a setting the
  multilingual plugin honours in some of those and not in others. Added by the repair of 5 October 2026.
- **FR-013**: Each figure of the summary under the report's table MUST equal the total of the list it
  summarises, in every admin language context. Added by the repair of 5 October 2026.

### Key Entities

- **Physical item**: one sellable product or variation. It has one or more language records and, under the D-10
  invariant, one stock identity.
- **Language record**: the product or variation post in one language. Each carries its own stock lookup row.
- **Translation group**: the stored relationship that says which language records are the same physical item.

## Optional safeguards (not owed)

| Id | Safeguard | Why | Custom code? |
|---|---|---|---|
| S-1 | A fact-finding scenario that reports any physical item whose language records disagree on quantity or status | The report can only be as right as the records under it, and A12 showed a code-level save can leave them apart | Test only, no behaviour |

## Future or Option C items (not built)

| Item | Where it sits |
|---|---|
| Dead stock, stock adjustments, stock cover in the inventory report | Not in the wording of RPT-10 as contracted for Option B |
| Products, best sellers, slow movers | RPT-03, DEF |
| The inventory transaction log | ADM-76, P2 |
| Profitability and margin | RPT-02, P2 |
| The product analytics report splitting one item by language (P-019) | The same root, a different report, owned with RPT-03 and not built here |
| Low and out-of-stock alerts | ADM-75, P1-E, behind PRE-09 |
| The dashboard's low-stock and out-of-stock figures | ADM-10 and ADM-11, P1, S1: contracted, owned by the dashboard PBI, with this feature's measurement as its evidence |
| The older stock report screen | No row. A compatibility finding for the Stage 1 ownership pass |

## Decisions this feature needs

| Id | Decision | Whose | Default if none is given |
|---|---|---|---|
| D-246-1 | C-1: whether the dashboard counts and the older stock report screen are inside RPT-10 | Mustafa | **Decided, 5 October 2026: neither is.** Only the current standard stock report is built and accepted here |

No client decision is needed to build or to verify this feature.

## Success Criteria

- **SC-001**: With the audit's fixtures, the low-stock report shows 3 lines and a total of 3, and the
  out-of-stock report shows 1 line and a total of 1, in each of the three language contexts.
- **SC-002**: No fixture item is missing from, or repeated in, any of the six lists.
- **SC-003**: A translated low variation is one line.
- **SC-004**: The existing integration suite still passes: the feature changes no stock and no synchronisation.
- **SC-005**: Read over HTTP as a signed-in administrator, in the English, Arabic and all-languages admin
  contexts, the lists, their totals and the summary under the table are identical, and hold each fixture item
  once. Added by the repair of 5 October 2026.

## Delivery stage

RPT-10 is contracted **S1**, and that does not move. Engineering work happens now, in delivery order 5.
**Technically verified** will mean the criteria above pass on the disposable runtime. **Contractually accepted**
needs manual testing on staging (DOD-04), PRE-09 for AC-246-11, and client approval (DOD-09) through
MS-UAT-2026-027.

## Assumptions

- The translation relationship stored by the multilingual plugin is the authority for "the same physical item".
  D-10 fixes that one commercial item is one stock identity regardless of language.
- The source-language record is the English one for an item created in English, and the report follows the
  stored relationship rather than assuming English.
- The report is an admin screen and is verified in English only (ADM-159).
- Stock keeps being copied to every language record by the multilingual plugin, as B1 measured. This feature
  reads that state and does not alter it.
