# Implementation Plan: ERP adapter seam

**Branch**: `012-erp-adapter-seam` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

One gateway (`MizzeySite\Stock\StockGateway`) answers every stock question and carries every stock change, behind a
`StockSource` adapter supplied through the `mizzey_stock_source` filter. The default adapter refuses, so stock is
unknown and not sellable until PRE-09's adapter is bound. A product or variation in either language resolves to its
translation group's source record. Failures are retried a bounded number of times with the same operation key;
refusals are not; every attempt is logged through WooCommerce's logger under `mizzey-stock`. No ERP is called.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/Stock/` |
| Testing | `t53-erp-adapter-seam`: in-process, with a mock adapter that keeps the contract and a recording logger |
| Callers | None yet. #306 (cart) and #255 (checkout) call the gateway and close WooCommerce's local stock writers (D-19 item 5) |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | No ERP mechanism chosen; AC-7 and AC-8 pending PRE-09; the seam holds whatever PRE-09 decides |
| M-4 | WPML's translation group and WooCommerce's logger are used as they are; the gap (an external stock source, retries) is recorded in Native coverage |
| M-7 | Stock paths have automated tests and mutation checks (`evidence/mutation.txt`) |
| M-10 | PRE-09 is an ERP input: a mock and a contract stand in |

## Design

| File | What it does |
|---|---|
| `StockSource.php` | The adapter interface and its contract: level, adjust once per operation key, unavailable (retried) or refused (not) |
| `UnconfiguredStockSource.php` | The default: refuses everything |
| `StockIdentity.php` | Resolves any language record to the translation group's source record, read from the group's own rows; no identity when the group does not list the record |
| `StockGateway.php` | Resolves, asks, retries (default 3 attempts, `mizzey_stock_attempts`), keeps the key, logs every attempt, answers unknown when the source did not answer |
| `StockItem.php`, `StockAnswer.php` | The identity asked about; known with a quantity or unknown with a reason, unknown never sellable |
| `StockSourceUnavailable.php`, `StockSourceRefused.php` | The two kinds of failure |

## Criterion to test map

| AC | `t53-erp-adapter-seam` asserts |
|---|---|
| AC-1 | The adapter bound through the filter is asked under the source identity; with none bound, nothing is sellable |
| AC-2 | A product's and a variation's English and Arabic records (WPML duplicates) resolve to one identity; a page, a missing record and no record have none |
| AC-3 | Unknown on refusal, exhaustion and a broken adapter; never sellable; quantity checks |
| AC-4 | Two failures then an answer: three attempts; endless failure: three, then unknown; a refusal once |
| AC-5 | A change applied then its answer lost: the retry keeps the key and the stock moves once; the same key again changes nothing |
| AC-6 | Every attempt logged under `mizzey-stock`; failures as warnings, a broken adapter as an error |
| AC-7, AC-8 | Pending PRE-09 |

## Risks

| Risk | Handling |
|---|---|
| A caller bypasses the gateway and writes WooCommerce stock | #255 and #306 close those writers with tests (D-19 item 5) |
| WPML answers for an unregistered record | No identity, so unknown (fail closed) |
| Retries slow a page | Bounded (3 attempts, 100 ms apart); PRE-09 sets freshness and caching |
