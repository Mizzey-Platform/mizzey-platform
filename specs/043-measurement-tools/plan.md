# Implementation Plan: measurement and advertising tools

**Branch**: `043-measurement-tools` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

`TagManager` prints the consent defaults and the named container on the storefront; `ConsentBanner` asks once and
gates WooCommerce's order attribution; `ProductFeed` serves one product feed per language. The scripts are files in
`assets/`, so no tracking code is written into templates. The pixels and Analytics are tags in the client's own
container.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Marketing/TagManager.php`, `ConsentBanner.php`, `ProductFeed.php`; `assets/consent-default.js`, `consent.js`, `gtm.js` |
| Data | Option `mizzey_gtm_container`; cookie `mizzey_consent` (`granted` or `denied`, 180 days); transients `mizzey_feed_products_en`, `_ar` |
| Extension points | `wp_head` (1), `wp_body_open` (1), `wp_footer` (5), `wp_enqueue_scripts`, `wc_order_attribution_allow_tracking`, `template_redirect` (0), `woocommerce_update_product`, `woocommerce_new_product`, `woocommerce_delete_product`, the Settings API |
| Testing | `t83-measurement-tools`, over the real request in both languages; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4, D-24 | WooCommerce's order attribution and Google's consent mode; our own small banner and feed instead of plugins |
| MKT-23 | Tag Manager is the only route; nothing else is printed |
| M-7 | Each criterion has an automated check |
