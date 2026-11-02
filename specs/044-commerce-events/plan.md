# Implementation Plan: the commerce events

**Branch**: `044-commerce-events` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

`Marketing/Events` builds the items (SKU as the id), works out the page events, queues the store's events in the
session and hands them over once (the page, or the Store API's cart answer), fires purchase on confirmation and sends
refunds by the Measurement Protocol. `assets/events.js` pushes them to the data layer and listens to WooCommerce
Blocks for card clicks and the shipping and payment choices.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Marketing/Events.php`, `assets/events.js`; hook points added to `Wishlist::add`, `GoogleSignIn`, `SignInLink` (`mizzey_wishlist_added`, `mizzey_signed_in_by`) and `TagManager::render` (`mizzey_marketing_tags_fields`) |
| Data | Session key `mizzey_events`; order meta `_mizzey_purchase_tracked`, `_mizzey_ga_client_id`; options `mizzey_ga4_measurement_id`, `mizzey_ga4_api_secret` (the secret is never printed back) |
| Extension points | `wp_enqueue_scripts`, `woocommerce_add_to_cart`, `woocommerce_cart_item_removed`, `woocommerce_after_cart_item_quantity_update`, `woocommerce_applied_coupon`, `wp_login`, `user_register`, `woocommerce_checkout_order_created`, `woocommerce_store_api_checkout_order_processed`, `woocommerce_order_refunded`, Store API endpoint data (`mizzey-events`) |
| Testing | `t84-commerce-events`: page events over the real request in both languages, the queue, the Store API answer, purchase and refund; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4, D-24 | WooCommerce's own hooks and the Store API's extension data; no analytics plugin |
| MKT-23 | Events go to the data layer only; Tag Manager sends them on |
| M-7 | Each criterion has an automated check |
