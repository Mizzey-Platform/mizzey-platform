# Feature Specification: the event tracking plan

**Feature Branch**: `042-event-tracking-plan`

**Created**: 2026-11-02

**Status**: Draft

**Work type**: requirement

**Input**: Issue #274 ("Event Tracking Plan: every event, trigger, parameter and source of truth per business
metric"). No predecessor. The owner's instruction of 2 November 2026 puts it first in the tracking chain (#274, then
#288, #312, #313). The plan is [tracking-plan.md](tracking-plan.md); the branded copy for the client is rendered from
it when the Stage 1 deliverables are packed.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| EVT-20 | DLV | S1 | Event Tracking Plan: defines every event, trigger, parameters, and the source of truth per business metric |

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| EVT-01 to EVT-16, EVT-18 | P1, P1-L | The events the plan defines; built in #312 |
| EVT-17, EVT-19 | P2, DEF | Named as not included |
| HND-12 | DLV | The same plan, handed over |
| MKT-01 to MKT-03, MKT-09, MKT-10, MKT-23 | P1, P1-L | Where the events go, campaign capture and consent; built in #288 |
| RPT-08, RPT-09 | P1-L | The reports the events feed; #313 |

## Open contract items

- **OD-21.** Which advertising platforms are switched on at launch. The plan covers all three either way.
- **CR-13.** The client's feedback on the plan within 10 working days of delivery.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | The plan defines every contracted event with its trigger and parameters, and names what is not included | EVT-20 | provisional |
| AC-2 | The plan names one source of truth for each business metric | EVT-20 | provisional |
| AC-3 | The plan says how one commercial item is identified in events when it exists as two language records | EVT-20 | provisional |

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The agency arrives (Priority: High)

1. **Given** a marketing agency hired after launch, **When** it reads the plan, **Then** it knows which events exist,
   when each fires, what each carries, and which number to trust for each metric, without a call.

### Edge Cases

- A contracted event row missing from the plan: the check fails.
- A deferred or P2 event listed as included: the check fails.

## Native coverage

| Need | Native mechanism | Verdict |
|---|---|---|
| The event names | Google Analytics 4's recommended e-commerce events | Used unchanged |
| The plan | None | Gap: this document |

## Requirements

### Functional Requirements

- **FR-001**: The plan MUST list each of EVT-01 to EVT-16 and EVT-18 with its event name, trigger and parameters (AC-1).
- **FR-002**: The plan MUST name EVT-17 and EVT-19 as not included (AC-1).
- **FR-003**: The plan MUST give the source of truth for revenue, orders, refunds, cash received, cash on delivery,
  traffic, conversion, campaign revenue, advertising cost, search terms and stock (AC-2).
- **FR-004**: The plan MUST identify an item by an identifier both language records share (AC-3).
- **FR-005**: The plan MUST carry no personal data in any parameter, and say so.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- Promotion events (EVT-17, P2), return requests (EVT-19, DEF), the Meta Conversions API (MKT-08, P2), server-side
  tag management (MKT-11, P3).

## Success Criteria

- **SC-001**: `t82-tracking-plan` confirms every contracted event row, the exclusions and the source-of-truth table
  are in the plan.

## Delivery stage

Contractual stage: S1. Engineering timing: November 2026 (D-22).

## Assumptions

- The SKU is copied to the Arabic record of a product (WooCommerce Multilingual's product sync), so it identifies one
  item across both storefronts; `t82` checks it on a product.
