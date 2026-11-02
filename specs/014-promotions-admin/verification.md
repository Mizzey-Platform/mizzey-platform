# Verification: promotions managed from admin

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, at `3b99ea0`, on the development runtime as it stood (not a fresh baseline reset): 49 scenarios, 2 failed, every other one passing or fact-finding. t55 passes its 12 checks; t52 (the promotion engine, whose rule this feature extends) passes |
| `evidence/mutation.txt` | Two deliberate defects, each caught by t55 |
| `analysis.md` | Two read-only review rounds |

**The two failures.** `t17` and `t25` fail because of eleven product records ("t11 B1-...", ids 56486 to 56496) that an
interrupted t11 run left in the development runtime on 31 October (see specs/013-launch-roles/verification.md). Neither
touches promotions. Removing the records, or a baseline reset, waits for the owner.

| AC | Result in t55 |
|---|---|
| AC-1 | Pass. An automatic promotion and a coupon code made on the coupon screen apply as set |
| AC-2 | Pass for conditions, rewards, limits and exclusions (two items, a cap of 15, an excluded product, a minimum spend, a per-customer limit). The combination setting is BR-004's, verified with #290 |
| AC-3 | Pass. A future start and a past end are both refused |
| AC-4 | Pass. Ticked Paused on the screen: published, refused ("This coupon is paused."), use count and record kept, a placed order keeps its discount on recalculation, its cancellation reverses the use; unticked: applies again; a numeric code paused too; an automatic promotion leaves and returns to a cart that holds it |

## 2. Staging (T004)

Done 31 October 2026 on release v0.1.9, on the owner's order, after backup set 20261031-184401-manual; build clean, the staging check 6 of 6, every security baseline passing (`docs/2026-10-31-stage3-staging-verification.md`). t55 passes on staging, 12 of 12.

## 3. Client acceptance (T005)

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22).
