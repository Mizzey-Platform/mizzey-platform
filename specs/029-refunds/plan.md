# Implementation Plan: refunds with their payment reference

**Branch**: `029-refunds` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's refund panel, the Paymob plugin's refund and the payment layer of specs/028 already make and count
refunds. What RET-08 and ADM-124 still need is the reference on each refund, a place to type one for a refund made by
hand, and the reference in the standard email. `RefundReference` stamps each refund before WooCommerce saves it, takes
the provider's refund id from the note `RefundLedger` already reads, shows both references on the refund line, and
adds one line to the refunded-order email.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, Paymob for WooCommerce 4.1.15 (`stack.lock.json`) |
| Code | `mizzey-site/src/Orders/RefundReference.php`; one call from `Payments/RefundLedger.php`; one argument in `Payments/PaymentGate.php` (the dashboard refund); `mizzey-site/assets/admin-refund-reference.js` |
| Extension points | `woocommerce_create_refund` (priority 20, after `RefundIntegrity` and the order lock), `woocommerce_new_order_note_data` (through `RefundLedger`), `woocommerce_after_order_refund_item_name`, `admin_enqueue_scripts`, `woocommerce_email_after_order_table` |
| Data | Refund meta `_mizzey_payment_reference`, `_mizzey_refund_reference`, `_mizzey_refund_reference_source` (gateway, dashboard, staff) |
| Security | The typed reference is read only inside WooCommerce's refund request, with its nonce checked again and the refund capability (`RefundCapability`, specs/013); plain text, 100 characters |
| Testing | `t69-refunds`, in process, with a stand-in gateway that reports its refund the way Paymob's plugin does, and a signed Paymob notice for the dashboard refund; mutation evidence in `evidence/mutation.txt` |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | RET-08 and ADM-124 only; no returns feature (AC-6) |
| M-4 | Native refund panel, gateway refund and email kept; the gaps are recorded in the spec's Native coverage |
| M-7 | Each criterion has an automated check; deliberate defects are caught |
| FIX-04 | The two customer email lines have their Arabic; staff text is English (ADM-159) |
