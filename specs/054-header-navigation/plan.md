# Implementation Plan: the header and its navigation, as data

**Branch**: `054-header-navigation` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Storefront\HeaderData`: two REST routes for the designed header (its counts and addresses; the category
tree) and a "Show in the main menu" setting on product categories.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/Storefront/HeaderData.php`, registered in `mizzey-site.php` |
| Testing | `t94-header-navigation`, over HTTP |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in US-01-05 and US-01-06 for the rows traced |
| M-4 | WooCommerce's cart count, category order and mini cart; WordPress's logo and menus |
| M-7 | Every criterion has a t94 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t94-header-navigation` asserts |
|---|---|
| AC-1 | The guest's header data before and after adding two items and a wishlist item; the Arabic addresses; `no-store` |
| AC-2 | The tagged categories in order with their children, the empty one left out, in English and Arabic |
| AC-3 | A new order followed; a category taken out of the menu gone in both languages; its Arabic edit screen shows it |
