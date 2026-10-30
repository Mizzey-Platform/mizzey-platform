# Implementation Plan: order status model and the record of when each status was entered

**Branch**: `010-order-status-model` | **Date**: 2026-10-30 | **Spec**: [spec.md](spec.md)

## Summary

Five statuses are added through WooCommerce's own status registration (Preparing, Shipped, Out for Delivery, COD
Collected, Delivery Failed), and two native ones are named for the register (Processing is Confirmed, Completed is
Delivered). One transition table decides every move. The order screen, bulk actions and the REST API are checked
before WooCommerce sets the status, so a refusal is a clean message; a last guard before every order save undoes a
forbidden move made by any other code without letting WooCommerce run its status-change steps. The same save records
the first time the order entered its new status, as order data, and a cancellation's reason.

## Technical Context

| | |
|---|---|
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0 with HPOS order storage (`woocommerce_custom_orders_table_enabled` = yes on the runtimes), WPML 4.9.7, WCML 5.5.7, CoreX v0.47.0 |
| Storage | Order meta only: `_mizzey_status_entered_<status>` (UTC timestamp, written once), `_mizzey_cancellation_reason`, `_mizzey_cancelled_by` (user id, 0 for system or customer). Underscore keys are hidden from the custom fields box. No table |
| Dependencies | None new |
| Testing | `t51-order-status-model`: in-process through WooCommerce's real handlers (the order data meta box save, the bulk-action filter, `rest_do_request` on `/wc/v3/orders`, `update_status`, `payment_complete`), on HPOS. Staging: the order screen in a browser (T012) |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | ORD-01 to ORD-08, ORD-12 to ORD-14, ADM-86, ADM-87. Return statuses (DEF), reporting (P2) and carrier mapping (#291) are out |
| M-4 native first | Yes | WooCommerce's status registration, labels, refunds, notes and paid-status filter are used. Custom code covers only the recorded gaps: no Returned to Origin status (P-012), no structured status history (P-018), no transition validation, no cancellation reason, REST changes not attributed to the person |
| M-5 three lists | Yes | Bulk actions for the new statuses are S-1, not built |
| M-7 tests | Yes | Every order-state path has an automated test, stock included |
| M-9 languages | Yes | Status labels are translated into Arabic; the admin stays English |
| D-19 item 5 | Yes | No stock hook on the new statuses; Delivery Failed cannot reach Cancelled, whose native restoration is the ERP's |
| ADR-0002, D-20 C9 | Yes | First-entry timestamps per status, written once; no event log |

## Design

| Piece | Responsibility |
|---|---|
| `src/Orders/StatusModel.php` | The data: new statuses and their labels, the two renamed labels, the transition table, the delivered set, the extra paid statuses, the meta keys. `allowed($from, $to)`, `isDelivered($order)`, `enteredAt($order, $status)`, `deliveredAt($order)`. From an unknown status (one a removed plugin left), any contracted status is allowed; into an unknown status, nothing |
| `src/Orders/StatusRegistry.php` | `woocommerce_register_shop_order_post_statuses` adds the five (not public, listed in admin, with counts); `wc_order_statuses` inserts them in model order and renames Processing and Completed; `woocommerce_order_is_paid_statuses` adds Preparing, Shipped, Out for Delivery and COD Collected |
| `src/Orders/TransitionGuard.php` | **Order screen**: `woocommerce_process_shop_order_meta` at priority 1, after WooCommerce's nonce check and before its data save at 40, reads the posted status; a forbidden move, or a cancellation without the posted reason, keeps the current status in the post data and reports through `WC_Admin_Meta_Boxes::add_error`. **Bulk**: `woocommerce_bulk_action_ids` removes, for `mark_<status>`, the orders whose move is forbidden and every cancellation (no reason can be given in bulk), keeps the refused numbers for the user and shows them once. **REST**: `woocommerce_rest_pre_insert_shop_order_object` returns a 400 `WP_Error` for a forbidden move or a staff cancellation without `cancellation_reason`; for a staff request it sets `manual_update`, so the note names the person. **Last guard**: `woocommerce_before_order_object_save` at the latest priority compares the saved status with the pending one; a forbidden move restores the status and the paid and completed dates as stored, clears WooCommerce's pending transition so no status hook, note or email fires, adds a note that names the refused move, and logs it; the rest of the save proceeds. An allowed move records `_mizzey_status_entered_<to>` if absent, and for Cancelled stores the reason: the one the screen or REST passed, required when the change is manual; otherwise the change's own note ("Unpaid order cancelled - time limit reached.", "Order cancelled by customer.") or "Cancelled by the system". New orders and orders leaving the checkout draft take any first status, and their first status is recorded too |
| `src/Orders/CancellationField.php` | The order screen's reason field, under the order details (admin, English); `woocommerce_order_status_cancelled` adds the reason to the timeline as a private note |
| `languages/` | Arabic for the seven customer-visible labels |

The pending transition is cleared through reflection on `WC_Order::$status_transition`, the only way WooCommerce 11.1.0
offers. The test that refuses a code-path move proves no note, hook or email follows; an upgrade that changes the
property fails that test.

## Criterion to test map

| AC | Scenario `t51-order-status-model` asserts |
|---|---|
| AC-1 | The eleven states and their names in English and Arabic; the five new keys registered and listed in admin; Preparing, Shipped, Out for Delivery and COD Collected are paid, Delivery Failed is not |
| AC-2 | Every allowed move in the table succeeds and a sample of forbidden ones is refused on each path: the order screen (meta box save), bulk (`woocommerce_bulk_action_ids`), REST (400, order unchanged), and code (`set_status` plus `save`: status, dates and meta unchanged in storage, no `woocommerce_order_status_changed`, no transition note, a refusal note). Payment callbacks are not blocked (`payment_complete`, a failed payment, on hold) |
| AC-3 | Staff cancellation without a reason refused on the screen, in bulk and over REST; with a reason stored with the user and noted; the unpaid-order timeout and a customer's own cancellation record their own reason |
| AC-4 | Delivery Failed is not in the delivered set; Delivery Failed to Delivered and to Cancelled refused |
| AC-5 | COD Collected only from Shipped or Out for Delivery; in the delivered set; leads to Delivered |
| AC-6 | A full refund sets Refunded; a partial refund leaves the status |
| AC-7 | Staff changes on the screen, in bulk and over REST produce a timeline note authored by that user |
| AC-8 | First-entry times equal the transition notes' times within two seconds; re-entering a status keeps the first time; `wc_get_orders` with a meta query finds orders by when they were delivered |
| FR-010 | A stock-managed product's stock is unchanged across every move among the new statuses |
| FR-012, FR-013 | An order placed while Arabic is current stores `wpml_language` = ar (D6, native), and status moves keep it |

## Risks

| Risk | Mitigation |
|---|---|
| A payment or shipping plugin uses a move the table forbids | The table allows every move WooCommerce's payment flows make; a refusal is logged and noted on the order, so it is visible, never silent |
| The reflection on the pending transition breaks on an upgrade | Pinned WooCommerce 11.1.0; the code-path test fails loudly |
| Renaming Completed to Delivered surprises staff used to WooCommerce | Admin is English; the labels match the register the client signed |
