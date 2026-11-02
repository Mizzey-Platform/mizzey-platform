# Implementation Plan: accounts

**Branch**: `017-accounts` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce and WordPress already register, sign in, reset passwords, verify email, link verified guest orders and
turn a guest into a customer from the order confirmation. Four small pieces close the gaps: the verification email
sent at registration, an unticked marketing consent box, an optional mobile number usable at sign-in, and a
passwordless email sign-in link. The welcome discount is #290's and is verified here. Google sign-in (AUTH-10,
AUTH-13) is a second PR, once the owner approves installing the approved plugin.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Accounts/`: `VerificationOnRegistration`, `MarketingConsent`, `PhoneSignIn`, `SignInLink` |
| Testing | `t58-accounts`: in-process and through the real HTTP request (registration, sign-in, the order-received account, the sign-in link) |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-2 | AUTH-02 and AUTH-12 follow recorded defaults (the ownership audit, B16), stated as owner decisions, not client confirmations |
| M-4 | Native first: verification, reset keys, guest order linking and the order-received account are WooCommerce's; the four gaps are recorded in Native coverage |
| M-7 | Every criterion has an automated check; nine deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every customer-facing message has its Arabic in `languages/mizzey-site-ar.po` |

## Design

| Piece | What it does |
|---|---|
| `VerificationOnRegistration` | On `woocommerce_created_customer`, calls WooCommerce's own verification controller to send its email, when that email is enabled; guest orders link on `woocommerce_customer_email_verified` (native) |
| `MarketingConsent` | An unticked box on the registration form; `_mizzey_marketing_consent` keeps given or not, when and where |
| `PhoneSignIn` | An optional mobile number at registration, kept as `01XXXXXXXXX` (`+20` and `0020` accepted), one per account; the `authenticate` filter at priority 19 resolves a number to its one account, then WordPress checks the password |
| `SignInLink` | `POST mizzey/v1/sign-in-link` answers 202 with one message whatever the email; a customer gets a link whose token is kept only as an HMAC with its expiry (15 minutes by default); requests share `RecoveryLimiter`'s limits; following it signs in once; a wrong token does not spend it; staff are never sent one |

## Criterion to test map

| AC | `t58-accounts` asserts |
|---|---|
| AC-1 | The registration form requires only email and password |
| AC-2 | A number kept in local form, refused for a second account, a malformed one refused; signing in with it |
| AC-3 | [HTTP] Sign-in starts a session; an unknown email and a wrong password get the same generic message |
| AC-4 | A reset key works, is spent by the reset, and is refused once expired |
| AC-5 | The verification email is sent at registration; the account is unverified until the key is used |
| AC-6 | Registering applies the template's welcome discount |
| AC-7, AC-10 | [HTTP] A guest order, then a password alone on the confirmation page: the account carries name, phone and address and holds the order |
| AC-8 | The guest's earlier order stays unlinked until the emailed key verifies the account |
| AC-9 | No consent recorded unless ticked; ticked, kept with when and where |
| AC-11 | One link by email; a wrong token does not spend it; it works once; an expired one does nothing; staff get none; the same answer for known and unknown emails; the shared limit; [HTTP] following it signs in, again does not |

## Risks

| Risk | Handling |
|---|---|
| WooCommerce changes its internal verification controller | `VerificationOnRegistration` checks the class exists and does nothing otherwise; t58 fails if the email stops being sent |
| A sign-in link mailbox is shared | The link lives 15 minutes and works once; staff accounts never get one |
