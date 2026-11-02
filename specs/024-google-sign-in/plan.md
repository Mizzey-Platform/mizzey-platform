# Implementation Plan: Google sign-in

**Branch**: `024-google-sign-in` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

Our own OpenID Connect client for Google: two REST routes, the start and the callback, the code exchanged server to
server, the ID token checked, and the account found, linked or created, one per email.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Accounts/GoogleSignIn.php` |
| Extension points | REST `mizzey/v1/google/start`, `mizzey/v1/google/callback`; `woocommerce_login_form_end`, `woocommerce_register_form_end`; `woocommerce_general_settings` |
| Testing | `t65-google-sign-in`: in-process with Google's token endpoint mocked (`pre_http_request`); over HTTP, the start and a refused callback |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 (2.0.0) | Neither WordPress nor WooCommerce signs in with Google; our own code, no plugin (D-24) |
| M-7 | Every criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every message has its Arabic |

## Design

| Piece | What it does |
|---|---|
| Start | A random state, PKCE verifier and nonce in a ten-minute transient keyed by the state's hash; the state in an HttpOnly, SameSite=Lax cookie; a redirect to Google's authorization endpoint |
| Callback | The transient is taken once and the cookie cleared; the state must equal the cookie; the code is exchanged at Google's token endpoint; the ID token checked (OpenID Connect Core 3.1.3.7) |
| Account | By the linked subject; else by the verified email (a never-verified account's password replaced first); else `wc_create_new_customer` with no verification message; the email marked verified; staff refused |
| Sign-in | `wp_set_auth_cookie`, then `wp_login`, so the cart and wishlist merges run; a redirect to the validated return address |

## Criterion to test map

| AC | `t65-google-sign-in` asserts |
|---|---|
| AC-1 | Off until configured; [HTTP] the start's redirect, state cookie, nonce and PKCE; [HTTP] a callback without the cookie refused and its state spent; the ID token's issuer, audience, expiry and nonce checks; the code exchange with the verifier; the callback signs in and returns to where it began |
| AC-2 | A new Google user is one customer account; signing in again is the same account; an existing customer's email signs in to it, a never-verified one's password replaced; staff and unverified emails refused |
