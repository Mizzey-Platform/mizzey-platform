# Implementation Plan: the chosen language is kept and followed

**Branch**: `052-language-preference` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

One class, `MizzeySite\I18n\LanguagePreference`: the choice kept in a cookie and, for an account, in user meta;
switcher links that carry it; a redirect on storefront pages to the chosen language; the account page's field and a
REST route; the cart setting held at keeping the cart; a head script for cached pages.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual 5.5.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/I18n/LanguagePreference.php`, registered in `mizzey-site.php` |
| Extension points | `icl_ls_languages`, `wpml_active_languages`, `template_redirect` (priority 0), `wp_login`, `user_register`, `woocommerce_edit_account_form`, `woocommerce_save_account_details`, `option__wcml_settings`, `rest_api_init` |
| Testing | `t92-language-preference`, over HTTP with the browser's cookies |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | AC-1 to AC-4 and AC-7 are US-01-02's criteria; AC-5 and AC-6 are D-31's gift, recorded |
| M-4 | WPML's switcher and addresses, WooCommerce Multilingual's cart sync and order item names; only the kept choice and its redirect are ours |
| M-7 | Every criterion has a t92 check; deliberate defects are caught (`evidence/mutations.txt`) |
| ADM-159 | The administration is never moved |

## Design

| Piece | What it does |
|---|---|
| `choose()`, `preferred()` | The choice on the device (cookie) and the account (meta); the account wins |
| `switcherLinks()`, `switchUrl()` | `?mizzey_language=<code>`, with a nonce for a signed-in customer |
| `route()` | Keeps a switcher link's choice and strips it; moves a storefront page in the other language to the chosen one |
| `translationUrl()` | WPML's translated address for the page (its query kept), or the account endpoint built in that language |
| `signedIn()`, `registered()` | The account's choice comes to the device; an account with none takes the device's; a new account the language it was opened in |
| `accountField()`, `saveAccountField()`, `routes()` | The account page's field; `GET` and `POST mizzey/v1/language` |
| `keepCart()` | `cart_sync.lang_switch` held at `WCML_CART_SYNC` |
| `cachedPageScript()` | For a guest's page served from a cache: the cookie and the `hreflang` alternates |

## Criterion to test map

| AC | `t92-language-preference` asserts |
|---|---|
| AC-1 | Both pages 200 in their own language with no cookie |
| AC-2 | The switcher link: 302 to the Arabic product, cookie set; WPML's switcher links carry the choice; the REST route keeps a guest's choice |
| AC-3 | Cookie `ar` on the English page: 302 to the Arabic one, and the other way; the query kept; an untranslated product served; REST and the administration not moved |
| AC-4 | Two items added in English: in the Arabic cart the same item and quantity named in Arabic, and back |
| AC-5 | The account field keeps `ar`; signing in on a new device brings it and moves the English page; the account wins over the device; a link without the nonce changes nothing; the switcher's own link changes it |
| AC-6 | An Arabic order's view link opened by a customer who chose English: 302 to the English order page, its item in English |
| AC-7 | The script on a guest's page; the cart setting held even when stored as emptying it |
