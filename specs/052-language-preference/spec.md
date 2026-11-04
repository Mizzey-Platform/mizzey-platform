# Feature Specification: the chosen language is kept and followed

**Feature Branch**: `052-language-preference`

**Created**: 2026-11-04

**Status**: Draft

**Work type**: requirement

**Input**: The owner, 4 November 2026 (translated from Arabic): a customer who adds items to the cart in English and
switches to Arabic must not lose them; the same for the wishlist and the orders. "If I am English, I must not open
any Arabic product, wherever I open it from; it must not switch me automatically without telling me." A guest
switches from the website; a signed-in customer's language is kept on the profile, recorded as a gift (D-31). The
owed part is NAV-02 and story US-01-02, with OP-14 recording that persistence is not built.

## Register trace [checked]

| ID | Scope | Stage | Register wording (short, verbatim) |
|---|---|---|---|
| NAV-02 | P1 | S1 | Language switcher (AR / EN) |

Story US-01-02 (Functional Specification MS-SPC-2026-032 v1.4), its criteria: the switcher on every page; the same
page in the other language; "The chosen language persists for the session and for a returning visitor on the same
device"; "Given items are in the cart, when the language is switched, then the cart is unchanged". Owned by #253.

## Context rows (no obligation here)

| ID | Scope | Why it matters here |
|---|---|---|
| ADM-159 | P1 | The administration is English only: never moved by the choice |
| ADM-159a | P2 | An Arabic administration: not built, and not owed because D-31 was given |
| WISH-01 to WISH-05 | P1 | The wishlist keeps the product, not a language's record (specs/015), so it shows in the language browsed: unchanged here |

## Open contract items

- None. OP-14 (persistence settled in the documents, not built) is closed by AC-2 and AC-3.

## Contractual acceptance criteria [checked]

| # | Criterion | Traces | Status |
|---|---|---|---|
| AC-1 | With no choice made, the address decides: each language's page answers in its own language, as search engines see it | NAV-02 | final |
| AC-2 | Switching language keeps the choice on the device and lands on the same page in the other language | NAV-02 | final |
| AC-3 | With a choice kept, a storefront page opened in the other language is answered with the same page in the chosen one, its query kept; a page with no translation is served as it is; the administration, REST and JSON are never moved | NAV-02 | final |
| AC-4 | Given items in the cart, when the language is switched, the cart is unchanged: the same items and quantities, named in the new language | NAV-02 | final |
| AC-5 | (D-31, a gift) A signed-in customer's choice is kept on the account: chosen on the account page or by the switcher, it follows them on a new device and wins over a device's older choice; a link made elsewhere cannot change it | NAV-02 | final |
| AC-6 | (D-31, a gift) An order opened by its link in the other language opens in the customer's language, its items named in it | NAV-02 | final |
| AC-7 | A page served from a page cache does the same in the browser; the store's cart setting cannot drift to emptying the cart on a switch | NAV-02 | final |

AC-5 and AC-6 go beyond NAV-02's wording (a device-level choice) and are given under D-31, recorded so they are never
read as owed. They trace NAV-02 because they extend its switcher.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The cart survives a switch (Priority: High)

1. **Given** two lamps in the cart in English, **When** the customer switches to Arabic, **Then** the cart holds the
   two lamps, named in Arabic, and back in English again.

### User Story 2 - English stays English (Priority: High)

1. **Given** a customer who chose English, **When** they open an Arabic product link (an email, an old order, a shared
   link), **Then** they see the same product in English.
2. **Given** a guest who never chose, **When** they open an Arabic link, **Then** they see it in Arabic, as before.

### User Story 3 - The account keeps it (Priority: Medium)

1. **Given** a customer who chose Arabic on their account, **When** they sign in on a new phone, **Then** the store is
   in Arabic there too.

### Edge Cases

- A page with no translation in the chosen language is served as it is; the customer is never sent to the home page.
- An account created by an administrator or a command has no choice until the customer makes one.
- Signing in on a device that already chose, with an account that has no choice yet: the account takes the device's.

## Native coverage

| Need | Native | Gap and how it is closed |
|---|---|---|
| The switcher, the same page in the other language | WPML's language switcher and its translated addresses | Used as it is |
| The chosen language persists | WPML takes the language from the address only; its browser-language redirect guesses, it does not keep a choice | A cookie and, for an account, user meta; a redirect on `template_redirect` |
| The cart on a switch | WooCommerce Multilingual's cart setting, "keep the cart" (`WCML_CART_SYNC`) | Held by a filter so it cannot drift to emptying the cart |
| An order's items in the language browsed | WooCommerce Multilingual names the order's items in the page's language on the order page | Used as it is; the account endpoints' addresses in the other language are built here, as WPML's switcher points at the account's home |

## Requirements

### Functional Requirements

- **FR-001**: The switcher's links MUST carry the visitor's choice; following one MUST keep it on the device and, for a
  signed-in customer with the link's nonce, on the account (AC-2, AC-5).
- **FR-002**: A storefront `GET` in the other language than the kept choice MUST be redirected (302, not cached) to the
  same page in the chosen language when it exists there (AC-3, AC-6).
- **FR-003**: The account page MUST offer the language, each named in itself (AC-5); `mizzey/v1/language` MUST read and
  keep the choice for the storefront's own switcher.
- **FR-004**: The cart setting for a language switch MUST be held at keeping the cart (AC-4, AC-7).
- **FR-005**: A guest's page MUST carry a short script that does the same from the cookie and the `hreflang`
  alternates when the page is served from a cache (AC-7).

### Key Entities

- **The choice**: `en` or `ar`; the cookie `mizzey_language` (a year, not HTTP-only, nothing private); the user meta
  `mizzey_language`.

## Optional safeguards (not owed)

- None.

## Future or Option C items (not built)

- An Arabic administration (ADM-159a, P2).
- Account emails (password reset and the like) in the account's language: they follow WordPress's own language
  handling, unchanged.

## Success Criteria

- **SC-001**: No customer who chose a language lands on a page in the other one, when the page exists in theirs.
- **SC-002**: No cart is emptied by a language switch.

## Delivery stage

Contractual stage: S1, with the header and navigation (#253). Engineering timing: 4 November 2026, the function layer
(D-27); the switcher's designed look is PRE-03b's.

## Assumptions

- The page cache passes `wordpress_logged_in` requests to PHP (Cloudways' default and WordPress's), so signed-in
  customers are always moved by the server; for guests on a cached page the script does it.
