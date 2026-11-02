# Verification: cart

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, at `3a8305c` on `019-checkout`, the top of the Stage 3 stack, which held this branch to its third review, on the development runtime as it stood (not a fresh baseline reset): 55 scenarios, 5 failed. See the note below |
| t59, t47, t49 and t52 at the final commit | Pass. t59 is this feature's; t47, t49 and t52 check out through the cart, and switch the stock fixture on, as the cart now fails closed without a stock source |
| `evidence/mutation.txt` | Deliberate defects, each caught by t59 |
| `analysis.md` | The read-only review rounds |
| `python -m unittest discover -s tools/tests -t .` | 422 tests pass |

**The five failures.** `t17` and `t25` fail because of eleven product records ("t11 B1-...", ids 56486 to 56496) that an
interrupted t11 run left in the development runtime on 31 October (see specs/013-launch-roles/verification.md). `t47`,
`t49` and `t52` checked out with no stock source and, above this branch, without a district; they are updated here and
in specs/019, and pass.

| AC | Result in t59 |
|---|---|
| AC-1, AC-3 | Pass over HTTP. A line's name, images, price and quantity; quantity changed; totals; an unknown code refused with a message; the line removed |
| AC-2 | Pass, in-process and over HTTP. A line moved to the wishlist leaves the cart; without the session nonce, refused |
| AC-4 | Pass. Items or amount still needed; free from the chosen rate only; no item target where no free method takes a promotion, beside an entered code that keeps automatic promotions out, or while a competing automatic promotion runs; on the Store API cart |
| AC-5 | Pass, in-process and over HTTP. The assigned related product, never Cross-sells |
| AC-6, AC-10 | Pass over HTTP. A guest cart merged into the saved one on sign-in; signing in again elsewhere doubles nothing; any route that sets the sign-in cookie marks the merge |
| AC-7 | Pass. No source: unconfirmed; short and sold out named; the cart check's errors; the fixture's answers, its ledger once per key, a change it cannot store unknown, never WooCommerce's stock; WooCommerce's order stock writers off |
| AC-9 | Pass over HTTP. A guest cart, then registering: kept |
| AC-11 | Pass over HTTP. The guest session lasts the 30 days set |
| AC-13 | Pass. Quantities combined and capped by confirmed stock, shared per stock item; one of an item sold one at a time; local stock not a limit |
| AC-8, AC-12 | Native (WooCommerce's cart blocks) and #307's t56 |

## 2. Staging

Done 31 October 2026 on release v0.1.9, on the owner's order, after backup set 20261031-184401-manual; build clean, the staging check 6 of 6, every security baseline passing (`docs/2026-10-31-stage3-staging-verification.md`). t59 passes on staging, 31 of 31, with its stock fixture, its HTTP checks through the server's web server and PHP-FPM.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22).
