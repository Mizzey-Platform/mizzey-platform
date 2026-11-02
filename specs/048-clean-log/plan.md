# Implementation Plan: a clean application log

**Branch**: `048-clean-log` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

Three small classes in `mizzey-site/src/I18n/` remove the site's sources; the scenarios stop creating their own.

## Technical Context

| Item | Value |
|---|---|
| Code | `I18n/ProductTranslationJob.php`, `I18n/WcmlSettingsDefaults.php`, `I18n/WpmlProductSaveHook.php`; `Catalogue/Import/CatalogueLoad.php` uses the first |
| Testing | A recording error handler over the whole suite (every message with its call stack); t89 checks a product save and a translation write leave the error log unchanged |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | WooCommerce Multilingual's own editor job and installer default are used; nothing replaces them |
| M-8 | The WPML handler removal is pinned to WPML 4.9.7 |
