# Feature Specification: stock in the admin

**Feature Branch**: `026-admin-stock`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: the owner, 31 October 2026, on the product's Inventory tab: "I shouldn't enter stock by
hand ... it should tell me this product's stock is so much; I don't change anything. I shouldn't be able to edit or
add stock." D-19 item 5 (23 October 2026): "Only the ERP is authoritative for stock. No independent local stock writer
may alter the effective available stock. Any permitted ERP-derived projection write must be controlled through the
designated adapter", every local path refused on the server, not hidden. Built now as the function layer, as D-10
allows when a fixture can stand in for the ERP; how WooCommerce's fields carry the ERP figure waits for PRE-09 (#243).
The admin side of #316 and #317.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ERP-07 | P1-E | S1 | How stock appears in, and is changed through, the store admin (Part One 1.7) |
| ADM-70 | P1-E | S1 | Stock on hand, from your ERP |
| ADM-72 | P1-E | S1 | Stock available, from your ERP |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ERP-01, ERP-03 to ERP-06 | P1-E | The adapter (specs/012), the cart (specs/018) and the checkout (specs/019) already ask the stock source; this closes the admin's local writers |
| MIG-14 | P1-E | Initial stock quantities, where PRE-09 settles: written through the adapter's projection |

## Open contract items

- PRE-09 (the ERP Integration Specification) decides how the ERP figure reaches WooCommerce's fields and whether the
  admin ever changes stock through the ERP (ERP-07's "changed through"). Until then the admin changes none.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Stock is not changed in the store admin or any other local writer; only the ERP's projection writes it | ERP-07 | pending PRE-09 |
| AC-2 | The admin shows each product's stock as the ERP reports it | ADM-70, ADM-72 | pending PRE-09 |

Both are built and tested against a stand-in stock source; acceptance waits for the ERP mechanism (PRE-09), then
staging and the client's approval.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Stock I can read, not type (Priority: High)

1. **Given** a product, **When** staff open it, its variations, or the products list, **Then** they see its stock as
   the stock source reports it, and the stock fields are locked.
2. **Given** any attempt to change stock, from the editor, quick or bulk edit, the importer, the REST API, WP-CLI or a
   plugin, **When** it is saved, **Then** the stored stock is unchanged, and the REST API says so with a 403.

### Edge Cases

- A store with no stock source shows the figure as not known.
- The rest of an edit (a name, a price) is kept; only the stock is put back.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Stock written only by the ERP | None: WooCommerce lets every editor and API write stock | Gap: `StockWriteGuard` |
| The ERP's figure in the admin | WooCommerce shows its own stock field | Gap: `StockWriteGuard` shows the adapter's figure |

## Requirements

### Functional Requirements

- **FR-001**: Every product and variation save MUST keep its stored stock quantity, stock status, stock management
  and backorders unless the adapter's projection writes them (AC-1).
- **FR-002**: Every other local writer MUST be refused a change to a stored stock key outside the projection: WooCommerce's direct stock
  update, WordPress's metadata API (by key, by meta id, for every object) and a raw query on the post meta table,
  as WooCommerce Multilingual's synchronisation makes. Writes to other keys MUST go through unchanged (AC-1).
- **FR-003**: The REST API MUST refuse a stock change with a 403 (AC-1).
- **FR-004**: The products list, the product editor and the variation editor MUST show the stock source's figure, or
  that it is not known, with the stock fields shown locked (AC-2).
- **FR-005**: Test fixtures and staging seeds MAY write stock as the ERP's stand-in, through a filter the site's own
  code never sets.
- **FR-006**: Every message MUST be translated (FIX-04).

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Stock changed through the admin into the ERP, if PRE-09 asks for it.

## Success Criteria

- **SC-001**: No stock figure in the store is ever typed by hand.

## Delivery stage

Contractual stage: S1. Engineering timing: 31 October 2026, the function layer ahead of PRE-09.

## Assumptions

- A direct database write outside WooCommerce cannot be refused by any PHP hook; it is recorded, not built.
