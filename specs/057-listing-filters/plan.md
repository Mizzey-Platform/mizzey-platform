# Implementation Plan: the listing's filters, sorting and paging

**Branch**: `057-listing-filters` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

No site code. A scenario, `t97-listing-filters`, measures WooCommerce's own product query and category page against
each criterion, in both languages, over HTTP.

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in US-03-01, US-03-02 or US-03-04 for the rows traced |
| M-4 | All native; no custom code |
| M-7 | Every criterion has a t97 check. With no code of ours, there is nothing to mutate; the check is that the native behaviour holds |

## Criterion to test map

| AC | `t97-listing-filters` asserts |
|---|---|
| AC-1 | Category, brand, price 100 to 250, in stock: the matching products; the Arabic store answers the same filters with the Arabic record |
| AC-2 | Newest, price both ways, best selling; the sort kept with a filter added |
| AC-3 | Two pages of two, `X-WP-Total` 3 and `X-WP-TotalPages` 2 |
| AC-4 | The category page with its description; its address's filter and sort applied; breadcrumb and result count present |
