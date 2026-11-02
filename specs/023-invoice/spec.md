# Feature Specification: the invoice

**Feature Branch**: `023-invoice`

**Created**: 2026-10-31

**Status**: Draft

**Work type**: requirement

**Input**: User description: "continue until you finish all waves" (the owner, 31 October 2026), wave 3 of the Stage 3
build plan under D-22: "E-ADM-5 invoice document, ADM-92 only: the invoice ACCT-12 downloads". #280. Built as our own
code under D-24 ("don't use ready made plugin, create ours", the owner, 31 October 2026), with mPDF bundled as the PDF
library, on the owner's approval of the same day. Built on `022-my-account`, which carries the order language helper
(specs/021) and the account area (specs/022).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-92 | P1-L | S1 | Print / download invoice |
| ACCT-12 | P1-L | S1 | Download invoice |

Stories: Functional Specification MS-SPC-2026-032 v1.4, US-10-03 (invoice download) and US-18-06 (invoices and
packing slips), the criteria that trace to the rows above. ACCT-12 is #308's row (specs/022, AC-6); its download is
built here, on this invoice.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-93 | P1-L | The packing slip: #280's, not in this wave (the Stage 3 plan names ADM-92 only) |
| ADM-85, ADM-88 to ADM-91 | P1, P1-L | #280's order management rows, not in this wave |
| INT-17 | OUT (EX-32) | The Egyptian Tax Authority's e-invoice: out of scope; tax reporting is the client's accounting or ERP system |

## Open contract items

- OD-09 (the tax treatment) and CR-12 and OD-18 (the legal entity details): the client's to supply. Until then the
  seller details are settings left empty, which the invoice leaves off; the tax lines are those WooCommerce records on
  the order, so the invoice follows whatever tax setup OD-09 settles.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | A paid order has an invoice with its own number, issued once, in sequence; an unpaid order has none | ADM-92 | provisional |
| AC-2 | The invoice carries the seller's legal details and the amounts actually charged, discounts included | ADM-92 | pending OD-09 |
| AC-3 | Staff print or download an order's invoice from the order | ADM-92 | provisional |
| AC-4 | The customer downloads an order's invoice from the account | ACCT-12 | provisional |

Provisional: verified by automated tests; acceptance follows manual testing on staging and the client's approval.
AC-2 is built and tested with sample details; its acceptance waits for the tax treatment (OD-09) and the legal entity
details (CR-12, OD-18).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A document to file (Priority: High)

1. **Given** a paid order, **When** its customer opens the account's orders, **Then** an Invoice action downloads a
   PDF with the seller's details, the invoice and order numbers, the billing details and the amounts charged.
2. **Given** an Arabic order, **When** its invoice is downloaded, **Then** it is in Arabic, right to left.

### User Story 2 - The paperwork for the parcel and the accounts (Priority: High)

1. **Given** a paid order, **When** staff open it, or the orders list, **Then** they download its invoice.

### Edge Cases

- An unpaid, failed or cancelled order has no invoice and no Invoice action; a refunded order keeps the invoice it
  had, showing the refund and the net.
- Another customer, or a link without its nonce, is refused.
- Two orders paid at once never get the same number.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| An invoice document | None: WooCommerce has no invoice or packing slip | Gap: `InvoiceDocument`, `InvoicePdf` |
| Invoice numbers | None | Gap: `InvoiceNumber` |
| Seller details | None | Gap: `InvoiceSettings` |
| Download from the account and the admin | The account's order actions, the admin's order actions | Extension: `InvoiceRoutes` |

A ready-made invoice plugin is not used (D-24, constitution M-4 2.0.0). The PDF is made by mPDF 8.3.1, a library
bundled in `mizzey-site/lib/` and pinned by `mizzey-site/composer.lock`, chosen because it shapes Arabic correctly;
its fonts are trimmed to DejaVu Sans.

## Requirements

### Functional Requirements

- **FR-001**: An order MUST get an invoice number the first time it reaches a paid status, or the first time its
  invoice is asked for if it was paid earlier; never for an unpaid one; once, in sequence, under a lock (AC-1).
- **FR-002**: The invoice MUST carry the seller's details set in WooCommerce's settings, the invoice number and date,
  the order number and date, the payment method, the billing details in the Egyptian structure, each line with its
  quantity and price charged, the discount, shipping, fees, taxes as recorded, the total, and refunds with the net
  (AC-2).
- **FR-003**: The invoice MUST be in the order's language and direction (AC-2).
- **FR-004**: Staff who edit orders MUST find the download on the order's screen and in the orders list (AC-3).
- **FR-005**: The order's own signed-in customer MUST find it beside the paid order in the account's orders (AC-4).
- **FR-006**: Every download MUST check the nonce for the order and the user's right to it (AC-3, AC-4).
- **FR-007**: Every label MUST be translated (FIX-04).

### Key Entities

- **Invoice**: the order's number and issue date (order meta), drawn from one counter.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- The packing slip (ADM-93) and bulk printing (ADM-156, DEF) are #280's later part.
- Sending the invoice by email is not contracted.

## Success Criteria

- **SC-001**: Every paid order has exactly one invoice number, and no two orders share one.

## Delivery stage

Contractual stage: S1, delivered with Stage 3 (D-22). Engineering timing: wave 3 of the Stage 3 build plan, from 31
October 2026.

## Assumptions

- The number format, a prefix (`INV-` by default, a setting) and six digits, is a working default (an owner decision,
  not a client confirmation).
- A cash on delivery order is invoiced when it reaches Processing, as WooCommerce counts it paid there.
