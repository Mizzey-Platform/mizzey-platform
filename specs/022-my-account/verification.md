# Verification: the account area

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| t63 at the final commit | Passes, on the development runtime as it stood (not a fresh baseline reset). The whole suite runs with the Stage 3 stack before release |
| `evidence/mutation.txt` | Deliberate defects, each caught by t63 |
| `analysis.md` | The read-only review rounds |

| AC | Result in t63 |
|---|---|
| AC-2 | Pass. Add, refuse three bad addresses, default copied with its district; another default, the sign-in number untouched; edit, delete, the default passed on; another customer refused; the capacity; REST 401 for a visitor, 201 for the customer; [HTTP] the customer's cookies without the REST nonce refused |
| AC-4 | Pass. Every status entered, in order, a failed delivery included, with the carrier and tracking number, through the order details hook |
| AC-5 | Pass for the menu: a Wishlist item after Orders opens the storefront's Wishlist page (IA-11), so the wishlist keeps one route (G-1). The page's list of saved items is specs/015's screen, after PRE-03b |
| AC-1, AC-3 | Native (WooCommerce's account pages) |
| AC-6 | Not built: waits for #280 |

## 2. Staging

Done 31 October 2026 on release v0.1.9, on the owner's order, after backup set 20261031-184401-manual; build clean, the staging check 6 of 6, every security baseline passing (`docs/2026-10-31-stage3-staging-verification.md`). t63 passes on staging, 10 of 10, once Egypt's governorates were carried to staging (#463).

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22).
