# Verification: the invoice

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7, mPDF 8.3.1.

| Evidence | What it shows |
|---|---|
| t64 at the final commit | Passes |
| `evidence/mutation.txt` | Deliberate defects, each caught by t64 |
| `analysis.md` | The read-only review rounds, the last READY |

| AC | Result in t64 |
|---|---|
| AC-1 | Pass. Consecutive numbers, asked again the same, an unpaid order none; the counter set back to a held number, a trashed order's number, and a failed database check each leave no repeat |
| AC-2 | Pass with sample seller details (pending OD-09, CR-12). Seller, numbers, billing in the Egyptian structure, unit price, discount, shipping, a fee, the total, the refund and the net, as independent numbers; a three-decimal currency; an Arabic order in Arabic, right to left, from an English request; the PDF with DejaVu Sans |
| AC-3 | Pass. Staff get the orders list action and the order screen's link; a customer does not |
| AC-4 | Pass over HTTP. The account's orders link downloads the PDF; an unpaid order has none; no nonce, or another customer with a nonce of their own, 403 |

## 2. Staging

Done 31 October 2026 on release v0.1.9, on the owner's order, after backup set 20261031-184401-manual; build clean, the staging check 6 of 6, every security baseline passing (`docs/2026-10-31-stage3-staging-verification.md`). t64 passes on staging, 10 of 10, with `mizzey-site/lib/` in the release; the download over HTTP through the server's web server and PHP-FPM.

## 3. Client acceptance

Not done. Through MS-UAT-2026-027, with Stage 3 after Stage 2 (D-22), after OD-09 and CR-12.
