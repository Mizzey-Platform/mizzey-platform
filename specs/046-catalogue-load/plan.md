# Implementation Plan: the initial catalogue load

**Branch**: `046-catalogue-load` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

`CatalogueLoad` turns the validation pass's decisions into a plan file and runs it in three Action Scheduler phases:
the English records, then the Arabic records through WooCommerce Multilingual's editor, then the images. Two CLI
commands start and follow it. The mapping gains the fields #295 and #296 need.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Catalogue/Import/CatalogueLoad.php`; `Mapping::FIELDS` (sale price, cost, category, brand, images, image alt, SEO title and description); `RowInterpreter` (several image columns); `Cli/CatalogueCommand` (`load`, `status`) |
| Data | Option `mizzey_catalogue_run`; the plan file in the run directory; attachment meta `_mizzey_source_image`; product meta `_mizzey_source_asin` |
| Testing | `t86-catalogue-load`, the phases run by WP-CLI in other processes; the 49 real products locally (evidence, counts only) |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | WooCommerce, WCML, Action Scheduler and WordPress media do the writing |
| #247 | The sources-first sequence A11 measured as safe, with a test behind it |
| M-7 | Each criterion has an automated check |
