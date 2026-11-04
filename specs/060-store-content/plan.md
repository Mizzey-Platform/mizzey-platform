# Implementation Plan: promotional banners and each term's page content

**Branch**: `060-store-content` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Storefront\Promotions` (the post type, its box, the block and route) and `TermContent` (the term fields
and route). `wpml-config.xml` makes the banner type translatable.

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in the wording of the SSC rows traced |
| M-4 | The block editor, its visibility, preview and revisions, and WooCommerce's term fields are used as they are |
| M-7 | Every criterion has a t100 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t100-store-content` asserts |
|---|---|
| AC-1 | Over HTTP: the targeted page shows the current and the every-page banners, not the future or ended; another page only the every-page one; the REST route the same; a binned banner gone; the Arabic page the Arabic version |
| AC-2 | The dates and targets kept as saved through the box |
| AC-3 | A category's banner, alt, link and description; a collection's image; no change without the nonce; the Arabic category's own words and the shared banner over REST |
| AC-4 | A hidden paragraph not shown and still in the content |
