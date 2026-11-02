# Feature Specification: everyday tasks in the standard administration

**Feature Branch**: `037-everyday-admin`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ADM-4, issue #278 ("The client's team performs the Section G10 daily tasks in the English standard
administration without a developer"). Register G10 says these rows are met in the standard commerce administration;
the dedicated Operations Console is DEF. The store operations walkthrough (#267) confirms the list with the client
later. The owner's instruction of 2 November 2026 puts it in the admin set that needs no client input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-146 | P1 | S1 | Add a simple product (title, price, stock, image, category) in under three minutes. The stock figure follows ERP-01 |
| ADM-148 | P1-L | S1 | Bulk price change by amount or percentage across a selection, using the standard commerce bulk-edit capability |
| ADM-150 | P1-L | S1 | Bulk publish, unpublish or archive, using the standard commerce bulk-edit capability |
| ADM-151 | P1-L | S1 | Bulk category assignment, using the standard commerce bulk-edit capability |
| ADM-152 | P1 | S1 | Find any product within seconds by SKU, title or barcode |
| ADM-153 | P1 | S1 | Duplicate an existing product as a starting point |
| ADM-154 | P1-L | S1 | Bulk order status change, using the standard commerce bulk-action capability |
| ADM-157 | P1 | S1 | Order search by customer phone number |
| ADM-158 | P1-L | S1 | Order export to spreadsheet using the standard supported commerce export |
| ADM-159 | P1 | S1 | Administrative interface in English, presented consistently across the standard store screens and the screens built in this engagement |
| ADM-160 | P1-L | S1 | Admin usable on a mobile device for order status checks and stock updates, the stock part per PRE-09 |
| ADM-161 | P1 | S1 | Product import and export: see Section U |

ADM-157 is marked [key] in the register.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-147, ADM-155, ADM-156 | DEF | Inline edit grid, bulk dispatch, bulk packing slips: not built |
| ADM-149 | P1-E | Bulk stock change, per PRE-09 |
| ADM-159a | P2 | An Arabic administration: not built |
| ADM-20, ADM-85 | P1 | Duplicating and the order search are also #276's and #280's (specs/036, specs/030) |
| MIG rows | P1-L | The catalogue migration (Section U) is #247 |

## Open contract items

- **#267.** The client confirms the G10 list at the store operations walkthrough.
- **PRE-09.** The stock parts of ADM-146 and ADM-160 follow the ERP specification.
- **A5 (data integrity).** Whether a bulk price change from the products list reaches the Arabic record was never
  measured (t20 measured the product form, the variation save, the importer and the sale cron); it is measured here.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A bulk price change by percentage or by amount across a selection changes both language records | ADM-148 | provisional |
| AC-2 | A selection is published, unpublished or archived, and given a category, in one bulk action | ADM-150, ADM-151 | provisional |
| AC-3 | A product is found by SKU, title or barcode, and an order by the customer's phone number | ADM-152, ADM-157 | provisional |
| AC-4 | Orders change status in bulk through the status model; orders are exported to a spreadsheet | ADM-154, ADM-158 | provisional |
| AC-5 | The administration is in English for every staff member | ADM-159 | provisional |
| AC-6 | A simple product is added and an existing one duplicated from the standard screens; import and export are Section U's | ADM-146, ADM-153, ADM-161 | provisional |
| AC-7 | The administration is usable on a phone for order status checks; stock updates follow the ERP Integration Specification | ADM-160 | pending PRE-09 |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A price rise across a range (Priority: High)

1. **Given** a range of products in both languages, **When** staff raise their prices by 10% from the products list,
   **Then** the English and Arabic records show the new prices.

### Edge Cases

- A staff member who chose Arabic as their own language: the administration stays in English (ADM-159a is P2).
- A bulk status change that skips a step of the status model: refused by the model (specs/010).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Bulk price, status, category | WooCommerce's bulk edit in the products list | Native, measured in both languages |
| Search by SKU, title, barcode | WooCommerce's product search | Native, verified |
| Search orders by phone | WooCommerce's order search | Native, verified |
| Bulk order status | WooCommerce's bulk actions, with the status model (specs/010) | Native plus specs/010 |
| Order export | WooCommerce Analytics' download | Native |
| Duplicate, add a product | WooCommerce | Native |
| English administration | WordPress's user language | Gap if a staff member picks Arabic: checked |
| Phone use | WordPress's responsive administration | Native |

## Requirements

### Functional Requirements

- **FR-001**: Each G10 task MUST work in the standard administration, checked through the request the screen makes.
- **FR-002**: A bulk price change MUST reach the Arabic record; if it does not, the gap MUST be fixed at its cause.
- **FR-003**: The administration MUST stay in English for staff (ADM-159).
- **FR-004**: Searching the orders list by a mobile number MUST find the order however the customer typed it and
  however staff type it (`+20`, spaces, Arabic digits): orders keep the number in the `01` form, and the search reads
  the number in that form (ADM-157).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The Operations Console, the inline edit grid, bulk dispatch and bulk packing slips (DEF).

## Success Criteria

- **SC-001**: On staging, a simple product is added in under three minutes and each bulk action is run over a
  selection, with both language records checked afterwards.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- "Archive" is the Private status, restorable.
