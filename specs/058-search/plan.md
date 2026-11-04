# Implementation Plan: the store's own product search

**Branch**: `058-search` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Search`: `Normalise` (text as the search compares it), `Synonyms` (the curated list and its admin
screen), `SearchIndex` (a table of each product record's searchable text, kept on save), `ProductSearch` (the
condition and order on WordPress's product query, the suggestion route, the empty-state suggestions, the term log).

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7 (`stack.lock.json`) |
| Tables | `{prefix}mizzey_search` (one row per product record), `{prefix}mizzey_search_log` |
| Extension points | `posts_join`, `posts_search`, `posts_search_orderby`, `found_posts`; `woocommerce_after_product_object_save`, `set_object_terms`, `edited_product_cat`, `edited_product_brand`, `deleted_post`; `rest_api_init`; `admin_menu`, `admin_post_mizzey_search_synonyms` |
| CLI | `wp mizzey search reindex` |
| Testing | `t98-search`, over HTTP through the Store API, the suggestion route and the results page |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in US-03-05 to US-03-08 for the rows traced; the list is curated, not automatic (SRCH-06's boundary) |
| M-4 | WordPress's product query, WooCommerce's results page, Store API and filters carry the search; only the matching, the list, the suggestions and the log are ours (D-27: no licensed component) |
| M-7 | Every criterion has a t98 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t98-search` asserts |
|---|---|
| AC-1 | Name, description, SKU, category and brand; every word required; Arabic by its Arabic name; an exact SKU before a name containing it |
| AC-2 | The normaliser's equalities; Arabic searches with plain alef, Latin digits and no marks find the products |
| AC-3 | Arabic, Franco-Arabic, a misspelling and two words from the list find the lipstick; an unlisted misspelling finds nothing; the Arabic store too; a several-word form |
| AC-4 | The list stored per line; the admin screen; a form added applies at once |
| AC-5 | Typing gives products, categories and the address; one letter nothing; Arabic records on the Arabic store; no result gives categories and best sellers |
| AC-6 | One log row per search with its count, 0 for none; none for suggestions; the log's columns hold nothing personal |
| AC-7 | The results page: the matches, its count and ordering |
