# Feature Specification: the stock source switch

**Feature Branch**: `049-stock-source-switch`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: D-28 (the owner, 2 November 2026): the store runs on the ERP's stock, on its own stock, or mixed, product by
product, with a master switch to take the ERP out of the picture when it cannot be relied on, and a route an outside
system (the ERP) can call to feed the store's own stock. Built on the adapter seam (specs/012), so the cart
(specs/018), the checkout and order reservation (specs/019) and the admin (specs/026) work unchanged on either source.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| INT-16 | P1 | S1 | Every integration behind an adapter with retries, logging and webhook handling |
| ERP-07 | P1-E | S1 | How stock appears in, and is changed through, the store admin (Part One 1.7) |
| ADM-73 | P1-E | S1 | Stock adjustments with reason and user recorded |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ERP-01 to ERP-06 | P1-E | Unchanged: every read and change still goes through `StockGateway`; on the ERP source they still wait for PRE-09 |
| OD-41 | Decided | When the ERP cannot be reached the sale is not confirmed: the store never fails over to its own stock by itself |

## Open contract items

- PRE-09 still decides the ERP mechanism (M-3). The route in User Story 3 is offered, not chosen: it serves the store's
  own stock and refuses items on the ERP source.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | Stock reads and changes go through one adapter, with retries and logging, whichever source holds the item | INT-16 | final |
| AC-2 | On the store's own stock, staff change stock from the admin with a reason, and the change, its reason and its user are recorded | ERP-07, ADM-73 | pending PRE-09 |

## Developer safeguards (approved by the owner, D-28; not presented as contracted deliverables)

| # | Safeguard |
|---|---|
| S-1 | A store-wide default source (ERP or the store's own stock) and a per-product or per-variation choice (default, ERP, own stock) |
| S-2 | A master switch, "ERP off", that sends every item to the store's own stock and, turned back on, restores each item's choice |
| S-3 | The store's own stock applies each change once per operation key, never goes below zero for a sale, and records every change |
| S-4 | An authenticated REST route that sets or changes the store's own stock by SKU, singly or in batches, once per key |
| S-5 | No automatic fail-over: an ERP that does not answer is an outage (logged), never a reason to sell from the store's figure |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Choose where stock comes from (Priority: High)

1. **Given** the store default is ERP and a product is set to "own stock", **When** a customer adds it to the cart,
   **Then** the cart asks the store's own stock for it and the ERP for every other product.
2. **Given** "ERP off", **When** any product is read or sold, **Then** the store's own stock answers, whatever the
   product's choice; **When** it is turned back on, **Then** each product's choice applies again.
3. **Given** a variable product, **When** one variation is set to "own stock", **Then** only that variation reads it.
4. **Given** the Arabic record of a product, **When** its stock is read or sold, **Then** it reads the same stock as the
   English record (one stock identity, specs/012).

### User Story 2 - Keep the store's own stock (Priority: High)

1. **Given** a product on its own stock, **When** an order is placed, paid, cancelled or fails, **Then** the order's
   reservation takes and gives back stock once per line, exactly as on the ERP (specs/019), and the storefront's in
   stock and out of stock follow the figure in both languages.
2. **Given** a sale for more than the store holds, **When** it is placed, **Then** it is refused before payment.
3. **Given** staff with the right to manage products, **When** they set a product's own stock with a reason, **Then**
   the figure changes once, and the change, its reason and the user are in the product's stock history.
4. **Given** a product on the ERP source, **When** anyone tries to change its stock locally, **Then** it is refused as
   before (specs/026).

### User Story 3 - Let an outside system feed the store's own stock (Priority: Medium)

1. **Given** an integration account holding the stock feed permission, **When** it sends `set` or `change` for SKUs
   with an operation key each, **Then** each is applied once, and the answer gives each SKU's outcome and level.
2. **Given** a key sent again, **When** it arrives, **Then** nothing changes and the answer says it was already applied.
3. **Given** a SKU on the ERP source, an unknown SKU, a change below zero, or a batch over 100 items, **When** it is
   sent, **Then** that item, or the batch, is refused with a reason, and nothing else is affected.
4. **Given** any other account or no account, **When** it calls the route, **Then** it is refused (401 or 403).

### Edge Cases

- A product switched from ERP to its own stock starts from the figure the store last showed for it, recorded as a
  change with reason "switched to own stock"; it is not sellable until that figure is above zero.
- Two changes to one item at the same moment (two orders, an order and the feed) are applied one after the other,
  under a lock per item, never from a stale figure.
- A translated product's choice is held on the stock identity record, so both languages always agree.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| The store's own stock figure | WooCommerce's stock quantity and status, kept in sync across languages by WooCommerce Multilingual | Native: used as the store's figure, written only through the guard's projection |
| One change applied once, with reason and user | `wc_update_product_stock()` has no operation key: a retried call applies twice, and records no reason or user | Gap: a stock change ledger with a unique key per change |
| Choosing a source per item | None | Gap: `StockMode` and `RoutedStockSource` |
| An outside system setting stock by SKU, once per key | WooCommerce's REST API sets stock with no operation key and is refused for stock (specs/026) | Gap: `mizzey/v1/stock` |

## Requirements

### Functional Requirements

- **FR-001**: `StockGateway` MUST route each stock item to the ERP adapter or the store's own stock by the master
  switch, then the item's choice, then the store default (S-1, S-2, AC-1).
- **FR-002**: The store's own stock MUST apply a change once per operation key, under a lock per item, refuse a
  result below zero, write WooCommerce's figure through the guard's projection, and record the change (S-3, AC-2).
- **FR-003**: Staff holding `manage_woocommerce` or `edit_products` MUST be able to set an item's own stock with a reason
  from the product and variation editors; the history MUST show figure, change, reason, user, source and time (AC-2).
- **FR-004**: The setting, the master switch and each product's choice MUST be changed only by staff holding
  `manage_woocommerce` (product choice: `edit_products`), with a nonce, and each change MUST be logged (S-1, S-2).
- **FR-005**: `POST /mizzey/v1/stock` and `GET /mizzey/v1/stock` MUST require the `mizzey_feed_stock` capability, take at
  most 100 items, and answer per item (S-4).
- **FR-006**: An ERP that does not answer MUST never switch an item to the store's own stock (S-5, OD-41).
