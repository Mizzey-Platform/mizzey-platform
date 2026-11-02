# Implementation Plan: search engine controls

**Branch**: `038-seo-controls` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

Three classes: `SeoFields` (the product SEO store of specs/036, extended to pages and the three product taxonomies, and the sitemap's do-not-index exclusion), `Redirects` (301 redirects and the missing-page log), `SearchSettings` (the Settings screen: redirects, missing pages, sitemap kinds, robots lines, verification codes). WordPress, WooCommerce and WPML already give the sitemap, robots file, hreflang and structured data.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Seo/SeoFields.php`, `Redirects.php`, `SearchSettings.php` |
| Data | Post and term meta with the product keys; options `mizzey_redirects`, `mizzey_missing_pages`, `mizzey_sitemap_excluded`, `mizzey_robots_extra`, `mizzey_verify_google`, `mizzey_verify_bing` (not autoloaded) |
| Extension points | `add_meta_boxes_page`, `save_post_page`, `{taxonomy}_edit_form_fields`, `edited_{taxonomy}`, `pre_get_document_title`, `mizzey_meta_description`, `get_canonical_url`, `mizzey_canonical_url`, `wp_robots`, `wp_sitemaps_*`, `robots_txt`, `template_redirect` (0 and 99), `admin_post_mizzey_search_engines` |
| Testing | `t78-seo-controls`, read back from the pages over the real request; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4, D-24 | Native sitemap, robots, hreflang, structured data; our own redirects instead of a plugin |
| ADR-0003 | One SEO store for products, pages and terms |
| M-7 | Each criterion has an automated check |
