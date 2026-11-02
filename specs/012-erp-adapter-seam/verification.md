# Verification: ERP adapter seam

Three states, kept apart (constitution M-7): technically verified, verified on staging, accepted by the client. Only
the first is claimed here.

## 1. Technical verification, 31 October 2026, development runtime

Stack: WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7.

| Evidence | What it shows |
|---|---|
| `evidence/final-suite.txt`, `final-suite.json` | The whole suite, serially, on the development runtime as it stood (not a fresh baseline reset), at `056e3a5`: 49 scenarios, 42 pass, 6 fact-finding, 1 failed. The failure is `t15-cost-sync-entrypoints`' CSV import case, which fails the same way on `main` (see specs/011-promotion-engine/verification.md) |
| t53 after the first review round (`7ce1979`) | 18 checks pass. The round changed only `src/Stock/StockGateway.php` and t53 |
| `evidence/mutation.txt` | Six deliberate defects, each caught by t53 |
| `analysis.md` | Two read-only review rounds |

| AC | Result in t53 |
|---|---|
| AC-1 | Pass. The adapter bound through `mizzey_stock_source` is asked under the source identity; with none bound, nothing is sellable |
| AC-2 | Pass. A product's and a variation's English and Arabic records (WPML duplicates) resolve to one identity, the variation's distinct from its parent's; a page, a missing record and no record have none |
| AC-3 | Pass. Unknown on refusal, on exhausted attempts, on a broken adapter and on an adapter whose name fails; never sellable |
| AC-4 | Pass. Three attempts on failure, one on refusal |
| AC-5 | Pass. A change applied and its answer lost is retried with the same key and moves stock once; the same key again changes nothing |
| AC-6 | Pass. Every attempt logged under `mizzey-stock`; a failing logger does not change the answer |
| AC-7, AC-8 | Pending PRE-09 |
| FR-007 | Pass. WooCommerce's own stock field is untouched (50 stays 50 while the source goes from 8 to 5) |

## 2. Staging (T007)

Done 31 October 2026 on release v0.1.9, on the owner's order, after backup set 20261031-184401-manual; build clean, the staging check 6 of 6, every security baseline passing (`docs/2026-10-31-stage3-staging-verification.md`). t53 passes on staging, 18 of 18.

## 3. Client acceptance (T008)

Not done. After PRE-09 and the client's approval (M-3).
