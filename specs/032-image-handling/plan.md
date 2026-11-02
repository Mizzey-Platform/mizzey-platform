# Implementation Plan: images and locale formatting

**Branch**: `032-image-handling` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

WordPress already scales, crops, lazy loads and formats per locale. Two filters close the image gap: an upload guard
on WordPress's upload and sideload prefilters, and WordPress's output-format filter to write WebP. NFR-04b needs no
code: the check proves the Western digits D-20 B2 locked.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4 (GD image editor, WebP supported), WooCommerce 11.1.0 |
| Code | `mizzey-site/src/Catalogue/ImageHandling.php` |
| Extension points | `wp_handle_upload_prefilter`, `wp_handle_sideload_prefilter` (the REST API and `media_handle_sideload` go through them), `image_editor_output_format` |
| Testing | `t72-image-handling`, in process through the real upload functions and the REST route; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4, ADR-0003 | Native WordPress mechanisms; one WebP authority |
| M-7 | Each criterion has an automated check |
