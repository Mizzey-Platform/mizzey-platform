# Feature Specification: the sales report

**Feature Branch**: `034-sales-report`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-RPT-1, issue #286 ("Sales report: revenue, net sales, orders, units, average order value, discounts,
shipping"). No predecessor. The owner's instruction of 2 November 2026 puts it in the admin set that needs no client
input.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| RPT-01 | P1-L | S1 | Sales: revenue, net sales, orders, units, AOV, discounts, shipping |

RPT-01 is P1-L: standard sales reporting. The register points other reporting needs at it ("The standard admin
dashboard and sales reporting (RPT-01)" for ADM-143; "Standard sales reporting (RPT-01) and Google Analytics 4" for
RPT-03).

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ROLE-06 | DEF | The Accountant profile lists RPT-01 (CX-01); who reads reports is the role set's (specs/013, D-20 C8) |
| RPT-02 to RPT-07, ADM-143 | P2, DEF | Not built |
| NFR-07 | P1 | Data integrity across both languages: the figures must count every order once, whatever its language |

## Open contract items

- None.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | For any period, the report gives revenue, net sales, orders, units, average order value, discounts and shipping, counting English and Arabic orders once each, matching a hand calculation | RPT-01 | provisional |
| AC-2 | The figures are the same whichever language the administration is opened in | RPT-01 | provisional |
| AC-3 | Only staff with access to reports can read it | RPT-01 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Last week's sales (Priority: High)

1. **Given** orders placed in English and in Arabic, with discounts, shipping and a refund, **When** the owner opens
   Analytics, Revenue and Orders for that week, **Then** the seven figures match a hand calculation.

### Edge Cases

- A refund on the day: lowers net sales and revenue by its amount.
- The administration switched to Arabic: the same figures.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Revenue (total sales), net sales, discounts (coupons), shipping, refunds | WooCommerce Analytics, Revenue report | Native, verified |
| Orders, units (items sold), average order value | WooCommerce Analytics, Orders report | Native, verified |
| Every order counted once in both languages | Analytics reads order-level statistics (`wc_order_stats`), which WPML does not split | Native, verified (the product split P-019 measured does not apply) |
| Access | WooCommerce's `view_woocommerce_reports` | Native |

## Requirements

### Functional Requirements

- **FR-001**: The sales figures MUST come from WooCommerce Analytics' Revenue and Orders reports, with no second
  calculation (AC-1).
- **FR-002**: The figures MUST be checked against a hand calculation over English and Arabic orders, in process and
  through the screen's own request in both admin languages (AC-1, AC-2).
- **FR-003**: A failure of either check MUST be fixed in Mizzey's code at its cause, not by a parallel report.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Product performance, customer and marketing reports (RPT-02 to RPT-07), today's summary widget (ADM-143).

## Success Criteria

- **SC-001**: On staging, the week's figures for the seeded orders match a hand calculation in both admin languages.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- "Revenue" is Analytics' total sales (net sales plus shipping and taxes), "net sales" its net sales after discounts
  and refunds, "discounts" its coupons, "units" its items sold.
