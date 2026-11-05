# Implementation Plan: third-party admin styles kept to their own screens

**Branch**: `063-admin-plugin-styles` | **Date**: 2026-11-05 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Admin\PluginAdminStyles` on `admin_print_styles` at 15 (Bosta enqueues at 10, WordPress prints at 20):
it names the screen (order list, single order, Bosta's, other), removes Paymob's sheet from the order list and Bosta's
everywhere but its screens and the order screens, and on the order screens adds a short inline sheet after Bosta's
that restores WordPress's look, in the admin colour (`--wp-admin-theme-color`). The cash-on-delivery filter's label
carries a class for its size.

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | ADM-159's wording, "presented consistently across the standard store screens" |
| M-4 | WordPress's own enqueue API and colour variable; no third-party file edited |
| M-7 | Every criterion has a t103 check over HTTP; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t103-admin-plugin-styles` asserts |
|---|---|
| AC-1 | The order list loads no Paymob sheet, keeps Bosta's and prints ours after it, with the title, bulk actions, filter row and table header rules |
| AC-2 | Our sheet sets Bosta's filters on one row and its buttons in WordPress's style |
| AC-3 | The filter's label carries `mizzey-unremitted` and the sheet sizes its checkbox |
| AC-4 | The dashboard, posts and products screens load neither plugin's sheet, nor ours |
| AC-5 | Bosta's shipping settings and its Create Pickup page keep its sheets; Paymob's settings keep its sheet; a single order keeps both |
