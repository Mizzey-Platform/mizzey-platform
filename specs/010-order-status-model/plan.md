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
| `src/Orders/StatusRegistry.php` | `woocommerce_register_shop_order_post_statuses` adds the five (not public, listed in admin, with counts); `wc_order_statuses` inserts them in model order, gives Pending Payment, Payment Failed, Confirmed and Delivered their register names, and shows COD Collected to a customer as Delivered; `woocommerce_order_is_paid_statuses` and `woocommerce_reports_order_statuses` add Preparing, Shipped, Out for Delivery and COD Collected; `woocommerce_email_enabled_customer_completed_order` is false for an order that went through COD Collected |
| `src/Orders/TransitionGuard.php` | One check for every entry point: the table, the cash route, and the cancellation reason. It reads the status in storage (the HPOS orders table, or the post status), never the copy an object was loaded with. **Order screen**: `woocommerce_process_shop_order_meta` at priority 1, after WooCommerce's nonce check and before its data save at 40; a refusal keeps the current status in the post data and leaves a notice. **List buttons**: `wp_ajax_woocommerce_mark_order_status` at priority 1, with WooCommerce's nonce and capability, redirects back with the reason. **Bulk**: `woocommerce_bulk_action_ids` removes refused orders and every cancellation. **REST**: `woocommerce_rest_pre_insert_shop_order_object`, for updates and creation, returns a 400; a staff change is marked manual so the note names the person. **Last guard**: `woocommerce_before_order_object_save` at the latest priority refuses a forbidden move, a move of a real order back into a draft, and a cash-route breach: it restores the status and the paid and completed dates, clears WooCommerce's pending transition so no status hook, transition note or email fires, notes and logs the refusal, and lets the rest of the save proceed. (`woocommerce_order_edit_status`, fired inside a manual `set_status()` before saving, cannot be recalled; every staff entry point is checked first for that reason.) An allowed move records the first entry, stores a cancellation's reason (required when manual; otherwise the change's note or "Cancelled by the system"), and, when WooCommerce passes no transition note (payment completion), adds the timeline entry |
| `src/Orders/CancellationField.php` | The order screen's reason field, under the order details (admin, English); `woocommerce_order_status_cancelled` adds the reason to the timeline as a private note |
| `languages/` | Arabic for the seven customer-visible labels |

The pending transition is cleared through reflection on `WC_Order::$status_transition`, the only way WooCommerce 11.1.0
offers. The test that refuses a code-path move proves no note, hook or email follows; an upgrade that changes the
property fails that test.

## Criterion to test map

| AC | Scenario `t51-order-status-model` asserts |
|---|---|
| AC-1 | The eleven register names in English and the main ones in Arabic; the five new keys registered with WordPress |
| AC-2 | Every move of a prepaid walk succeeds; refusals on each path: the order screen (meta box save, with the notice naming the allowed moves), the list buttons (check registered at priority 1, before WooCommerce), bulk (filtered, then applied the way WooCommerce applies it), REST (400, order unchanged), and code (`set_status` plus `save`: status, completed date and meta unchanged in storage, no `woocommerce_order_status_changed`, no transition note, a refusal note, the rest of the save kept); a stale copy cannot overwrite a newer status; a real order cannot go back to a draft; a just-created order cannot jump to Delivered. Payment flows pass: `payment_complete`, a failed payment then on hold, a late payment after cancellation; WooCommerce's cash shortcut from Pending Payment to Delivered is refused |
| AC-3 | Staff cancellation without a reason refused on the screen, in bulk and over REST (update and creation); with a reason stored with the user and noted; the unpaid-order timeout and a customer's own cancellation record their own reason |
| AC-4 | Delivery Failed is not delivered; Delivery Failed to Delivered and to Cancelled refused |
| AC-5 | A cash order cannot skip COD Collected; COD Collected only from Shipped or Out for Delivery and only for cash orders; in the delivered set, dated from collection; leads to Delivered; shown to a customer as Delivered; the completed email is off after it and on for a prepaid order |
| AC-6 | A partial refund is recorded and leaves the status; a full refund sets Refunded, from Shipped too and from a cancelled paid order |
| AC-7 | Staff changes on the screen, in bulk and over REST, and the cancellation reason, are notes authored by that user; a payment-driven change has its timeline entry |
| AC-8 | The first entry is recorded within the move's own time window; re-entering a status keeps the first time; `wc_get_orders` with a meta query finds orders by when they were delivered |
| FR-010 | A stock-managed product's stock is unchanged across every move among the new statuses |
| FR-011 | The four statuses are in the paid list and the legacy report list; Delivery Failed is not paid |
| FR-012, FR-013 | An order placed while Arabic is current stores `wpml_language` = ar (D6, native), and status moves keep it |

Mutation checks: removing the transition clearing, allowing Confirmed to Delivered, and dropping the reason requirement
each fail t51.

## Risks

| Risk | Mitigation |
|---|---|
| A payment or shipping plugin uses a move the table forbids | The table allows every move WooCommerce's payment flows make; a refusal is logged and noted on the order, so it is visible, never silent |
| The reflection on the pending transition breaks on an upgrade | Pinned WooCommerce 11.1.0; the code-path test fails loudly |
| Renaming Completed to Delivered surprises staff used to WooCommerce | Admin is English; the labels match the register the client signed |
