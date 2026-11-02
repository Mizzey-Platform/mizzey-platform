# Implementation Plan: payments through Paymob

**Branch**: `028-payments` | **Date**: 2026-11-01 | **Spec**: [spec.md](spec.md)

## Summary

The official Paymob plugin (PAY-13) takes the payment on Paymob's hosted page and refunds through Paymob. Mizzey's
payment layer stands in front of every request to the plugin's callback address and decides, before the plugin
acts, whether the request is Paymob's, whether it matches the order, and whether it was already applied. It keeps the
payment record on the order and gives Paymob a callback address that staging's access gate lets through.

Reading the plugin (4.1.15) settled what the layer must do, not only what the register asks:

| Plugin behaviour | Consequence | Layer answer |
|---|---|---|
| On a successful payment it sets the order total to the amount Paymob reports, then marks the order paid | A payment of another amount would rewrite the order instead of being refused (AC-4) | The amount and currency are compared with the order before the plugin runs; a mismatch is recorded and flagged, the order is not touched |
| The callback address takes requests from anyone | The store must rely on its own check, not on what reaches the address | No request reaches the plugin unless the layer has verified its signature itself (AC-4) |
| A refund made from the order screen is recorded, then Paymob's refund notice is recorded again | One refund counted twice (AC-2, AC-7) | The refund's Paymob id is captured when it is made; its notice is then acknowledged without a second record |
| A pending transaction notice marks the order failed | A wallet payment still in progress would show as failed | Pending notices are recorded and change nothing |
| Its default callback address is `/?wc-api=paymob_callback`, on the site's root path | Staging's access gate cannot let it through without opening the site | The layer sends Paymob the path form `/wc-api/paymob_callback/`, which reaches the same handler |

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`); Paymob for WooCommerce 4.1.15 from wordpress.org, recorded in `stack.lock.json`; the release packer's line is its own tooling PR, as WP 2FA's was |
| Code | `mizzey-site/src/Payments/`: `PaymentProvider` (the seam), `CallbackResult`, `PaymentEvent`, `PaymentGate` (the layer), `PaymentRecord` (the record on the order and the staff panel), `RefundLedger`, `Paymob/PaymobProvider`, `Paymob/CallbackAddress` |
| Extension points | `woocommerce_api_{action}` at priority 1, ahead of the plugin's 10; `paymob_intention_data`; `woocommerce_create_refund`; `woocommerce_new_order_note_data`; `woocommerce_admin_order_data_after_order_details` |
| Concurrency | A database lock per order (`GET_LOCK`) held for the rest of the request, taken by every callback and by a refund, so two notices of one payment, or a refund and its notice, run one after the other |
| Card data | The layer stores the provider's transaction id, kind, outcome, amount, currency and times, never a card field; refusals are logged by order and reason only |
| Testing | `t68-payments`: in process for the layer with a stand-in provider, and over HTTP to the local runtime for the real plugin with signed and forged callbacks |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 (2.0.0) | The provider integration is the plugin PAY-13 names; Mizzey's code is the layer the same row asks for |
| M-6 | Every row stays S1; PAY-09 and PAY-15 are switches, not moved stages |
| M-7 | Each criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every customer and staff message has its Arabic |
| Security | Forged, replayed, mismatched and unknown callbacks are tested; no card data stored; the access-gate exception is one path |

## Criterion to test map

| AC | `t68-payments` asserts |
|---|---|
| AC-1 | A second successful payment of an already paid order is refunded in full through Paymob once (API stood in for), does not move the order and leaves the order's own refunds alone; its repeat is not refunded again; its refund notice is acknowledged; a refund Paymob does not make is flagged for staff |
| AC-2 | The same success notice twice, the notice after the return, and a refund notice for a refund made from the order screen each change the order once |
| AC-3 | A failed notice leaves the order unpaid; a pending notice changes nothing; an unsigned return changes nothing |
| AC-4 | Unsigned, wrongly signed, other-amount, other-currency and unknown-order callbacks are refused with no change, by POST and by GET, through the real plugin |
| AC-5 | After the run, no card number from the test payloads is in the database or the logs |
| AC-6 | Each verified event stores reference, kind, outcome, amount, currency and time on the order, once |
| AC-7 | A refund from the order screen goes through the plugin's refund (stubbed transport), under BR-006's limit; a refused provider refund leaves no refund |
| AC-8 | A stand-in provider registered through `mizzey_payment_providers` is gated the same way with no checkout change |
| AC-9 | Native gateway switches; recorded as configuration, checked on staging |
| AC-10 | `docs/payments-settlement.md` exists and the staff panel says Paid by Paymob, not received |

## Not built here

- The release packer's line for the plugin (internal:tooling PR).
