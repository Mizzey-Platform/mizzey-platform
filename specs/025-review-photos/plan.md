# Implementation Plan: photos in reviews

**Branch**: `025-review-photos` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

A REST route for the reviewer to attach up to 5 photos to their held review; each photo checked, cleaned and stored
privately; served through the site only while the review is approved; listed under the approved review.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, PHP GD (JPEG, PNG, WebP) |
| Code | `mizzey-site/src/Reviews/ReviewPhotos.php`; `mizzey-site/assets/review-photos.htaccess` |
| Extension points | REST `mizzey/v1/reviews/{id}/photos`; `template_redirect` (`?mizzey-review-photo={id}-{n}`); `woocommerce_review_after_comment_text`; `delete_comment` |
| Testing | `t66-review-photos`: in-process uploads through `rest_do_request()`; over HTTP, the serving |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 (2.0.0) | WooCommerce has no review images; our own code (D-24) |
| M-5 | The gift is recorded as a gift (D-23), never as REV-06 |
| M-7 | Each requirement has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |

## Criterion to test map

| Item | `t66-review-photos` asserts |
|---|---|
| FR-001 | JPEG and PNG attached; a script named .jpg, an image over 6000 pixels, another customer, a sixth photo and a published review refused |
| FR-002, FR-003 | The stored file has no location or camera data, is 2000 pixels on its long side, has a random name; the folder has its `.htaccess` |
| AC-1, FR-004 | [HTTP] 404 while held, 200 once approved, 404 when sent back; the markup's alternative text |
| FR-005 | Deleting the review deletes the files |
