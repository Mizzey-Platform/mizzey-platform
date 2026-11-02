# Feature Specification: the store at a glance

**Feature Branch**: `035-admin-dashboard`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Slice E-ADM-1, issue #294 ("Admin dashboard: sales figures, top products, low and out-of-stock counts").
Its predecessor #246 (one physical item counted once) is verified. The owner's instruction of 2 November 2026 puts it
in the admin set that needs no client input; the stock figures use the adapter that already exists (specs/012).

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| ADM-01 | P1 | S1 | Revenue |
| ADM-02 | P1 | S1 | Orders |
| ADM-03 | P1 | S1 | Units sold |
| ADM-04 | P1-L | S1 | Average order value |
| ADM-06 | P1-L | S1 | Refunds and returns |
| ADM-08 | P1-L | S1 | Charts by day, week, month, custom range |
| ADM-09 | P1 | S1 | Top products |
| ADM-10 | P1 | S1 | Low stock, as reported by your ERP |
| ADM-11 | P1 | S1 | Out of stock, as reported by your ERP |
| ADM-12 | P1 | S1 | Orders requiring action |
| ADM-13 | P1 | S1 | Pending returns and refunds |
| ADM-15 | P1 | S1 | Quick actions: add product, create coupon, view pending orders |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-05, ADM-07 | P1-L, S2 | New and returning customers, conversion rate: the second release |
| ADM-14, ADM-140 to ADM-143 | P2, DEF | Not built |
| RPT-01 | P1-L | The sales report (specs/034); the dashboard reads the same figures |
| RPT-10 | P1 | One physical item counted once (specs/004) |
| ERP-01, INT-16 | P1-E, P1 | Stock is the ERP's, asked through the seam (specs/012) |

## Open contract items

- **PRE-09.** ADM-10 and ADM-11 read the ERP's figures through the seam; their criteria stay pending PRE-09 (M-3).
- **CX-05** (D-12, owner decision, not client-confirmed): ADM-12 and ADM-13 are counts linked to standard filtered
  views; ADM-13 counts only refund states the launch platform has. No returns queue.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The dashboard shows revenue, orders, units, average order value and refunds for today, 7 or 30 days, agreeing with the sales report | ADM-01, ADM-02, ADM-03, ADM-04, ADM-06 | provisional |
| AC-2 | Charts by day, week, month and a custom range are reached from the dashboard | ADM-08 | provisional |
| AC-3 | Top products are listed by units, one line per physical item whatever its languages | ADM-09 | provisional |
| AC-4 | Orders requiring action and refunds still to make are counted, each linked to the filtered orders list | ADM-12, ADM-13, ADM-06 | pending CX-05 |
| AC-5 | Low-stock and out-of-stock counts come from the stock source, one answer per physical item | ADM-10, ADM-11 | pending PRE-09 |
| AC-6 | Quick actions add a product, create a coupon and open the pending orders | ADM-15 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Monday morning (Priority: High)

1. **Given** last week's orders in both languages, **When** the owner opens the dashboard, **Then** revenue, orders,
   units, the average order and refunds match the sales report, the best sellers are listed once each, and the orders
   to prepare and the refunds to make are counted with links.

### Edge Cases

- The stock source cannot answer for an item: counted as unknown, never as in stock.
- A product in two languages: one line in top products, one item in the stock counts.
- A customer reaching the dashboard: sees nothing of it.
- Many orders: the action counts are made by the database; each refund list holds the newest 500 orders and its
  count then reads "500 or more".
- Which orders are sales, and by which date: the sales report's settings (specs/034 AC-4).

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| Revenue, orders, units, average order value, refunds, charts | WooCommerce Analytics (Overview, Revenue, Orders) | Native, read by the widget; charts linked |
| Top products | Analytics' leaderboard lists each language record | Gap: units summed per physical item |
| Low and out of stock | WooCommerce reads its own stock, which is not the ERP's (D-19) | Gap: the seam's answers, per physical item |
| Orders requiring action, refunds to make, quick actions | None on the dashboard | Gap: counts and links |

## Requirements

### Functional Requirements

- **FR-001**: Sales figures MUST be WooCommerce Analytics' revenue totals for the chosen period (AC-1).
- **FR-002**: Top products MUST sum units per canonical product (AC-3).
- **FR-003**: Orders requiring action MUST count On hold, Confirmed, Preparing and Delivery failed orders; refunds to
  make MUST count paid orders cancelled and not fully refunded and orders the payment layer flagged (AC-4).
- **FR-004**: Stock counts MUST ask the stock source once per physical item through the seam, count an unanswered
  item as unknown, and keep the result ten minutes (AC-5).
- **FR-005**: Only staff who may view reports MUST see the widget.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- New and returning customers, conversion (ADM-05, ADM-07, S2); previous-period comparison (ADM-14, P2).

## Success Criteria

- **SC-001**: On staging, the week's figures agree with Analytics and the stock counts with the stock source.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22, D-27).

## Assumptions

- The low-stock threshold is WooCommerce's own setting (2 by default), which the client may change.
