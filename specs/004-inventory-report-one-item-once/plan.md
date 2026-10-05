# Implementation Plan: the inventory report counts one physical item once

**Branch**: `004-inventory-report-one-item-once` | **Date**: 5 October 2026 | **Spec**: `spec.md`

**Input**: PBI #246, RPT-10 (P1-L, S1). Scope fixed by decision D-246-1: the current standard stock report only.

## Summary

The standard stock report lists language records, so a translated item is either listed twice or, in a session
narrowed to one language, an item that exists only in the other language is dropped. The correction is one
condition inside the report's own query that keeps a single representative record per translation group, with
the language scope widened for that query. Filtering, ordering, totals, paging and the export then all work on
physical items. No stock, synchronisation or storefront behaviour changes.

## Technical Context

| | |
|---|---|
| Runtime | WordPress 7.1.2, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual 5.5.7, PHP 8.3 |
| Code | `mizzey-site/src/Reporting/`, namespace `MizzeySite\Reporting` |
| Storage | None added. Reads `icl_translations`, `wp_posts` and what the report already reads |
| Tests | `mizzey-site/tests/integration/t24-stock-report-physical-items.php` (contract), `t25-stock-report-volume.php` (contract at the sizing baseline) |
| Scale | The OD-15 working baseline: 5,000 physical items, 10,000 product records |
| Constraints | The report's own query only. No change to CoreX, WooCommerce or the multilingual plugins |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced, wording respected | Yes | One row, RPT-10. Every criterion makes the standard report correct; none adds a report, a column or a filter. The dashboard figures and the older screen are excluded by D-246-1 |
| M-2 contradictions | Not touched | No affected id |
| M-3 P1-E waits for PRE-09 | Yes | AC-246-11 is `pending PRE-09`. No ERP mechanism, storage location or threshold is chosen |
| M-4 native first | Yes | The native report is kept. The gap is recorded with measurements in `research.md` R-2: no setting of the three plugins gives one line per physical item |
| M-5 three lists | Yes | Criteria, one optional safeguard (not built), and future items are separate in the spec |
| M-6 contractual stage fixed | Yes | S1, unchanged |
| M-7 evidence and tests | Yes | Every criterion has an automated assertion; the suite output is committed |
| M-8 versions pinned | Yes | Evidence is stated for the pinned versions only |
| M-9 languages | Yes | An admin screen, English only. No Arabic wording is added to the admin |
| M-10 inputs classified | Yes | No client or vendor input. The ERP input gates one criterion's acceptance only |
| CoreX client-site mode | Yes | Only `mizzey-site/` and `specs/` are edited |

No violation, so no complexity is tracked.

## Design

### The shared resolution: `MizzeySite\Reporting\PhysicalItems`

One class, used by any report that lists products (FR-009).

| Method | Does |
|---|---|
| `representativeOnly( string $postsTable ): string` | Returns a `WHERE` fragment that suppresses a record when its translation group holds another listable record that outranks it. A source-language record outranks a translation; between two of the same kind the lower id wins. A record in no group is never suppressed |
| `widenLanguageScope(): ?string` | Switches the multilingual plugin to all languages and returns the language to restore |
| `restoreLanguageScope( ?string $language ): void` | Puts it back |
| `available(): bool` | False when the multilingual plugin is absent, in which case every method is a no-op and the native report is unchanged |

### The report hook: `MizzeySite\Reporting\StockReport`

| Hook | Priority | Does |
|---|---|---|
| `parse_query` | first | For the report's query, widens the language scope before the plugin's own filters run |
| `posts_clauses` | 20, after the controller's 10 | Appends the representative condition to the `WHERE` |
| `posts_request` | last | Restores the language scope once the SQL exists |

The report's query is identified by the controller's own clause filter being registered, plus its two post types
(`research.md` R-5). That holds for the screen and the export and for nothing else.

### Why this is the smallest correction

Two classes, no storage, no setting, no template, no change to any stock write. The report keeps its native
filter, ordering, paging, columns and export.

## Criterion to test map

| Criterion | Asserted by |
|---|---|
| AC-246-01, AC-246-02 | t24: the low and out-of-stock lists, per language context |
| AC-246-03 | t24: the Arabic-only and English-only fixtures in every context; the item whose original is a draft |
| AC-246-04 | t24: the three contexts return identical lists and totals |
| AC-246-05 | t24: the pair shows 2, where its two records hold 2 each |
| AC-246-06 | t24: total equals lines; a two-per-page walk. t25: 5,000 items, the last page |
| AC-246-07 | t24: one low variation of a translated variable product |
| AC-246-08 | t24: WooCommerce's own exporter, in two contexts |
| AC-246-11 | Not testable before PRE-09. `pending PRE-09` |
| FR-010 | t24: stored stock unchanged after every read; an ordinary product query still narrowed |

## Project Structure

```text
mizzey-site/
  mizzey-site.php                       registers the report hook
  src/Reporting/PhysicalItems.php       the shared resolution
  src/Reporting/StockReport.php         the stock report hook
  tests/integration/t24-stock-report-physical-items.php
  tests/integration/t25-stock-report-volume.php
specs/004-inventory-report-one-item-once/
  spec.md  research.md  plan.md  tasks.md  analysis.md  verification.md
  checklists/requirements.md
  evidence/
```

## Risks

| Risk | Answer |
|---|---|
| A future WooCommerce version changes how the report queries | The scenario fails loudly: it asserts the list, not the hook. M-8's upgrade path reruns it |
| A corrupted translation group (A11) | The report follows the relationship as stored. Repair is #247 |
| A catalogue far beyond the baseline | Measured at 5,000 items. A stored representative flag is the growth path, a later decision |
| PRE-09 places the ERP's figures outside the store's stock fields | The report's data source would change; the resolution of records to items would still be needed and is reusable |
