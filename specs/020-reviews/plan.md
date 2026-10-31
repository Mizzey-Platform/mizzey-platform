# Implementation Plan: product reviews

**Branch**: `020-reviews` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's product reviews already take a rating and text, keep the average of approved reviews and show it on the
product page and the listing. Three pieces close the gaps: who may review (D-17), every review held for moderation, and
the invitation after delivery. The designed form and display are PRE-03b's; the images (D-23) are #309's second part.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Reviews/`: `ReviewEligibility`, `ReviewModeration`, `ReviewInvitation`, `ReviewInvitationEmail` |
| Extension points | `preprocess_comment` (the form post and XML-RPC), `rest_pre_insert_comment`, `comments_open` and `comment_form_comments_closed`, `pre_comment_approved`, `pre_option_woocommerce_review_rating_required`, `woocommerce_email_classes`, `woocommerce_order_status_completed` and `_cod-collected` |
| Testing | `t61-reviews`: in-process, the form post through `wp_new_comment()`, the REST endpoint, the invitation through WooCommerce's mailer |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-2 | D-17 is applied as the owner's working decision; acceptance waits for the client's Stage 1 Approval Record |
| M-4 | Native first; each gap is in Native coverage |
| M-7 | Every criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every customer-facing message has its Arabic |

## Design

| Piece | What it does |
|---|---|
| `ReviewEligibility` | `refusal()`: not eligible without a delivered order line (not fully refunded) for the product's group (its variations' parent and its translations, via WPML), already reviewed with a pending or approved review in the group; checked on the form post (`wp_die`, 403) and the REST insert (403); the form is closed with the reason shown |
| `ReviewModeration` | Holds every product review from someone who does not moderate; the rating required |
| `ReviewInvitation`, `ReviewInvitationEmail` | On entering Completed or COD Collected, once per order: a WooCommerce email to the billing email, in the order's language (WPML language and locale switched around it), linking each product's reviews in that language |

## Criterion to test map

| AC | `t61-reviews` asserts |
|---|---|
| AC-1 | Eligible on a delivered order; not on processing, as a guest, on a fully refunded line, or for another product; the form post refuses the ineligible and a second review; the REST endpoint refuses; the form closed with the reason; the rating required |
| AC-2 | A delivered customer's review is held |
| AC-3 | The average is 0 while held and 4 once approved |
| AC-4 | One invitation to the billing email, linking the reviews; none on a second delivery; an Arabic order's in Arabic, the language left as found |

## Risks

| Risk | Handling |
|---|---|
| Another plugin reopens product comments | The insert checks still refuse |
| Many orders per customer | The eligibility query reads the customer's delivered orders only |
