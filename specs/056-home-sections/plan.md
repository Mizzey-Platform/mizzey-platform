# Implementation Plan: what the home page's sections draw from

**Branch**: `056-home-sections` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Storefront\HomeSections`: the badge taxonomy and its mirror of the product's badges; "Show on the home
page" and a position on categories, brands and collections; `GET mizzey/v1/home/terms`.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/Storefront/HomeSections.php`; `mizzey-site/wpml-config.xml` (the badge taxonomy is not translated: its terms are shared) |
| Testing | `t96-home-sections`: a Product Collection block on a page, fetched over HTTP; the REST route in both languages |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in US-02-01 or US-02-03 for the rows traced |
| M-4 | The block editor and WooCommerce's Product Collection block carry the sections; only the badge filter and the home flags are ours |
| M-7 | Every criterion has a t96 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t96-home-sections` asserts |
|---|---|
| AC-1 | The flagged product's terms, its Arabic duplicate's, none on another |
| AC-2 | A page's Product Collection block by "Trending": the flagged product only; another flagged, both; an empty rule, the page whole |
| AC-3 | Two categories in their position in English and Arabic, one unmarked absent, another taxonomy refused; a brand and a collection listed |
