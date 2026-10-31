# Implementation Plan: cart

**Branch**: `018-cart` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's cart and Store API cart already list, update, remove, total and take coupons, keep a signed-in
customer's cart and move a guest's session to the account at sign-in. Six small pieces close the gaps: the stock check
through the adapter, a stand-in stock fixture for development and staging, the merge of the saved and guest carts, the
guest cart window as a setting, the free shipping progress on the Store API cart, suggestions from the related-products
assignment, and moving a line to the wishlist. The designed cart and mini-cart are PRE-03b's.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Cart/`: `CartStock`, `CartMerge`, `GuestCartRetention`, `FreeShippingProgress`, `CartSuggestions`; `src/Stock/FixtureStockSource`; `Wishlist::fromCart()` and its route |
| Extension points | `woocommerce_check_cart_items` (classic cart and checkout, and the Store API's cart errors), `woocommerce_load_cart_from_session`, `set_logged_in_cookie`, `wc_session_expiration` and `wc_session_expiring`, `woocommerce_general_settings`, `woocommerce_store_api_register_endpoint_data` (cart, namespace `mizzey`), `woocommerce_cart_crosssell_ids` |
| Testing | `t59-cart`: in-process, and over HTTP for the Store API cart, sign-in and registration with a guest cart, and the guest session cookie |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | No ERP mechanism is chosen: the cart asks the seam; the fixture is a stand-in on non-production sites only |
| M-4 | Native first; each gap is in Native coverage |
| M-7 | Every criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| D-19 item 5 | Every stock question goes through the adapter and fails closed; nothing here writes stock |
| FIX-04 | Every customer-facing message has its Arabic |

## Design

| Piece | What it does |
|---|---|
| `CartStock` | On `woocommerce_check_cart_items`: lines counted together by stock identity, each asked of the gateway; unknown, short and sold out each named as an error, which blocks checkout on both carts |
| `FixtureStockSource` | Bound by the gateway's filter when `mizzey_stock_fixture` is `yes` and the site is not production; answers from the canonical record's stock fields; refuses changes |
| `CartMerge` | `set_logged_in_cookie` marks the merge for every sign-in; on `woocommerce_load_cart_from_session`, before WooCommerce's own merge, the saved and guest carts are combined and capped (sold individually, purchase limit, confirmed stock), WooCommerce's mark is cleared, the result is saved to the account and a notice names lowered items |
| `GuestCartRetention` | The guest session lasts the set days (30), renewed a day before it ends; the setting under General |
| `FreeShippingProgress` | Items needed for an automatic free-shipping promotion the cart otherwise qualifies for, and the amount needed for a free shipping method's minimum, measured as WooCommerce measures it; on the Store API cart |
| `CartSuggestions` | The cart's suggestions are its items' Upsells, less what is in the cart |
| `Wishlist::fromCart()` | Saves a line's product and removes the line; `POST mizzey/v1/wishlist/from-cart` |

## Criterion to test map

| AC | `t59-cart` asserts |
|---|---|
| AC-1, AC-3 | [HTTP] Store API: a line's name, images, price and quantity; quantity changed; totals; an unknown code refused with a message; the line removed |
| AC-2 | A line moved to the wishlist leaves the cart; a missing line changes nothing |
| AC-4 | One unit: one more item, or 400 more; two units: free; no item promotion: 200 more, then free; [HTTP] on the Store API cart |
| AC-5 | The assigned product not in the cart, never Cross-sells; nothing assigned, nothing; [HTTP] on the Store API cart |
| AC-6, AC-10 | [HTTP] A guest with 2 creams and 2 soaps signs in to an account saving 1 soap: 2 creams and 3 soaps saved; any route that sets the sign-in cookie marks the merge |
| AC-7 | No source: every line unconfirmed; short and sold out named; enough raises nothing; the cart check adds errors; the fixture's answers and its refusal to write; a bound adapter wins |
| AC-9 | [HTTP] A guest cart, then registering: kept |
| AC-11 | [HTTP] The guest session cookie lasts 30 days; the setting, its fallback, a signed-in session unchanged |
| AC-13 | 2 + 4 capped at 5; saved-only and guest-only lines kept; sold individually as 1; sold out removed; unanswered not capped |
| AC-8, AC-12 | Native blocks (screens after PRE-03b); #307's t56 (guest wishlist merge) |

## Risks

| Risk | Handling |
|---|---|
| The fixture is switched on where it should not be | It never binds on a production site; the option is off by default |
| A later WooCommerce changes the session merge | The merge runs before WooCommerce's own and clears its mark; t59 checks the result over HTTP |
