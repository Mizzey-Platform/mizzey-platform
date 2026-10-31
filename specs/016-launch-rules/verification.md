# Verification: launch business rules

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, at `c5c6c80` (after the fourth review), on the development runtime as it stood (not a fresh baseline reset): 50 scenarios, 2 failed, every other one passing or fact-finding. See the note below |
| t57, t52 and t55 at the final commit | Pass. The only change after `c5c6c80` is the fifth review's change to the promotion choice and its two checks; t57 (these rules), t52 (the promotion engine it extends) and t55 (promotions in admin) are the scenarios it touches |
| `evidence/mutation.txt` | Twenty-eight deliberate defects, each caught by t57 |
| `analysis.md` | The read-only review rounds |

**The two failures.** `t17` and `t25` fail because of eleven product records ("t11 B1-...", ids 56486 to 56496) that an
interrupted t11 run left in the development runtime on 31 October (see specs/013-launch-roles/verification.md). Neither
touches promotions, refunds or accounts. Removing the records, or a baseline reset, waits for the owner.

| AC | Result in t57 |
|---|---|
| AC-1 | Pass, against the recorded example (OD-03 pending). Registering issues the template's discount once per email, owned by and restricted to the customer, first order only, expiring after the set days; the template itself cannot be used; brand conditions are copied |
| AC-2 | Pass, against the recorded example (OD-04 pending). Two units of any products ship free as an automatic promotion; one unit pays the flat rate |
| AC-3 | Not verified here: pending PRE-09 (the store side of the reservation is specs/019) |
| AC-4 | Pass. The setting is in WooCommerce's general settings, off by default: one promotion per order on the cart, on orders and through the Store API; on, an entered code, an automatic discount and automatic free shipping combine, valued as WooCommerce applies them |
| AC-5 | Pass. A refund above the order's paid total, or above a line's paid amount after discounts less earlier refunds, is refused |
| AC-6 | Not verified here: pending PRE-09 (the store side of the release is specs/019) |
| AC-7 | Pass over HTTP. A guest order, then the account made on the order-received page, has the order linked |
| AC-8 | Pass. With the free-shipping promotion the paid rate is not offered and shipping is 0 |
| AC-9 | Pass. 5,000 off a 100 order takes 100 off; the total is not negative |

## 2. Staging (T007)

Not done. On the owner's approval, with the next release.

## 3. Client acceptance (T008)

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22), once OD-03 and OD-04 replace the examples.
