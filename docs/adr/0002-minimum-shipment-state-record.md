# ADR-0002: Minimum shipment state and timing record for Option B

**Status:** Proposed. This is the design direction. It is **not** permission to implement Bosta status mappings,
which are unverified.
**Date:** 2026-09-22
**Traces:** SHIP-14 (P1 key, S1), SHIP-16 (P1-L key, S1), SHIP-17 (P1 key, S1), ENT-10 (P1-L, S1), ADM-86 (P1, S1)
**Refines:** Technical Design v1.2 §6 row "ENT-10 Shipment | New table | Carrier, tracking, status, and collected
versus remitted for SHIP-16"
**Revisits:** `DECISIONS.md` 2026-09-09 "Shipment status timestamps on ENT-10" (made under Option C)

## Context

What the contract requires:

| Row | Wording | What must be recorded |
|---|---|---|
| SHIP-14 | Explicit carrier-status-to-order-status mapping, documented and visible in admin | A mapping table and the current mapped state. No history |
| SHIP-16 | Cash-on-delivery reconciliation: collected versus remitted recorded per order | Per order, amount collected and amount remitted, and enough to tell which collected sums are unremitted |
| SHIP-17 | Returned-to-origin handled as a distinct state, never silently recorded as delivered | A distinct state value. No timestamp is named |
| ENT-10 | Shipment: carrier, tracking, rate, status | The shipment record |
| ADM-86 | Order detail ... timeline | Met natively by order notes (P-018). Nothing to build |

Not contracted in Option B: delivery-time or returned-to-origin-rate measurement, and per-transition analytics.

Evidence:

- P-018: WooCommerce keeps no structured status history. `date_paid` and `date_completed` are the only queryable
  milestones. A cash-on-delivery order is marked paid on completion, so collected and remitted cannot be told apart
  natively.
- P-012: in Bosta 4.5.7 the status mapping is dormant (`map_bosta_state_to_wc_status` is commented out). Webhooks
  only overwrite `bosta_state_code` and `bosta_status` meta. States 45 and 46 are both "Finished successfully", and
  only 45 sets `bosta_delivery_date`. The plugin ships no legend for its codes.

## Options compared

| | A. Three timestamps (9 Sep decision) | B. Append-only event log |
|---|---|---|
| Shape | Columns on ENT-10 `dispatched_at`, `delivered_at`, `returned_at`, plus current status | One row per status event; current state derived |
| Serves SHIP-14, 16, 17 | Yes | Yes |
| Build and test cost | Low | Higher: second table, derivation, ordering, dedupe, retention |
| Adds beyond Option B | Two prudence columns | Measurement-grade history for reporting that is not contracted |
| Risk of drifting into Option C | Low | High |

## Decision (proposed)

Model A, trimmed to what the contract needs.

**Required (contract):**

1. The ENT-10 shipment record: carrier, tracking, rate, current mapped status.
2. A mapping table from carrier codes to Mizzey states, visible in admin, with returned-to-origin as its own state.
   An unmapped code lands in a visible "needs review" state, never in delivered.
3. Per order: collected amount, collected-at (the carrier's delivery or collection time), remitted amount,
   remitted-at and remittance reference. Collected-at is included because collected and remitted cannot be
   reconciled against a remittance cycle without knowing when collection happened.
4. Write-once rules: collected-at is set once, from the first qualifying event. A repeated webhook changes nothing.
   A later returned-to-origin event overrides delivered, never the reverse.

**Optional safeguards (not owed, need approval):** `dispatched_at` and `returned_at`, which are cheap while the table
is designed and unrecoverable later. Plus keeping the raw carrier code and time in an order note on each update
(native).

**Rejected for Option B:** model B, the event log.

## Depends on

| Decision point | Depends on | Until then |
|---|---|---|
| Which carrier codes map to delivered, returned-to-origin, failed or in-transit (item 2) | **Bosta's status code legend**, from Bosta documentation or the client's Bosta account (review comment C-09). What code 46 means is not knowable from the plugin (P-012) | No mapping is implemented. Unknown codes go to "needs review" |
| Which event sets collected-at (item 3) | **Bosta's legend**: which code means cash collected, and whether its event time is the collection time | Field designed, source not chosen |
| Whether a returned-to-origin can follow a delivery for the same parcel (item 4) | **Bosta's legend** and its lifecycle rules | The override rule stays proposed |
| What "remitted" means and how it is recorded: per order or per remittance batch, who records it, from which statement | **The client's COD remittance process**, OD-20 (carrier COD collection and remittance cycle) | Remitted fields designed, entry method not chosen |
| Whether remitted-at and the reference are entered by hand, imported from a Bosta statement, or read through an API | **OD-20** and Bosta's remittance reporting | Not implemented |
| Whether COD is offered at all, with which limits | **OD-05** | SHIP-16 build waits |

## Consequences

- One table (ENT-10), already committed in the TDD. No reporting infrastructure.
- Stage 1 review comment C-08 asks the TDD to name the fields, with no scope change.
- The mapping and remittance work is implemented only after the inputs above arrive, each through its own spec.

## Alternatives rejected

Parsing order-note prose (P-018: not a basis for a figure). Relying on plugin meta (overwritten on each update).
The event log (model B), which stays available if delivery-time or returned-to-origin reporting is ever contracted.
