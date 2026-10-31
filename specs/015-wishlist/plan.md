# Implementation Plan: wishlist and recently viewed

**Branch**: `015-wishlist` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

`Wishlist` and `RecentlyViewed` keep canonical product ids (`CanonicalProduct`, the translation group's source record,
now shared with the stock seam): on the account for customers, in a signed cookie (`GuestCookie`) for guests. Sign-in
and registration merge a guest's lists into the account's. `WishlistRoutes` offers them over REST for the screens that
follow PRE-03b. The branch is built on `012-erp-adapter-seam` because `CanonicalProduct` is drawn out of its stock
identity.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/Catalogue/CanonicalProduct.php`, `mizzey-site/src/Wishlist/` |
| Testing | `t56-wishlist`: in-process, and over HTTP as a guest in English and Arabic; `t53` for the shared resolver |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | WooCommerce has no wishlist (P-015) and tracks recently viewed only with its widget, by language record; the gaps are recorded |
| M-7 | Each rule has an automated test and a mutation check (`evidence/mutation.txt`) |
| M-9 | Customer-facing messages in English and Arabic |

## Design

| File | What it does |
|---|---|
| `CanonicalProduct.php` | The canonical record of any language record, read from the translation group's rows; the record to show in the current language |
| `GuestCookie.php` | A list of ids in an HTTP-only, SameSite=Lax cookie with its expiry and an HMAC over both (`wp_salt('auth')`) |
| `Wishlist.php` | Add, remove, move to cart, merge on `wp_login` and `user_register`; account meta or guest cookie |
| `RecentlyViewed.php` | Records a product page view on `template_redirect`; lists products in the current language |
| `WishlistRoutes.php` | The REST routes |

## Criterion to test map

| AC | `t56-wishlist` asserts |
|---|---|
| AC-1 | English and Arabic saves are one entry; removal by the Arabic record; a missing product not saved |
| AC-2 | A customer's list on the account, there after signing in afresh |
| AC-3 | The signed cookie and its lifetime; a planted id and a stretched lifetime ignored; expiry; capacity; the merge on sign-in and registration, no duplicates, idempotent, the guest's cleared |
| AC-4 | A product and an Arabic variation moved in one action; an out-of-stock item refused with its reason and kept; an unsaved item refused |
| AC-5 | One entry per product across languages, most recent first, shown in Arabic on an Arabic page; at most 15; a customer's kept across sessions |
| FR-005 | Over HTTP: save, read back, the Arabic record on the Arabic address, move, refusal, removal |

## Risks

| Risk | Handling |
|---|---|
| The cart's stock check is WooCommerce's until #306 | #306 routes the cart's stock questions through the seam (D-19 item 5) |
| The screens change the flow | The REST routes are the screens' only dependency |
