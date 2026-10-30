# Verification: order status model

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 30 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0 with HPOS order storage, WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite on a clean baseline, serially: 47 scenarios, 41 pass, 6 fact-finding, 0 failed. t51 passes its 80 checks |
| `evidence/mutation.txt` | Four deliberate defects, each caught by t51 |

| AC | Result in t51 |
|---|---|
| AC-1 | Pass. The eleven register names in English; Pending Payment, Payment Failed, Confirmed, Preparing, Delivered and Delivery Failed / Returned to Origin in Arabic; the five new statuses registered with WordPress |
| AC-2 | Pass. Allowed moves succeed; forbidden ones are refused on the order screen (with the allowed next statuses named), the list buttons (checked before WooCommerce's handler), bulk actions, REST (400, order unchanged, also for creation as Shipped) and code paths (status, completed date and other changes kept right, no status hook or transition note, a refusal note). An out-of-date copy cannot change the status, fire a hook or erase a date; a real order cannot go back to a draft; a new order starts only as Pending Payment, Confirmed, On hold, Payment Failed or Cancelled. Payment flows pass, a late payment revives a cancelled order |
| AC-3 | Pass. Staff cancellation without a reason refused on the screen, in bulk and over REST (update and creation); with a reason stored with the person and noted; the unpaid-order timeout and a customer's own cancellation record their own reason |
| AC-4 | Pass. Delivery Failed is not delivered and cannot become Delivered or Cancelled |
| AC-5 | Pass. A cash order cannot skip COD Collected, including at creation; COD Collected only from Shipped or Out for Delivery and only for cash orders; delivery dated from collection; a customer sees Delivered; no "order complete" email when the remittance is recorded; completing a cash payment gives Confirmed; an ordinary cash order is not marked paid early, also after a repeated or aborted payment completion |
| AC-6 | Pass. A partial refund is recorded and leaves the status; a full refund sets Refunded, from Shipped and from a cancelled paid order |
| AC-7 | Pass. Staff changes on the screen, in bulk and over REST, and the cancellation reason, are notes in the person's name; a payment-driven change has its note |
| AC-8 | Pass. First entry recorded at the time of the move, kept on re-entry, and queryable with `wc_get_orders` |
| FR-010 to FR-013 | Pass. Stock unchanged across the new statuses; paid and legacy report lists; the order's language captured natively and kept |

## 2. Staging, 31 October 2026, release v0.1.7

`evidence/staging-T012.txt`. Backup first; deploy, build and the staging check clean; the security check passes. t51
passes on staging, 80 of 80 checks, on MariaDB with the Redis object cache. A real guest checkout through the block
checkout's Store API in Arabic stored the order's language as `ar` (D6), confirmed the cash order without a paid date,
and recorded its first entry; cancelling it by code stored the system reason and WooCommerce restored the unit before
the test order was deleted.

Still to do: the order screen seen in a browser, which needs a staff sign-in with the owner's authenticator.

## 3. Client acceptance

Not done. T013, through MS-UAT-2026-027 (DOD-09), with Stage 3 after Stage 2 is accepted (D-22).
