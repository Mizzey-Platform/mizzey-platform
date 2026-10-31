# Verification: promotion engine

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, on the development runtime as it stood (not a fresh baseline reset): 48 scenarios, 41 pass, 6 fact-finding, 1 failed. t52 passes its 65 checks |
| `evidence/mutation.txt` | Thirteen deliberate defects, each caught by t52 |
| `analysis.md` | Eleven read-only review rounds and what each changed |

The one failure is `t15-cost-sync-entrypoints`, the CSV import case. It fails the same way on `main` (`1ad0313`, two
runs of two, 31 October 2026) and alternates between its simple and variation rows, so it is not this feature's; it is
left for its own investigation.

| AC | Result in t52 |
|---|---|
| AC-1 | Pass. Percentage, fixed-amount and free-shipping rewards |
| AC-2 | Pass. Minimum cart value, minimum item count, products, categories, brands and first order only, with the first order checked again at checkout over HTTP |
| AC-3 | Pass. Automatic promotions with no code, the larger saving first then the earliest created (Q1), measured with tax, shipping and WooCommerce's rounding; a customer's code takes their place; the block cart's Store API lists the automatic promotion |
| AC-4 | Pass. Total and per-customer limits, a guest's email counted as the checkout counts it |
| AC-5 | Pass. Start and end dates; a start after the expiry is not saved |
| AC-6 | Pass. Product and category exclusions |
| AC-7 | Pass. The maximum cap on the cart, with the item limit, with sequential discounts, and when an order's coupons are recalculated |
| AC-8 | Pass. Every setting saves through the coupon screen |

Recorded limits (in the specification): a paid shipping rate that depends on the discounted value is not modelled; a
one-piaster tie with subtotal tax rounding and taxed shipping.

## 2. Staging (T009)

Not done. On the owner's approval: a promotion created on the coupon screen and seen in the cart.

## 3. Client acceptance (T010)

Not done. Through MS-UAT-2026-027 (DOD-09), with Stage 3 after Stage 2 (D-22).
