# Implementation Plan: manual product ordering within categories and collections

**Branch**: `061-manual-order` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Storefront\ManualOrder`: the term screen's "Product order", positions as product meta per term
(`_mizzey_pos_<term id>`) on every language record, and an ORDER BY that puts the positioned first on the term's page
and its single-term Store API query.

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | MER-03's wording; ADM-55 and ADM-57 for the term screens |
| M-4 | WooCommerce's ordering, term screens and product query; only the per-term positions are ours |
| M-7 | Every criterion has a t101 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t101-manual-order` asserts |
|---|---|
| AC-1 | The category page over HTTP: Charlie then Alpha first; the Arabic page the same |
| AC-2 | Store API in `menu_order`: the same; by price: price order |
| AC-3 | The collection's own positions, changed and followed at once |
| AC-4 | A save with a wrong nonce changes nothing |
