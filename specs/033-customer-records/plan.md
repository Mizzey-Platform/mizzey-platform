# Implementation Plan: the customer record in admin

**Branch**: `033-customer-records` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

One class, `CustomerRecords`, adds a "Customer record" section to WordPress's user screen (orders, internal notes,
suspension) and enforces a suspension on `authenticate` (password, with its message) and `determine_current_user`
(any cookie).

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 |
| Code | `mizzey-site/src/Account/CustomerRecords.php` |
| Extension points | `edit_user_profile`, `edit_user_profile_update`, `user_profile_update_errors`, `authenticate` (99), `determine_current_user` (99) |
| Data | User meta `_mizzey_customer_notes`, `_mizzey_suspended`, `_mizzey_suspension_reason`, `_mizzey_suspension_history` (private, not in the REST API) |
| Testing | `t73-customer-records`, in process and over the real sign-in request in both languages; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | ADM-100, ADM-103, ADM-104 only; ADM-101, ADM-105 (S2) and ADM-102 (P2) not built |
| M-7 | Each criterion has an automated check; deliberate defects are caught |
| FIX-04 | The customer's refusal message has its Arabic; staff text is English (ADM-159) |
