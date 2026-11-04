# Implementation Plan: the product sheet carries its Arabic, and its tabs

**Branch**: `053-sheet-arabic-tabs` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

A workbook reader beside the CSV reader; a `tab` on every row and report line; the mapping's optional columns
(`category_ar`, `brand_ar`) and its prefix's Arabic suffix (`option_<name>_ar`); the load writes Arabic terms and the
options' Arabic through WPML and WooCommerce Multilingual; W11 and F4.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WooCommerce Multilingual 5.5.7 (`stack.lock.json`) |
| Code | `Catalogue/Import/Workbook.php` (new), `ExportReader`, `Mapping`, `HeaderCheck`, `RowInterpreter`, `ValidationPass`, `ReportWriter`, `LoadRules`, `CatalogueLoad`; `import/catalogue-mapping.json` 1.1.0 |
| Testing | `t93-sheet-arabic-tabs` (new); t30 to t38, t86, t91 unchanged in what they assert |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Excel is in MIG-01's wording; the Arabic terms and options are MIG-10 to MIG-12's mapping in both languages |
| M-4 | WPML's term translations and WooCommerce Multilingual's editor fields; the workbook reader uses PHP's own extensions |
| M-7 | Each criterion has a t93 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Design

| Piece | What it does |
|---|---|
| `Workbook::read()` | Shared and inline strings, numbers as typed, every tab in order, columns by name, `tab` and line on each row; a mismatched tab refused |
| `ExportReader::isWorkbook()` | `.xlsx` with a zip signature goes to `Workbook` |
| `Mapping` | `category_ar`, `brand_ar` fields; `optional` columns; `arabic_suffix` on a prefix (`option_colour_ar` fills `ar_attribute_colour`); `arabicAttributeColumns()` |
| `HeaderCheck` | An optional mapped column may be absent; an Arabic option column without its option is F4 |
| `RowInterpreter` | `attributes_ar`; `tab`; W11 |
| `CatalogueLoad` | `categoryPath()` with the Arabic path; `arabicTerm()`; `optionTranslations()` into the translation editor's save |
| Reports | A `tab` column after `row` |

## Criterion to test map

| AC | `t93-sheet-arabic-tabs` asserts |
|---|---|
| AC-1 | A two-tab workbook (the second reordered, an empty third): 8 rows, tabs and lines; 349 and 180; the CLI validation plans 8 and names each tab |
| AC-2 | A tab with a renamed column: the read stops naming the tab and the column |
| AC-3 | The v2 sheet complete with its Arabic columns; the first sheet still complete |
| AC-4 | The Arabic product's category path and brand in Arabic; a translated category kept |
| AC-5 | The Arabic product's option values and names; each Arabic variation's values |
| AC-6 | W11 on a mismatched path; F4 on an Arabic column without its option |
